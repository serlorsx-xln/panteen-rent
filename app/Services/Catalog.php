<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Costume queries for the storefront: the floor directory, listings, search and filters. */
final class Catalog
{
    public const SIZES = ['XS', 'S', 'M', 'L', 'XL', '2XL', 'FREE'];
    public const SORTS = ['new' => 'มาใหม่ก่อน', 'popular' => 'ยอดนิยม', 'price_asc' => 'ราคาน้อยไปมาก', 'price_desc' => 'ราคามากไปน้อย'];
    public const PER_PAGE = 24;

    /** @return list<array<string, mixed>> */
    public static function categories(bool $activeOnly = true): array
    {
        return DB::all(
            'SELECT cat.*, (SELECT COUNT(*) FROM costumes c WHERE c.category_id = cat.id AND c.status = \'active\') AS costume_count
               FROM categories cat ' . ($activeOnly ? 'WHERE cat.is_active = 1 ' : '') . 'ORDER BY cat.sort_order, cat.name'
        );
    }

    /** @return list<string> */
    public static function colors(): array
    {
        return array_column(DB::all("SELECT DISTINCT color_name FROM costumes WHERE status = 'active' AND color_name IS NOT NULL AND color_name <> '' ORDER BY color_name"), 'color_name');
    }

    /**
     * The floor directory: active categories in shop order, each with its two-digit
     * numeral, how many dresses hang there and where its prices start.
     * @return list<array<string, mixed>>
     */
    public static function directory(): array
    {
        $rows = DB::all(
            "SELECT cat.*,
                    (SELECT COUNT(*) FROM costumes c WHERE c.category_id = cat.id AND c.status = 'active') AS costume_count,
                    (SELECT MIN(c.rent_price) FROM costumes c WHERE c.category_id = cat.id AND c.status = 'active') AS min_price
               FROM categories cat WHERE cat.is_active = 1 ORDER BY cat.sort_order, cat.name"
        );
        foreach ($rows as $i => $r) {
            $rows[$i]['numeral'] = sprintf('%02d', $i + 1);
        }
        return $rows;
    }

    /**
     * How many active dresses on each floor are free for an event date: [slug => count].
     * @return array<string, int>
     */
    public static function freeByCategory(string $eventDate): array
    {
        $busy = Availability::busyExistsSql(...Pricing::defaultWindow($eventDate));
        $rows = DB::all(
            "SELECT cat.slug, COUNT(*) AS n FROM costumes c JOIN categories cat ON cat.id = c.category_id
              WHERE c.status = 'active' AND cat.is_active = 1 AND NOT $busy GROUP BY cat.slug"
        );
        return array_map('intval', array_column($rows, 'n', 'slug'));
    }

    /**
     * Other dresses from the same floor, for the foot of a dress page.
     * @param array<string, mixed> $costume
     * @return list<array<string, mixed>>
     */
    public static function related(array $costume, ?string $eventDate, ?int $userId, int $limit = 4): array
    {
        $busy = $eventDate ? Availability::busyExistsSql(...Pricing::defaultWindow($eventDate)) : '0';
        $fav = $userId ? '(SELECT COUNT(*) FROM favorites f WHERE f.costume_id = c.id AND f.user_id = ' . (int) $userId . ')' : '0';
        return DB::all(
            "SELECT c.*, cat.name AS category_name, cat.slug AS category_slug, cat.color AS category_color,
                    (SELECT path FROM costume_images i WHERE i.costume_id = c.id ORDER BY sort_order, id LIMIT 1) AS image,
                    $busy AS busy, $fav AS fav
               FROM costumes c JOIN categories cat ON cat.id = c.category_id
              WHERE c.category_id = ? AND c.id <> ? AND c.status = 'active' ORDER BY RAND() LIMIT " . max(1, $limit),
            [$costume['category_id'], $costume['id']]
        );
    }

    /**
     * The dress whose money lines the home page prints in full: a featured one at the shop's typical price, not its cheapest or its dearest.
     * @return array<string, mixed>|null
     */
    public static function showcase(): ?array
    {
        $prices = array_column(DB::all(
            "SELECT c.rent_price FROM costumes c JOIN categories cat ON cat.id = c.category_id
              WHERE c.status = 'active' AND cat.is_active = 1 ORDER BY c.rent_price"
        ), 'rent_price');
        if (!$prices) {
            return null;
        }
        $median = (float) $prices[intdiv(count($prices), 2)];
        return DB::one(
            "SELECT c.*, cat.name AS category_name, cat.slug AS category_slug,
                    (SELECT path FROM costume_images i WHERE i.costume_id = c.id ORDER BY sort_order, id LIMIT 1) AS image,
                    (SELECT credit FROM costume_images i WHERE i.costume_id = c.id ORDER BY sort_order, id LIMIT 1) AS image_credit
               FROM costumes c JOIN categories cat ON cat.id = c.category_id
              WHERE c.status = 'active' AND cat.is_active = 1
              ORDER BY c.is_featured DESC, ABS(c.rent_price - ?), c.id DESC LIMIT 1",
            [$median]
        );
    }

