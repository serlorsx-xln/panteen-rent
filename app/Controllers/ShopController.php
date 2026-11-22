<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Session;
use App\Core\View;
use App\Services\Availability;
use App\Services\BookingService;
use App\Services\Catalog;
use App\Services\Pricing;
use App\Services\Settings;
use App\Services\Shipping;

final class ShopController extends Controller
{
    /** The event date travels with the visitor: query string first, then session. */
    private function eventDate(): ?string
    {
        if (array_key_exists('date', $_GET)) {
            $f = Catalog::filters($_GET);
            if ($f['date']) {
                Session::put('event_date', $f['date']);
            } else {
                Session::forget('event_date');
            }
            return $f['date'];
        }
        $d = Session::get('event_date');
        if ($d && $d < today()) {
            Session::forget('event_date');
            return null;
        }
        return $d;
    }

    /** Query keys that belong to the listing. The home page hands them on. */
    private const LIST_KEYS = ['q', 'size', 'color', 'max', 'free', 'new', 'sort', 'page', 'category'];

    /**
     * Where a set of listing parameters lives: /c/{slug} when a floor is named, else /dresses.
     * @param array<int|string, mixed> $query
     */
    private function listingPath(array $query): string
    {
        $slug = Catalog::filters($query)['category'];
        $known = $slug !== '' ? DB::value('SELECT slug FROM categories WHERE slug = ? AND is_active = 1', [$slug]) : null;
        $keep = [];
        foreach (['date', 'q', 'size', 'color', 'max', 'free', 'new', 'sort', 'page'] as $k) {
            if (isset($query[$k]) && is_scalar($query[$k]) && (string) $query[$k] !== '') {
                $keep[$k] = (string) $query[$k];
            }
        }
        return ($known ? '/c/' . $known : '/dresses') . ($keep ? '?' . http_build_query($keep) : '');
    }

    /** The front of the shop: the date desk, the floor directory and what has just arrived. */
    public function home(): void
    {
        $date = $this->eventDate();
        if (array_intersect_key($_GET, array_flip(self::LIST_KEYS))) {
            redirect($this->listingPath($_GET));   // links from before the listing had its own address
        }
        $f = Catalog::filters(['date' => $date]);
        $f['sort'] = 'latest';
        $arrivals = Catalog::search($f, Auth::id(), 4);
        $floors = Catalog::directory();
        $freeBy = $date ? Catalog::freeByCategory($date) : null;

        if (wants_json()) {
            json_response([
                'ok' => true,
                'html' => View::make('partials/grid', ['items' => $arrivals['items'], 'filters' => $f, 'markNew' => false]),
                'date' => $date,
                'floors' => array_map(fn($fl) => [
                    'slug' => $fl['slug'],
                    'text' => View::make('partials/floor-count', ['floor' => $fl, 'freeBy' => $freeBy]),
                ], $floors),
            ]);
        }

        $show = Catalog::showcase();
        $look = Catalog::byCode(self::SITE_IMAGES['hero']['dress']);
        $this->view('shop/home', [
            'wide' => true,
            'overHero' => true,
            'look' => $look && $look['status'] === 'active' ? $look : null,
            'date' => $date,
            'filters' => $f,
            'floors' => $floors,
            'freeBy' => $freeBy,
            'arrivals' => $arrivals['items'],
            'total' => $arrivals['match_total'],
            'show' => $show,
            'showQuote' => $show ? Pricing::quote($show, today(), add_days(today(), Settings::int('base_days') - 1)) : null,
        ]);
    }

