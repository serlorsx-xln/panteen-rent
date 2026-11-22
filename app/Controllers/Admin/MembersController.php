<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Validator;
use App\Services\Notifier;
use App\Services\Status;
use App\Services\Tokens;
use App\Services\BookingService;
use App\Services\Shipping;

final class MembersController extends Controller
{
    public function index(): void
    {
        $q = input_str('q');
        $role = $_GET['role'] ?? 'member';
        $role = isset(Status::ROLE[$role]) || $role === 'team' ? $role : 'member';
        $status = $_GET['status'] ?? '';
        $where = [$role === 'team' ? "u.role IN ('admin','staff')" : 'u.role = ?'];
        $params = $role === 'team' ? [] : [$role];
        if ($q !== '') {
            $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.nickname LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.line_id LIKE ?)';
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        if (isset(Status::USER[$status])) {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }
        $base = ' FROM users u WHERE ' . implode(' AND ', $where);
        $pg = $this->paginate('SELECT COUNT(*)' . $base, $params, 30);
        $items = DB::all(
            "SELECT u.*, (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) AS bookings,
                    (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id AND b.status IN " . BookingService::ACTIVE_SQL . ") AS active_bookings,
                    (SELECT MAX(b.created_at) FROM bookings b WHERE b.user_id = u.id) AS last_booking"
            . $base . " ORDER BY u.created_at DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}",
            $params
        );
        $this->admin('members/index', ['title' => $role === 'team' ? 'ทีมงาน' : 'สมาชิก', 'items' => $items, 'pg' => $pg, 'q' => $q, 'role' => $role, 'status' => $status]);
    }

    /** JSON lookup for the counter-booking form. */
    public function lookup(): void
    {
        $q = input_str('q');
        if (mb_strlen($q) < 2) {
            json_response(['ok' => true, 'items' => []]);
        }
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $rows = DB::all(
            "SELECT id, first_name, last_name, nickname, phone, email, status FROM users
              WHERE role = 'member' AND (first_name LIKE ? OR last_name LIKE ? OR nickname LIKE ? OR phone LIKE ? OR email LIKE ?)
              ORDER BY status = 'active' DESC, first_name LIMIT 8",
            [$like, $like, $like, $like, $like]
        );
        // the counter booking form offers the picked customer's saved delivery addresses
        $book = [];
        if ($rows) {
            $ids = array_map('intval', array_column($rows, 'id'));
            foreach (DB::all('SELECT * FROM addresses WHERE user_id IN (' . implode(',', $ids) . ') ORDER BY is_default DESC, id') as $a) {
                $book[(int) $a['user_id']][] = [
                    'id' => (int) $a['id'], 'province' => $a['province'],
                    'title' => ($a['label'] ? $a['label'] . ' · ' : '') . $a['recipient'] . ' · ' . phone_fmt($a['phone']),
                    'text' => str_replace("\n", ' · ', Shipping::addressText($a)),
                    'zone' => Shipping::ZONE[Shipping::zone($a['province']) ?? 'upcountry'],
                ];
            }
        }
        json_response(['ok' => true, 'items' => array_map(fn($u) => [
            'id' => (int) $u['id'],
            'name' => full_name($u) . ($u['nickname'] ? ' (' . $u['nickname'] . ')' : ''),
            'meta' => $u['phone'] . ' · ' . $u['email'],
            'suspended' => $u['status'] !== 'active',
            'addresses' => $book[(int) $u['id']] ?? [],
        ], $rows)]);
    }

    public function create(): void
    {
        $this->admin('members/create', ['title' => 'เพิ่มลูกค้าหน้าร้าน', 'back' => input_str('back')]);
    }