    /** Swatch colours for the colour names used in the catalogue; unknown names get no swatch. */
    public const SWATCHES = [
        'ขาว' => '#FFFFFF', 'ครีม' => '#EFE6D0', 'เขียว' => '#2F6B53', 'พีช' => '#F0B79A', 'แดง' => '#A3262E',
        'น้ำเงิน' => '#1F3A6B', 'ม่วง' => '#5B3A78', 'ดำ' => '#1B181C', 'ฟ้า' => '#9CC7E8', 'น้ำตาลแดง' => '#7A3B2E',
        'ชมพู' => '#E8A6B8', 'เหลือง' => '#EBCB5B', 'เงิน' => '#C3C5C8', 'ทอง' => '#C9A24B', 'เทา' => '#8C8A8D',
        'ส้ม' => '#E08A3C', 'น้ำตาล' => '#6B4A36', 'กรมท่า' => '#1B2A4A',
    ];

    /**
     * A listing URL that keeps the event date and the current filters, with overrides (null removes a filter).
     * Overriding the date with '' writes an empty date into the address, which tells the shop to forget
     * the one it was holding for this visitor.
     * @param array<string, mixed> $f
     * @param array<string, mixed> $over
     */
    public static function listUrl(string $base, array $f, array $over = []): string
    {
        $q = array_merge([
            'date' => $f['date'] ?? null,
            'q' => $f['q'] ?? '', 'size' => $f['size'] ?? '', 'color' => $f['color'] ?? '',
            'max' => ($f['max'] ?? 0) ?: null, 'free' => !empty($f['free']) ? 1 : null, 'new' => !empty($f['new']) ? 1 : null,
            'sort' => ($f['sort'] ?? 'new') !== 'new' ? $f['sort'] : null,
        ], $over);
        $forget = array_key_exists('date', $over) && !$over['date'];
        $q = array_filter($q, fn($v) => $v !== null && $v !== '' && $v !== false);
        // "only the free ones" means nothing without a day
        $keep = empty($q['date']) ? array_diff_key($q, ['free' => 1]) : $q;
        return url($base, ($forget ? ['date' => ''] : []) + $keep);
    }

