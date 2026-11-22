<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Services\BookingException;
use App\Services\BookingService;

final class PaymentsController extends Controller
{
    public function index(): void
    {
        $tab = in_array($_GET['tab'] ?? '', ['pending', 'done'], true) ? $_GET['tab'] : 'pending';
        $where = $tab === 'pending' ? "p.status = 'pending'" : "p.status <> 'pending' AND p.slip_path IS NOT NULL";
        $items = DB::all(
            "SELECT p.*, b.booking_no, b.total_amount, b.event_date, b.pickup_date, b.status AS booking_status,
                    c.code AS costume_code, c.name AS costume_name, u.first_name, u.last_name, u.nickname, u.phone,
                    r.first_name AS reviewer_first, r.nickname AS reviewer_nick
               FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN costumes c ON c.id = b.costume_id
               JOIN users u ON u.id = b.user_id LEFT JOIN users r ON r.id = p.reviewed_by
              WHERE $where ORDER BY " . ($tab === 'pending' ? 'p.created_at ASC' : 'p.reviewed_at DESC') . ' LIMIT 100'
        );
        $this->admin('payments', ['title' => 'ตรวจสลิป', 'items' => $items, 'tab' => $tab]);
    }

    public function approve(string $id): void
    {
        try {
            $b = BookingService::approvePayment($this->intId($id), Auth::id());
            flash('success', 'อนุมัติสลิป ' . $b['booking_no'] . ' แล้ว ออกใบเสร็จและแจ้งลูกค้าเรียบร้อย');
        } catch (BookingException $e) {
            flash('error', $e->getMessage());
        }
        back('/admin/payments');
    }

    public function reject(string $id): void
    {
        $reason = input_str('reason') ?: input_str('reason_preset');
        try {
            $b = BookingService::rejectPayment($this->intId($id), Auth::id(), $reason);
            flash('success', 'แจ้งลูกค้าว่าสลิป ' . $b['booking_no'] . ' ไม่ผ่านแล้ว');
        } catch (BookingException $e) {
            flash('error', $e->getMessage());
        }
        back('/admin/payments');
    }
}