    /**
     * Every dress, or one floor of them, with the filters down the side.
     * @param array<string, mixed>|null $category
     */
    public function index(?array $category = null): void
    {
        $date = $this->eventDate();
        if (!$category && array_key_exists('category', $_GET)) {
            redirect($this->listingPath($_GET));   // the desk on the home page sends the floor as a field
        }
        $f = Catalog::filters(['date' => $date] + $_GET);
        $f['category'] = $category['slug'] ?? '';
        $result = Catalog::search($f, Auth::id());
        $base = $category ? '/c/' . $category['slug'] : '/dresses';
        $data = [
            'category' => $category,
            'base' => $base,
            'filters' => $f,
            'result' => $result,
            'window' => $f['date'] ? Pricing::defaultWindow($f['date']) : null,
        ];

        if (wants_json()) {
            json_response([
                'ok' => true,
                'html' => View::make('partials/grid', ['items' => $result['items'], 'filters' => $f]),
                'more' => $f['page'] < $result['pages'],
                'left' => max(0, $result['total'] - $f['page'] * Catalog::PER_PAGE),
                'total' => $result['total'],
                'date' => $f['date'],
                'count' => View::make('partials/desk-count', $data),
                'gcount' => View::make('partials/grid-count', $data),
                'chips' => View::make('partials/chips', $data),
                'empty' => View::make('partials/list-empty', $data),
            ]);
        }

        $floors = Catalog::directory();
        $floor = null;
        foreach ($floors as $fl) {
            if ($category && $fl['slug'] === $category['slug']) {
                $floor = $fl;
            }
        }
        $this->view('shop/list', $data + [
            'wide' => true,
            'title' => $category ? 'เช่า' . $category['name'] : 'ชุดทั้งหมด',
            'floors' => $floors,
            'floor' => $floor,
            'colors' => Catalog::colors(),
        ]);
    }

    public function category(string $slug): void
    {
        $cat = DB::one('SELECT * FROM categories WHERE slug = ? AND is_active = 1', [$slug]);
        if (!$cat) {
            abort(404);
        }
        $this->index($cat);
    }

    /** @return array<string, mixed> */
    private function costumeOr404(string $code): array
    {
        $c = Catalog::byCode($code);
        if (!$c || ($c['status'] === 'retired' && !Auth::isStaff())) {
            abort(404);
        }
        return $c;
    }