    /**
     * Normalise query-string filters.
     * @param array<int|string, mixed> $q
     * @return array{date: string|null, category: string, size: string, color: string, q: string, max: int, free: bool, new: bool, sort: string, page: int}
     */
    public static function filters(array $q): array
    {
        $date = $q['date'] ?? '';
        $validDate = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) && $date >= today();
        $size = strtoupper((string) ($q['size'] ?? ''));
        return [
            'date'     => $validDate ? $date : null,
            'category' => is_string($q['category'] ?? null) ? preg_replace('/[^a-z0-9-]/', '', $q['category']) : '',
            'size'     => in_array($size, self::SIZES, true) ? $size : '',
            'color'    => is_string($q['color'] ?? null) ? mb_substr(trim($q['color']), 0, 50) : '',
            'q'        => is_string($q['q'] ?? null) ? mb_substr(trim($q['q']), 0, 80) : '',
            'max'      => is_numeric($q['max'] ?? null) ? max(0, (int) $q['max']) : 0,
            'free'     => !empty($q['free']) && $validDate,
            'new'      => !empty($q['new']),
            'sort'     => array_key_exists($q['sort'] ?? '', self::SORTS) ? $q['sort'] : 'new',
            'page'     => max(1, min(10000, (int) ($q['page'] ?? 1))),
        ];
    }

    /**
     * @param array{date: string|null, category: string, size: string, color: string, q: string, max: int, free: bool, new: bool, sort: string, page: int} $f
     * @return array{items:list<array<string, mixed>>, total:int, pages:int, match_total:int, free_total:?int}
     *   total follows the "free only" switch; match_total ignores it; free_total is null until a date is chosen.
     */
    public static function search(array $f, ?int $userId = null, int $perPage = self::PER_PAGE): array
    {
        $where = ["c.status = 'active'", 'cat.is_active = 1'];
        $params = [];
        if ($f['category'] !== '') {
            $where[] = 'cat.slug = ?';
            $params[] = $f['category'];
        }
        if ($f['size'] !== '') {
            $where[] = 'c.size_label = ?';
            $params[] = $f['size'];
        }
        if ($f['color'] !== '') {
            $where[] = 'c.color_name = ?';
            $params[] = $f['color'];
        }
        if ($f['max'] > 0) {
            $where[] = 'c.rent_price <= ?';
            $params[] = $f['max'];
        }
        if ($f['new']) {
            $where[] = 'c.arrived_at >= ?';
            $params[] = add_days(today(), -45);
        }
        if ($f['q'] !== '') {
            $where[] = '(c.name LIKE ? OR c.code LIKE ? OR c.description LIKE ? OR c.color_name LIKE ? OR cat.name LIKE ?)';
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']) . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $join = ' FROM costumes c JOIN categories cat ON cat.id = c.category_id WHERE ';
        $matchTotal = (int) DB::value('SELECT COUNT(*)' . $join . implode(' AND ', $where), $params);
        $busy = '0';
        $freeTotal = null;
        if ($f['date']) {
            [$p, $r] = Pricing::defaultWindow($f['date']);
            $busy = Availability::busyExistsSql($p, $r);
            $freeTotal = (int) DB::value('SELECT COUNT(*)' . $join . implode(' AND ', array_merge($where, ["NOT $busy"])), $params);
            if ($f['free']) {
                $where[] = "NOT $busy";
            }
        }
        $fav = $userId ? '(SELECT COUNT(*) FROM favorites f WHERE f.costume_id = c.id AND f.user_id = ' . (int) $userId . ')' : '0';
        $order = match ($f['sort']) {
            'price_asc'  => 'c.rent_price ASC, c.id DESC',
            'price_desc' => 'c.rent_price DESC, c.id DESC',
            'popular'    => "(SELECT COUNT(*) FROM bookings x WHERE x.costume_id = c.id AND x.status IN " . BookingService::RENTED_SQL . ") DESC, c.id DESC",
            'latest'     => 'c.arrived_at DESC, c.id DESC',
            default      => 'c.is_featured DESC, c.arrived_at DESC, c.id DESC',
        };
        $from = $join . implode(' AND ', $where);
        $total = $f['date'] && $f['free'] ? (int) $freeTotal : $matchTotal;
        $perPage = max(1, $perPage);
        $offset = ($f['page'] - 1) * $perPage;
        $items = DB::all(
            "SELECT c.*, cat.name AS category_name, cat.slug AS category_slug, cat.color AS category_color,
                    (SELECT path FROM costume_images i WHERE i.costume_id = c.id ORDER BY sort_order, id LIMIT 1) AS image,
                    $busy AS busy, $fav AS fav" . $from . " ORDER BY $order LIMIT $perPage OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage)), 'match_total' => $matchTotal, 'free_total' => $freeTotal];
    }

    /** @return array<string, mixed>|null */
    public static function byCode(string $code): ?array
    {
        return DB::one(
            'SELECT c.*, cat.name AS category_name, cat.slug AS category_slug, cat.color AS category_color
               FROM costumes c JOIN categories cat ON cat.id = c.category_id WHERE c.code = ?',
            [$code]
        );
    }

    /** @return list<array<string, mixed>> */
    public static function images(int $costumeId): array
    {
        return DB::all('SELECT * FROM costume_images WHERE costume_id = ? ORDER BY sort_order, id', [$costumeId]);
    }

    /**
     * Compare a member's measurements with a costume.
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $c
     * @return array{state:string, text:string}  state: fit|tight|loose|unknown
     */
    public static function fit(?array $user, array $c): array
    {
        if (!$user || !$user['bust_in'] || !$user['waist_in']) {
            return ['state' => 'unknown', 'text' => 'ใส่สัดส่วนในโปรไฟล์ แล้วเราจะบอกว่าชุดนี้พอดีไหม'];
        }
        $notes = [];
        $tight = false;
        $loose = false;
        $check = function (string $label, ?float $v, $min, $max) use (&$notes, &$tight, &$loose) {
            if ($v === null) {
                return;
            }
            if ($max !== null && $v > (float) $max) {
                $tight = true;
                $notes[] = "$label คับไป " . rtrim(rtrim(number_format($v - (float) $max, 1), '0'), '.') . ' นิ้ว';
            } elseif ($min !== null && $v < (float) $min) {
                $loose = true;
                $notes[] = "$label หลวม " . rtrim(rtrim(number_format((float) $min - $v, 1), '0'), '.') . ' นิ้ว';
            }
        };
        $check('อก', (float) $user['bust_in'], $c['bust_min'], $c['bust_max']);
        $check('เอว', (float) $user['waist_in'], $c['waist_min'], $c['waist_max']);
        $check('สะโพก', $user['hip_in'] !== null ? (float) $user['hip_in'] : null, null, $c['hip_max']);
        if ($tight) {
            return ['state' => 'tight', 'text' => 'อาจคับ: ' . implode(' · ', $notes)];
        }
        if ($loose) {
            return ['state' => 'loose', 'text' => 'อาจหลวม: ' . implode(' · ', $notes) . ' (ร้านกลัดเข็มให้ได้)'];
        }
        return ['state' => 'fit', 'text' => 'พอดีกับสัดส่วนของคุณ'];
    }
}
