<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Services\Appointments;
use App\Services\BookingException;
use App\Services\Status;

final class AppointmentsController extends Controller
{
    public function index(): void
    {
        $date = input_str('date');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = today();
        }
        $items = DB::all(
            "SELECT a.*, u.first_name, u.last_name, u.nickname, u.phone, b.booking_no, c.code AS costume_code, c.name AS costume_name
               FROM appointments a JOIN users u ON u.id = a.user_id
               LEFT JOIN bookings b ON b.id = a.booking_id LEFT JOIN costumes c ON c.id = b.costume_id
              WHERE DATE(a.starts_at) = ? ORDER BY a.starts_at, a.type",
            [$date]
        );
        $week = DB::all(
            "SELECT DATE(starts_at) AS d, COUNT(*) AS n FROM appointments WHERE status = 'scheduled' AND DATE(starts_at) BETWEEN ? AND ? GROUP BY d",
            [add_days($date, -3), add_days($date, 10)]
        );
        $this->admin('appointments', [
            'title' => 'นัดหมาย',
            'date' => $date,
            'items' => $items,
            'week' => array_column($week, 'n', 'd'),
            'slots' => Appointments::fittingSlots($date),
            'member' => isset($_GET['member']) ? DB::one("SELECT * FROM users WHERE id = ? AND role = 'member'", [(int) $_GET['member']]) : null,
        ]);
    }

    public function store(): void
    {
        $uid = input_int('user_id');
        $date = input_str('date');
        if (!DB::value("SELECT 1 FROM users WHERE id = ? AND role = 'member' AND status = 'active'", [$uid])) {
            back_with_errors(['user_id' => 'เลือกลูกค้า'], '/admin/appointments?date=' . urlencode($date));
        }
        try {
            Appointments::requestFitting($uid, $date, input_str('time'), input_str('note') ?: null, Auth::id());
            flash('success', 'บันทึกนัดลองชุดแล้ว ส่งอีเมลยืนยันให้ลูกค้าเรียบร้อย');
        } catch (BookingException $e) {
            back_with_errors(['time' => $e->getMessage()], '/admin/appointments?date=' . urlencode($date));
        }
        redirect('/admin/appointments?date=' . urlencode($date));
    }

    public function status(string $id): void
    {
        $a = DB::one('SELECT * FROM appointments WHERE id = ?', [$this->intId($id)]);
        if (!$a) {
            abort(404);
        }
        $status = input_str('status');
        if (!isset(Status::APPOINTMENT[$status])) {
            abort(400);
        }
        Appointments::setStatus((int) $a['id'], $status);
        flash('success', 'อัปเดตนัดเป็น “' . Status::label(Status::APPOINTMENT, $status) . '” แล้ว');
        redirect('/admin/appointments?date=' . substr($a['starts_at'], 0, 10));
    }
}