    public function show(string $code): void
    {
        $c = $this->costumeOr404($code);
        $date = $this->eventDate();
        $quote = null;
        $free = null;
        if ($date) {
            [$p, $r] = Pricing::defaultWindow($date);
            if ($p < today()) {
                $p = today();
            }
            $quote = Pricing::quote($c, $p, $r) + ['pickup' => $p, 'return' => $r];
            $free = $c['status'] === 'active' && Availability::isFree((int) $c['id'], $p, $r);
        }
        $ym = $date ? substr($date, 0, 7) : date('Y-m');
        // without scripts the calendar's month arrows come back here with the month they want
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($_GET['ym'] ?? ''))) {
            $ym = (string) $_GET['ym'];
        }
        $floors = Catalog::directory();
        $floor = null;
        foreach ($floors as $fl) {
            if ((int) $fl['id'] === (int) $c['category_id']) {
                $floor = $fl;
            }
        }
        $this->view('shop/dress', [
            'wide' => true,
            'title' => $c['name'],
            'floor' => $floor,
            'c' => $c,
            'images' => Catalog::images((int) $c['id']),
            'date' => $date,
            'quote' => $quote,
            'free' => $free,
            'fit' => Catalog::fit(Auth::user(), $c),
            'ym' => $ym,
            'busy' => Availability::upcomingBusy((int) $c['id']),
            'isFav' => Auth::check() && DB::value('SELECT 1 FROM favorites WHERE user_id = ? AND costume_id = ?', [Auth::id(), $c['id']]),
            'related' => Catalog::related($c, $date, Auth::id()),
            'timesRented' => (int) DB::value("SELECT COUNT(*) FROM bookings WHERE costume_id = ? AND status IN " . BookingService::RENTED_SQL, [$c['id']]),
        ]);
    }

    public function calendar(string $code): void
    {
        $c = $this->costumeOr404($code);
        $ym = (string) ($_GET['ym'] ?? date('Y-m'));
        if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
            $ym = date('Y-m');
        }
        $html = View::make('partials/calendar', [
            'c' => $c,
            'ym' => $ym,
            'selected' => Catalog::filters($_GET)['date'],
            'link' => $_GET['link'] ?? 'dress',
        ]);
        if (wants_json()) {
            json_response(['ok' => true, 'html' => $html]);
        }
        echo $html;
    }

    /** Live price + availability check for the booking form. */
    public function quote(string $code): void
    {
        $c = $this->costumeOr404($code);
        $event = (string) ($_GET['event'] ?? '');
        $pickup = (string) ($_GET['pickup'] ?? '');
        $return = (string) ($_GET['return'] ?? '');
        $ignore = isset($_GET['ignore']) && Auth::isStaff() ? (int) $_GET['ignore'] : null;
        $staff = Auth::isStaff();
        $errors = BookingService::validateDates($c, $event, $pickup, $return, $staff);
        if ($errors) {
            json_response(['ok' => false, 'errors' => $errors, 'message' => reset($errors)]);
        }
        // delivery choice: how the dress goes out and back, and the province it goes to (sets fees and transit days)
        $delivery = (string) ($_GET['delivery'] ?? 'pickup');
        $back = (string) ($_GET['back'] ?? 'store');
        $plan = Shipping::plan($pickup, $return, $delivery, $back, Shipping::zone(is_string($_GET['province'] ?? null) ? $_GET['province'] : null));
        $shipErrors = Shipping::check($plan, $staff);
        unset($shipErrors['address']); // the address is still being typed; the fees wait for the province
        if ($shipErrors) {
            json_response(['ok' => false, 'errors' => $shipErrors, 'message' => reset($shipErrors)]);
        }
        $conflicts = Availability::conflicts((int) $c['id'], $pickup, $return, $ignore, $plan['out_days'], $plan['back_days']);
        $q = Pricing::quote($c, $pickup, $return, null, $plan['delivery_fee'], $plan['return_fee']);
        $moving = $plan['out_days'] + $plan['back_days'] > 0;
        json_response([
            'ok' => true,
            'free' => $conflicts === [],
            'message' => $conflicts ? 'ไม่ว่างช่วง ' . thai_date($conflicts[0]['block_from'], false, false) . ' – ' . thai_date($conflicts[0]['busy_until'])
                . ' (รวมวัน' . ($moving ? 'ขนส่งและวัน' : '') . 'ซักชุด)' : 'ว่างตลอดช่วงที่เลือก',
            'quote' => $q,
            'plan' => $plan,
            'html' => View::make('partials/quote', ['q' => $q, 'c' => $c, 'pickup' => $pickup, 'return' => $return, 'plan' => $plan]),
        ]);
    }

    public function howTo(): void
    {
        $this->view('shop/how-to', ['title' => 'วิธีเช่าชุดและเงื่อนไข']);
    }

    public function credits(): void
    {
        $rows = DB::all('SELECT i.credit, i.path, c.code, c.name FROM costume_images i JOIN costumes c ON c.id = i.costume_id WHERE i.credit IS NOT NULL ORDER BY c.code');
        $this->view('shop/credits', ['title' => 'ที่มาของภาพ', 'rows' => $rows, 'site' => self::SITE_IMAGES]);
    }

    /** Photographs used by the site itself rather than by one dress. */
    public const SITE_IMAGES = [
        'hero' => [
            'where' => 'ภาพใหญ่หน้าแรก',
            'by' => 'Xander Miadelko',
            'url' => 'https://www.pexels.com/photo/elegant-woman-in-white-dress-in-sunlit-studio-30954526/',
            'note' => 'ตัดส่วน และต่อผนังด้านซ้ายให้กว้างขึ้นจากผนังในภาพเดิม',
            'dress' => 'GR-005',   // the dress worn in the photograph; the home page links to it
        ],
        'fitting' => [
            'where' => 'ภาพประกอบส่วนลองชุดที่ร้าน หน้าแรก',
            'by' => 'Los Muertos Crew',
            'url' => 'https://www.pexels.com/photo/a-woman-standing-near-hanging-wedding-gowns-8459404/',
            'note' => 'ตัดส่วน',
        ],
    ];
}
