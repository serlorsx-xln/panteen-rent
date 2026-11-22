<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Core\Validator;
use App\Services\BookingService;

final class CategoriesController extends Controller
{
    public function index(): void
    {
        $items = DB::all(
            "SELECT cat.*,
                    (SELECT COUNT(*) FROM costumes c WHERE c.category_id = cat.id AND c.status <> 'retired') AS costume_count,
                    (SELECT COUNT(*) FROM costumes c WHERE c.category_id = cat.id) AS all_count,
                    (SELECT COUNT(*) FROM bookings b JOIN costumes c ON c.id = b.costume_id WHERE c.category_id = cat.id AND b.status IN " . BookingService::RENTED_SQL . ") AS rentals
               FROM categories cat ORDER BY cat.sort_order, cat.name"
        );
        $editing = isset($_GET['edit']) ? DB::one('SELECT * FROM categories WHERE id = ?', [(int) $_GET['edit']]) : null;
        $this->admin('categories', ['title' => 'ประเภทชุด', 'items' => $items, 'editing' => $editing]);
    }

    /** @return array<string, mixed> */
    private function validated(?int $id): array
    {
        $_POST['slug'] = strtolower(input_str('slug'));
        $v = Validator::make($_POST, [
            'name' => 'required|max:100|unique:categories,name' . ($id ? ",$id" : ''),
            'slug' => 'required|slug|max:100|unique:categories,slug' . ($id ? ",$id" : ''),
            'color' => 'required|hex',
            'description' => 'nullable|max:500',
            'sort_order' => 'nullable|int',
        ], ['name' => 'ชื่อประเภท', 'slug' => 'ชื่อในลิงก์', 'color' => 'สีประจำประเภท', 'description' => 'คำอธิบาย', 'sort_order' => 'ลำดับ']);
        if ($v->fails()) {
            back_with_errors($v->errors(), '/admin/categories' . ($id ? '?edit=' . $id : '') . '#form');
        }
        $d = $v->data();
        $d['color'] = strtoupper($d['color']);
        $d['sort_order'] = (int) ($d['sort_order'] ?? 0);
        $d['is_active'] = !empty($_POST['is_active']) ? 1 : 0;
        return $d;
    }

    public function store(): void
    {
        $d = $this->validated(null);
        DB::insert('categories', $d);
        flash('success', 'เพิ่มประเภท “' . $d['name'] . '” แล้ว');
        redirect('/admin/categories');
    }

    public function update(string $id): void
    {
        $cid = $this->intId($id);
        if (!DB::value('SELECT 1 FROM categories WHERE id = ?', [$cid])) {
            abort(404);
        }
        $d = $this->validated($cid);
        DB::update('categories', $d, 'id = :id', ['id' => $cid]);
        flash('success', 'บันทึกประเภท “' . $d['name'] . '” แล้ว');
        redirect('/admin/categories');
    }

    public function delete(string $id): void
    {
        $cat = DB::one('SELECT * FROM categories WHERE id = ?', [$this->intId($id)]);
        if (!$cat) {
            abort(404);
        }
        $n = (int) DB::value('SELECT COUNT(*) FROM costumes WHERE category_id = ?', [$cat['id']]);
        if ($n > 0) {
            flash('error', "ลบ “{$cat['name']}” ไม่ได้ เพราะยังมีชุดในประเภทนี้ $n ชุด ย้ายชุดไปประเภทอื่นก่อน หรือปิดการแสดงผลแทน");
            redirect('/admin/categories');
        }
        DB::run('DELETE FROM categories WHERE id = ?', [$cat['id']]);
        flash('success', 'ลบประเภท “' . $cat['name'] . '” แล้ว');
        redirect('/admin/categories');
    }
}
