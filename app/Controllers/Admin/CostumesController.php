<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Core\Upload;
use App\Core\Validator;
use App\Services\Availability;
use App\Services\Catalog;
use App\Services\Status;
use App\Services\BookingService;

final class CostumesController extends Controller
{
    private const RULES = [
        'code' => 'required|max:20',
        'category_id' => 'required|int',
        'name' => 'required|max:150',
        'description' => 'nullable|max:2000',
        'color_name' => 'nullable|max:50',
        'size_label' => 'required|in:XS,S,M,L,XL,2XL,FREE',
        'bust_min' => 'nullable|between:20,70',
        'bust_max' => 'nullable|between:20,70',
        'waist_min' => 'nullable|between:15,70',
        'waist_max' => 'nullable|between:15,70',
        'hip_max' => 'nullable|between:20,80',
        'length_in' => 'nullable|between:10,80',
        'rent_price' => 'required|between:0,100000',
        'extra_day_price' => 'required|between:0,100000',
        'deposit' => 'required|between:0,100000',
        'late_fee_per_day' => 'required|between:0,100000',
        'replacement_value' => 'nullable|between:0,1000000',
        'accessories' => 'nullable|max:300',
        'status' => 'required|in:active,maintenance,retired',
        'arrived_at' => 'nullable|date',
    ];
    private const LABELS = [
        'code' => 'รหัสชุด', 'category_id' => 'ประเภท', 'name' => 'ชื่อชุด', 'description' => 'รายละเอียด', 'color_name' => 'สี',
        'size_label' => 'ไซส์', 'bust_min' => 'รอบอกต่ำสุด', 'bust_max' => 'รอบอกสูงสุด', 'waist_min' => 'รอบเอวต่ำสุด', 'waist_max' => 'รอบเอวสูงสุด',
        'hip_max' => 'สะโพกสูงสุด', 'length_in' => 'ความยาว', 'rent_price' => 'ค่าเช่า', 'extra_day_price' => 'ค่าวันเพิ่ม',
        'deposit' => 'เงินมัดจำ', 'late_fee_per_day' => 'ค่าปรับต่อวัน', 'replacement_value' => 'มูลค่าชุด', 'accessories' => 'ของที่มากับชุด', 'status' => 'สถานะ', 'arrived_at' => 'วันที่รับเข้าร้าน',
    ];

