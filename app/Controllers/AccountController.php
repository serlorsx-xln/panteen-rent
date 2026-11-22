<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Validator;
use App\Services\Addresses;
use App\Services\Appointments;
use App\Services\BookingException;
use App\Services\Catalog;
use App\Services\Notifier;
use App\Services\Tokens;
use App\Services\BookingService;

final class AccountController extends Controller
{
    public function profile(): void
    {
        $u = Auth::user();
        $this->view('account/profile', [
            'title' => 'บัญชีของฉัน',
            'u' => $u,
            'stats' => DB::one(
                "SELECT COUNT(*) AS total,
                        SUM(status IN " . BookingService::ACTIVE_SQL . ") AS active,
                        SUM(status = 'returned') AS done
                   FROM bookings WHERE user_id = ?",
                [$u['id']]
            ),
        ]);
    }

    public function updateProfile(): void
    {
        $u = Auth::user();
        $_POST['email'] = mb_strtolower(input_str('email'));
        $v = Validator::make($_POST, [
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'nickname' => 'nullable|max:50',
            'email' => 'required|email|max:190|unique:users,email,' . $u['id'],
            'phone' => 'required|phone',
            'line_id' => 'nullable|max:50',
        ], ['first_name' => 'ชื่อ', 'last_name' => 'นามสกุล', 'nickname' => 'ชื่อเล่น', 'email' => 'อีเมล', 'phone' => 'เบอร์โทร', 'line_id' => 'LINE ID']);
        if ($v->fails()) {
            back_with_errors($v->errors(), '/account');
        }
        $d = $v->data();
        $emailChanged = $d['email'] !== $u['email'];
        DB::update('users', [
            'first_name' => $d['first_name'], 'last_name' => $d['last_name'], 'nickname' => $d['nickname'],
            'email' => $d['email'], 'phone' => preg_replace('/\D/', '', $d['phone']), 'line_id' => $d['line_id'],
        ] + ($emailChanged ? ['email_verified_at' => null] : []), 'id = :id', ['id' => $u['id']]);
        Auth::refresh();
        if ($emailChanged) {
            Tokens::sendVerification(Auth::user());
            flash('warning', 'เปลี่ยนอีเมลแล้ว ยืนยันอีเมลใหม่จากลิงก์ที่ส่งไปที่ ' . $d['email'] . ' ก่อนจองครั้งต่อไป');
        } else {
            flash('success', 'บันทึกข้อมูลส่วนตัวแล้ว');
        }
        redirect('/account');
    }

    public function updateMeasurements(): void
    {
        $v = Validator::make($_POST, [
            'bust_in' => 'nullable|between:20,60',
            'waist_in' => 'nullable|between:18,60',
            'hip_in' => 'nullable|between:20,70',
            'height_cm' => 'nullable|between:120,210',
        ], ['bust_in' => 'รอบอก', 'waist_in' => 'รอบเอว', 'hip_in' => 'รอบสะโพก', 'height_cm' => 'ส่วนสูง']);
        if ($v->fails()) {
            back_with_errors($v->errors(), '/account#size');
        }
        $d = $v->data();
        DB::update('users', [
            'bust_in' => $d['bust_in'], 'waist_in' => $d['waist_in'], 'hip_in' => $d['hip_in'],
            'height_cm' => $d['height_cm'] !== null ? (int) $d['height_cm'] : null,
        ], 'id = :id', ['id' => Auth::id()]);
        flash('success', 'บันทึกสัดส่วนแล้ว หน้าชุดจะบอกว่าแต่ละชุดพอดีกับคุณไหม');
        $back = input_str('back');
        // only a path on this site: "//host" and "/\host" would both leave it
        redirect(preg_match('~^/(?![/\\\\])[^\\\\\s]*$~', $back) ? $back : '/account#size');
    }