    /** Staff registers a walk-in customer. A random password is set; the customer can use "forgot password" later. */
    public function store(): void
    {
        $_POST['email'] = mb_strtolower(input_str('email'));
        $v = Validator::make($_POST, [
            'first_name' => 'required|max:100', 'last_name' => 'required|max:100', 'nickname' => 'nullable|max:50',
            'email' => 'required|email|max:190|unique:users,email', 'phone' => 'required|phone', 'line_id' => 'nullable|max:50',
        ], ['first_name' => 'ชื่อ', 'last_name' => 'นามสกุล', 'nickname' => 'ชื่อเล่น', 'email' => 'อีเมล', 'phone' => 'เบอร์โทร', 'line_id' => 'LINE ID']);
        if ($v->fails()) {
            back_with_errors($v->errors(), '/admin/members/new' . (input_str('back') === 'booking' ? '?back=booking' : ''));
        }
        $d = $v->data();
        $id = DB::insert('users', [
            'role' => 'member', 'first_name' => $d['first_name'], 'last_name' => $d['last_name'], 'nickname' => $d['nickname'],
            'email' => $d['email'], 'phone' => preg_replace('/\D/', '', $d['phone']), 'line_id' => $d['line_id'],
            'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
        $user = DB::one('SELECT * FROM users WHERE id = ?', [$id]);
        Tokens::sendReset($user);
        flash('success', 'เพิ่มลูกค้า ' . full_name($user) . ' แล้ว ส่งลิงก์ตั้งรหัสผ่านไปที่อีเมลลูกค้า');
        $back = input_str('back');
        redirect($back === 'booking' ? '/admin/bookings/new?member=' . $id : '/admin/members/' . $id);
    }

    public function show(string $id): void
    {
        $u = DB::one('SELECT * FROM users WHERE id = ?', [$this->intId($id)]);
        if (!$u) {
            abort(404);
        }
        $this->admin('members/show', [
            'title' => full_name($u),
            'u' => $u,
            'bookings' => DB::all(
                'SELECT b.*, c.code AS costume_code, c.name AS costume_name FROM bookings b JOIN costumes c ON c.id = b.costume_id
                  WHERE b.user_id = ? ORDER BY b.pickup_date DESC LIMIT 50',
                [$u['id']]
            ),
            'stats' => DB::one(
                "SELECT COUNT(*) AS total, SUM(status = 'returned') AS done, SUM(status = 'cancelled') AS cancelled,
                        SUM(late_days > 0) AS late, COALESCE(SUM(CASE WHEN status IN " . BookingService::RENTED_SQL . " THEN rent_amount + extra_amount END), 0) AS spent
                   FROM bookings WHERE user_id = ?",
                [$u['id']]
            ),
        ]);
    }

    public function update(string $id): void
    {
        $uid = $this->intId($id);
        $u = DB::one('SELECT * FROM users WHERE id = ?', [$uid]);
        if (!$u) {
            abort(404);
        }
        if ($u['role'] !== 'member' && !Auth::isAdmin()) {
            abort(403);
        }
        // the e-mail signs in and receives password resets: staff who could change it could take the account over
        $_POST['email'] = Auth::isAdmin() ? mb_strtolower(input_str('email')) : $u['email'];
        $v = Validator::make($_POST, [
            'first_name' => 'required|max:100', 'last_name' => 'required|max:100', 'nickname' => 'nullable|max:50',
            'email' => 'required|email|max:190|unique:users,email,' . $uid, 'phone' => 'required|phone', 'line_id' => 'nullable|max:50',
            'bust_in' => 'nullable|between:20,60', 'waist_in' => 'nullable|between:18,60', 'hip_in' => 'nullable|between:20,70',
        ], ['first_name' => 'ชื่อ', 'last_name' => 'นามสกุล', 'nickname' => 'ชื่อเล่น', 'email' => 'อีเมล', 'phone' => 'เบอร์โทร', 'line_id' => 'LINE ID',
            'bust_in' => 'รอบอก', 'waist_in' => 'รอบเอว', 'hip_in' => 'รอบสะโพก']);
        if ($v->fails()) {
            back_with_errors($v->errors(), '/admin/members/' . $uid);
        }
        $d = $v->data();
        $d['phone'] = preg_replace('/\D/', '', $d['phone']);
        DB::update('users', $d, 'id = :id', ['id' => $uid]);
        flash('success', 'บันทึกข้อมูลแล้ว');
        redirect('/admin/members/' . $uid);
    }

    public function status(string $id): void
    {
        $uid = $this->intId($id);
        $u = DB::one('SELECT * FROM users WHERE id = ?', [$uid]);
        if (!$u) {
            abort(404);
        }
        if ($uid === Auth::id()) {
            flash('error', 'ระงับบัญชีของตัวเองไม่ได้');
            redirect('/admin/members/' . $uid);
        }
        $status = input_str('status') === 'suspended' ? 'suspended' : 'active';
        $reason = input_str('reason');
        if ($status === 'suspended' && $reason === '') {
            back_with_errors(['reason' => 'ระบุเหตุผลที่ระงับบัญชี'], '/admin/members/' . $uid . '#status');
        }
        DB::update('users', ['status' => $status, 'suspended_reason' => $status === 'suspended' ? mb_substr($reason, 0, 255) : null], 'id = :id', ['id' => $uid]);
        if ($status === 'active') {
            Notifier::user($uid, 'account', 'บัญชีของคุณกลับมาใช้งานได้แล้ว', null, '/');
        }
        flash('success', $status === 'suspended' ? 'ระงับบัญชีแล้ว ผู้ใช้จะถูกออกจากระบบทันที' : 'เปิดใช้งานบัญชีแล้ว');
        redirect('/admin/members/' . $uid);
    }

    public function role(string $id): void
    {
        $uid = $this->intId($id);
        $role = input_str('role');
        if (!isset(Status::ROLE[$role]) || $uid === Auth::id()) {
            flash('error', 'เปลี่ยนบทบาทนี้ไม่ได้');
            redirect('/admin/members/' . $uid);
        }
        if ($role !== 'admin') {
            $admins = (int) DB::value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id <> ?", [$uid]);
            if ($admins < 1) {
                flash('error', 'ต้องมีผู้ดูแลระบบอย่างน้อย 1 คน');
                redirect('/admin/members/' . $uid);
            }
        }
        DB::update('users', ['role' => $role], 'id = :id', ['id' => $uid]);
        flash('success', 'เปลี่ยนบทบาทเป็น' . Status::ROLE[$role] . 'แล้ว');
        redirect('/admin/members/' . $uid);
    }
}