    public function index(): void
    {
        $q = input_str('q');
        $cat = input_int('category');
        $status = $_GET['status'] ?? '';
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(c.code LIKE ? OR c.name LIKE ? OR c.color_name LIKE ?)';
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            array_push($params, $like, $like, $like);
        }
        if ($cat) {
            $where[] = 'c.category_id = ?';
            $params[] = $cat;
        }
        if (isset(Status::COSTUME[$status])) {
            $where[] = 'c.status = ?';
            $params[] = $status;
        } else {
            $where[] = "c.status <> 'retired'";
        }
        $base = ' FROM costumes c JOIN categories cat ON cat.id = c.category_id WHERE ' . implode(' AND ', $where);
        $pg = $this->paginate('SELECT COUNT(*)' . $base, $params, 40);
        $today = today();
        $items = DB::all(
            "SELECT c.*, cat.name AS category_name, cat.color AS category_color,
                    (SELECT path FROM costume_images i WHERE i.costume_id = c.id ORDER BY sort_order, id LIMIT 1) AS image,
                    (SELECT MIN(b.pickup_date) FROM bookings b WHERE b.costume_id = c.id AND b.status IN ('pending_payment','awaiting_verification','confirmed') AND b.pickup_date >= '$today') AS next_pickup,
                    (SELECT COUNT(*) FROM bookings b WHERE b.costume_id = c.id AND b.status IN " . BookingService::RENTED_SQL . ") AS rentals"
            . $base . " ORDER BY cat.sort_order, c.code LIMIT {$pg['limit']} OFFSET {$pg['offset']}",
            $params
        );
        foreach ($items as &$it) {
            $it['inventory'] = Availability::inventoryState($it)['state'];
        }
        unset($it);
        $this->admin('costumes/index', [
            'title' => 'ชุดทั้งหมด', 'items' => $items, 'pg' => $pg, 'q' => $q, 'cat' => $cat, 'status' => $status,
            'categories' => Catalog::categories(false),
        ]);
    }

    private function nextCode(?int $categoryId = null): string
    {
        $prefix = 'PT';
        if ($categoryId) {
            $slug = (string) DB::value('SELECT slug FROM categories WHERE id = ?', [$categoryId]);
            $prefix = strtoupper(substr(preg_replace('/[^a-z]/', '', $slug), 0, 2)) ?: 'PT';
        }
        $n = (int) DB::value("SELECT MAX(CAST(SUBSTRING_INDEX(code, '-', -1) AS UNSIGNED)) FROM costumes WHERE code LIKE ?", [$prefix . '-%']);
        return sprintf('%s-%03d', $prefix, $n + 1);
    }

    public function create(): void
    {
        $cats = Catalog::categories(false);
        $this->admin('costumes/form', [
            'title' => 'เพิ่มชุดใหม่',
            'c' => ['id' => null, 'code' => $this->nextCode($cats[0]['id'] ?? null), 'status' => 'active', 'size_label' => 'M', 'arrived_at' => today(),
                    'extra_day_price' => 100, 'late_fee_per_day' => 100, 'is_featured' => 0],
            'categories' => $cats,
            'images' => [],
            'codes' => array_combine(array_column($cats, 'id'), array_map(fn($c) => $this->nextCode((int) $c['id']), $cats)),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(?int $ignoreId): array
    {
        $_POST['code'] = strtoupper(input_str('code'));
        $rules = self::RULES;
        $rules['code'] .= '|unique:costumes,code' . ($ignoreId ? ',' . $ignoreId : '');
        $v = Validator::make($_POST, $rules, self::LABELS);
        $d = $v->data();
        if (!$v->fails()) {
            if (!DB::value('SELECT 1 FROM categories WHERE id = ?', [(int) $d['category_id']])) {
                $v->addError('category_id', 'เลือกประเภทชุด');
            }
            foreach ([['bust_min', 'bust_max'], ['waist_min', 'waist_max']] as [$a, $b]) {
                if ($d[$a] !== null && $d[$b] !== null && (float) $d[$a] > (float) $d[$b]) {
                    $v->addError($a, self::LABELS[$a] . 'ต้องไม่มากกว่า' . self::LABELS[$b]);
                }
            }
            if (!preg_match('/^[A-Z0-9-]+$/', $d['code'])) {
                $v->addError('code', 'รหัสชุดใช้ได้เฉพาะ A-Z, 0-9 และ -');
            }
        }
        if ($v->fails()) {
            back_with_errors($v->errors(), $ignoreId ? '/admin/costumes/' . $ignoreId : '/admin/costumes/new');
        }
        $d['category_id'] = (int) $d['category_id'];
        $d['is_featured'] = !empty($_POST['is_featured']) ? 1 : 0;
        foreach (['bust_min', 'bust_max', 'waist_min', 'waist_max', 'hip_max', 'length_in', 'color_name', 'description', 'arrived_at', 'replacement_value'] as $k) {
            $d[$k] = $d[$k] === '' ? null : $d[$k];
        }
        // one item per comma or line, trimmed and de-duplicated, so the packing list and inspection read cleanly
        $items = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\n]+/u', (string) $d['accessories']) ?: []), fn(string $x): bool => $x !== '')));
        $d['accessories'] = $items ? implode(', ', $items) : null;
        return $d;
    }

    public function store(): void
    {
        $d = $this->validated(null);
        $id = DB::insert('costumes', $d);
        $added = $this->saveImages($id);
        flash('success', 'เพิ่มชุด ' . $d['code'] . ' แล้ว' . ($added ? " พร้อมรูป $added รูป" : ' ยังไม่มีรูป เพิ่มรูปได้ด้านล่าง'));
        redirect('/admin/costumes/' . $id);
    }

    public function edit(string $id): void
    {
        $c = DB::one('SELECT * FROM costumes WHERE id = ?', [$this->intId($id)]);
        if (!$c) {
            abort(404);
        }
        $this->admin('costumes/form', [
            'title' => 'แก้ไข ' . $c['code'],
            'c' => $c,
            'categories' => Catalog::categories(false),
            'images' => Catalog::images((int) $c['id']),
            'inventory' => Availability::inventoryState($c),
            'upcoming' => DB::all(
                "SELECT b.*, u.first_name, u.nickname FROM bookings b JOIN users u ON u.id = b.user_id
                  WHERE b.costume_id = ? AND b.status IN " . BookingService::ACTIVE_SQL . " ORDER BY b.pickup_date LIMIT 10",
                [$c['id']]
            ),
            'codes' => [],
        ]);
    }

    public function update(string $id): void
    {
        $cid = $this->intId($id);
        $c = DB::one('SELECT * FROM costumes WHERE id = ?', [$cid]);
        if (!$c) {
            abort(404);
        }
        $d = $this->validated($cid);
        DB::update('costumes', $d, 'id = :id', ['id' => $cid]);
        flash('success', 'บันทึก ' . $d['code'] . ' แล้ว ราคาใหม่ใช้กับการจองครั้งต่อไป การจองเดิมยังใช้ราคาเดิม');
        redirect('/admin/costumes/' . $cid);
    }

    public function status(string $id): void
    {
        $cid = $this->intId($id);
        $status = input_str('status');
        if (!isset(Status::COSTUME[$status])) {
            abort(400);
        }
        $active = (int) DB::value("SELECT COUNT(*) FROM bookings WHERE costume_id = ? AND status IN " . BookingService::ACTIVE_SQL, [$cid]);
        DB::update('costumes', ['status' => $status], 'id = :id', ['id' => $cid]);
        flash($active && $status !== 'active' ? 'warning' : 'success',
            'เปลี่ยนสถานะเป็น “' . Status::label(Status::COSTUME, $status) . '” แล้ว' . ($active && $status !== 'active' ? " · ชุดนี้ยังมีการจองค้างอยู่ $active รายการ ตรวจในหน้าการจอง" : ''));
        back('/admin/costumes');
    }

    private function saveImages(int $costumeId): int
    {
        $files = $_FILES['images'] ?? null;
        if (!$files || !is_array($files['name'])) {
            return 0;
        }
        $count = 0;
        $order = (int) DB::value('SELECT COALESCE(MAX(sort_order), -1) FROM costume_images WHERE costume_id = ?', [$costumeId]);
        $errors = [];
        foreach ($files['name'] as $i => $name) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $file = ['name' => $name, 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
            $up = Upload::image($file, PUBLIC_PATH . '/media/costumes', 1600, ['thumb' => 900, 'mini' => 320]);
            if (!$up['ok']) {
                $errors[] = $name . ': ' . $up['error'];
                continue;
            }
            DB::insert('costume_images', [
                'costume_id' => $costumeId, 'path' => 'costumes/' . $up['path'],
                'credit' => input_str('credit') ?: null, 'sort_order' => ++$order,
            ]);
            $count++;
        }
        if ($errors) {
            flash('error', implode(' · ', $errors));
        }
        return $count;
    }

    public function addImage(string $id): void
    {
        $cid = $this->intId($id);
        if (!DB::value('SELECT 1 FROM costumes WHERE id = ?', [$cid])) {
            abort(404);
        }
        $picked = array_filter((array) ($_FILES['images']['error'] ?? []), fn($e) => $e !== UPLOAD_ERR_NO_FILE);
        if (!$picked) {
            flash('error', 'เลือกรูปอย่างน้อย 1 รูป');
            redirect('/admin/costumes/' . $cid . '#images');
        }
        $n = $this->saveImages($cid);
        if ($n) {
            flash('success', "เพิ่มรูป $n รูปแล้ว");
        }
        redirect('/admin/costumes/' . $cid . '#images');
    }

    public function deleteImage(string $id, string $img): void
    {
        $cid = $this->intId($id);
        $row = DB::one('SELECT * FROM costume_images WHERE id = ? AND costume_id = ?', [$this->intId($img), $cid]);
        if (!$row) {
            abort(404);
        }
        DB::run('DELETE FROM costume_images WHERE id = ?', [$row['id']]);
        $file = PUBLIC_PATH . '/media/' . $row['path'];
        $shared = (int) DB::value('SELECT COUNT(*) FROM costume_images WHERE path = ?', [$row['path']]);
        if (!$shared && is_file($file) && str_starts_with(realpath($file), realpath(PUBLIC_PATH . '/media'))) {
            @unlink($file);
            foreach (['thumb', 'mini', 'large'] as $copy) {
                @unlink(dirname($file) . '/' . $copy . '/' . basename($file));
            }
        }
        flash('success', 'ลบรูปแล้ว');
        redirect('/admin/costumes/' . $cid . '#images');
    }

    public function coverImage(string $id, string $img): void
    {
        $cid = $this->intId($id);
        $row = DB::one('SELECT * FROM costume_images WHERE id = ? AND costume_id = ?', [$this->intId($img), $cid]);
        if (!$row) {
            abort(404);
        }
        DB::run('UPDATE costume_images SET sort_order = sort_order + 1 WHERE costume_id = ?', [$cid]);
        DB::run('UPDATE costume_images SET sort_order = 0 WHERE id = ?', [$row['id']]);
        flash('success', 'ตั้งเป็นรูปหน้าปกแล้ว');
        redirect('/admin/costumes/' . $cid . '#images');
    }
}