    public function updatePassword(): void
    {
        $u = Auth::user();
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $u['password_hash'])) {
            back_with_errors(['current_password' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'], '/account#password');
        }
        $v = Validator::make($_POST, ['password' => 'required|password', 'password_confirmation' => 'required|same:password'], ['password' => 'รหัสผ่านใหม่']);
        if ($v->fails()) {
            back_with_errors($v->errors(), '/account#password');
        }
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        DB::update('users', ['password_hash' => $hash], 'id = :id', ['id' => $u['id']]);
        Auth::keepAfterPasswordChange($hash);
        flash('success', 'เปลี่ยนรหัสผ่านแล้ว');
        redirect('/account');
    }

    public function addresses(): void
    {
        $list = Addresses::forUser(Auth::id());
        $edit = input_int('edit') ? Addresses::find(Auth::id(), input_int('edit')) : null;
        $this->view('account/addresses', ['title' => 'ที่อยู่จัดส่ง', 'list' => $list, 'edit' => $edit]);
    }

    public function saveAddress(): void
    {
        $id = input_int('id') ?: null;
        [$saved, $errors] = Addresses::save(Auth::id(), Addresses::fromPost($_POST), $id, !empty($_POST['is_default']));
        if ($errors) {
            back_with_errors(Addresses::formErrors(array_diff_key($errors, ['form' => 1])) + array_intersect_key($errors, ['form' => 1]),
                '/account/addresses' . ($id ? '?edit=' . $id : '') . '#form');
        }
        flash('success', $id ? 'บันทึกที่อยู่แล้ว' : 'เพิ่มที่อยู่แล้ว');
        redirect('/account/addresses');
    }

    public function defaultAddress(string $id): void
    {
        if (!Addresses::makeDefault(Auth::id(), $this->intId($id))) {
            abort(404);
        }
        flash('success', 'ตั้งเป็นที่อยู่หลักแล้ว');
        redirect('/account/addresses');
    }

    public function deleteAddress(string $id): void
    {
        if (!Addresses::delete(Auth::id(), $this->intId($id))) {
            abort(404);
        }
        flash('success', 'ลบที่อยู่แล้ว การจองที่ใช้ที่อยู่นี้ยังส่งตามเดิม');
        redirect('/account/addresses');
    }

    public function favorites(): void
    {
        $date = \App\Core\Session::get('event_date');
        $busy = $date && $date >= today() ? \App\Services\Availability::busyExistsSql(...\App\Services\Pricing::defaultWindow($date)) : '0';
        $items = DB::all(
            "SELECT c.*, cat.name AS category_name, cat.color AS category_color, 1 AS fav, $busy AS busy,
                    (SELECT path FROM costume_images i WHERE i.costume_id = c.id ORDER BY sort_order, id LIMIT 1) AS image
               FROM favorites f JOIN costumes c ON c.id = f.costume_id JOIN categories cat ON cat.id = c.category_id
              WHERE f.user_id = ? AND c.status <> 'retired' ORDER BY f.created_at DESC",
            [Auth::id()]
        );
        $this->view('account/favorites', ['title' => 'ชุดที่ถูกใจ', 'items' => $items, 'filters' => ['date' => $date && $date >= today() ? $date : null]]);
    }

    public function toggleFavorite(string $code): void
    {
        $c = Catalog::byCode($code);
        if (!$c) {
            abort(404);
        }
        $exists = DB::value('SELECT 1 FROM favorites WHERE user_id = ? AND costume_id = ?', [Auth::id(), $c['id']]);
        if ($exists) {
            DB::run('DELETE FROM favorites WHERE user_id = ? AND costume_id = ?', [Auth::id(), $c['id']]);
        } else {
            DB::run('INSERT IGNORE INTO favorites (user_id, costume_id) VALUES (?, ?)', [Auth::id(), $c['id']]);
        }
        if (wants_json()) {
            json_response(['ok' => true, 'fav' => !$exists]);
        }
        flash('success', $exists ? 'นำออกจากชุดที่ถูกใจแล้ว' : 'เก็บไว้ในชุดที่ถูกใจแล้ว');
        back('/dress/' . $code);
    }

    public function notifications(): void
    {
        $items = Notifier::recent(Auth::id(), 60);
        Notifier::markAllRead(Auth::id());
        $this->view('account/notifications', ['title' => 'การแจ้งเตือน', 'items' => $items]);
    }

    public function readNotifications(): void
    {
        Notifier::markAllRead(Auth::id());
        if (wants_json()) {
            json_response(['ok' => true]);
        }
        back('/account/notifications');
    }

    public function appointments(): void
    {
        $items = DB::all(
            "SELECT a.*, b.booking_no, c.name AS costume_name FROM appointments a
               LEFT JOIN bookings b ON b.id = a.booking_id LEFT JOIN costumes c ON c.id = b.costume_id
              WHERE a.user_id = ? ORDER BY (a.starts_at >= NOW()) DESC, ABS(TIMESTAMPDIFF(MINUTE, NOW(), a.starts_at)) LIMIT 50",
            [Auth::id()]
        );
        $costume = isset($_GET['dress']) ? Catalog::byCode((string) $_GET['dress']) : null;
        $date = Catalog::filters(['date' => $_GET['date'] ?? add_days(today(), 1)])['date'] ?? add_days(today(), 1);
        $this->view('account/appointments', [
            'title' => 'นัดหมาย',
            'items' => $items,
            'costume' => $costume,
            'date' => $date,
            'slots' => Appointments::fittingSlots($date),
        ]);
    }

    public function slots(): void
    {
        $date = (string) ($_GET['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < today()) {
            json_response(['ok' => false, 'error' => 'เลือกวันที่ตั้งแต่วันนี้เป็นต้นไป']);
        }
        json_response(['ok' => true, 'slots' => Appointments::fittingSlots($date)]);
    }

    public function requestAppointment(): void
    {
        $costumeId = null;
        if ($code = input_str('dress')) {
            $c = Catalog::byCode($code);
            $costumeId = $c ? (int) $c['id'] : null;
        }
        try {
            Appointments::requestFitting(Auth::id(), input_str('date'), input_str('time'), input_str('note') ?: null, null, $costumeId);
            flash('success', 'นัดลองชุดเรียบร้อย ส่งรายละเอียดไปทางอีเมลแล้ว');
        } catch (BookingException $e) {
            back_with_errors(['time' => $e->getMessage()], '/account/appointments?date=' . urlencode(input_str('date')) . ($code ? '&dress=' . urlencode($code) : ''));
        }
        redirect('/account/appointments');
    }

    public function cancelAppointment(string $id): void
    {
        $a = DB::one('SELECT * FROM appointments WHERE id = ? AND user_id = ?', [$this->intId($id), Auth::id()]);
        if (!$a) {
            abort(404);
        }
        if ($a['type'] !== 'fitting' || $a['status'] !== 'scheduled') {
            flash('error', 'ยกเลิกเองได้เฉพาะนัดลองชุดที่ยังไม่ถึงเวลา นัดรับ/คืนชุดเปลี่ยนได้โดยติดต่อร้าน');
            redirect('/account/appointments');
        }
        Appointments::setStatus((int) $a['id'], 'cancelled');
        flash('success', 'ยกเลิกนัดแล้ว');
        redirect('/account/appointments');
    }
}
