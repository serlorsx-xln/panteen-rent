<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Services\Notifier;
use App\Services\BookingService;

final class DashboardController extends Controller
{
    private const LIST = "SELECT b.*, c.code AS costume_code, c.name AS costume_name, c.size_label, c.late_fee_per_day,
                                 (SELECT path FROM costume_images i WHERE i.costume_id = c.id ORDER BY sort_order, id LIMIT 1) AS image,
                                 u.first_name, u.last_name, u.nickname, u.phone
                            FROM bookings b JOIN costumes c ON c.id = b.costume_id JOIN users u ON u.id = b.user_id ";

    public function index(): void
    {
        $today = today();
        $this->admin('dashboard', [
            'title' => 'วันนี้',
            'pickups' => DB::all(self::LIST . "WHERE b.status = 'confirmed' AND b.delivery_method = 'pickup' AND b.pickup_date <= ? ORDER BY b.pickup_date, b.pickup_time", [$today]),
            // parcels to send today (or late), and return parcels that reached the shop and wait for inspection
            'parcels' => DB::all(
                self::LIST . "WHERE (b.status = 'confirmed' AND b.delivery_method <> 'pickup' AND b.ship_by_date <= ?)
                    OR (b.status = 'returning' AND EXISTS (SELECT 1 FROM shipments s WHERE s.booking_id = b.id AND s.direction = 'back' AND s.status IN ('received','lost')))
                  ORDER BY b.status = 'returning', b.ship_by_date, b.id",
                [$today]
            ),
            'returns' => DB::all(self::LIST . "WHERE b.status = 'picked_up' AND b.return_date <= ? ORDER BY b.return_date, b.return_time", [$today]),
            'slips' => (int) DB::value("SELECT COUNT(*) FROM payments WHERE status = 'pending'"),
            'holds' => DB::all(self::LIST . "WHERE b.status = 'pending_payment' AND b.hold_expires_at > NOW() ORDER BY b.hold_expires_at LIMIT 8"),
            'fittings' => DB::all(
                "SELECT a.*, u.first_name, u.last_name, u.nickname, u.phone FROM appointments a JOIN users u ON u.id = a.user_id
                  WHERE a.type = 'fitting' AND DATE(a.starts_at) = ? ORDER BY a.starts_at",
                [$today]
            ),
            'week' => DB::all(
                "SELECT d, SUM(kind = 'pickup') AS pickups, SUM(kind = 'return') AS returns FROM (
                    SELECT COALESCE(ship_by_date, pickup_date) AS d, 'pickup' AS kind FROM bookings WHERE status = 'confirmed' AND COALESCE(ship_by_date, pickup_date) BETWEEN ? AND ?
                    UNION ALL
                    SELECT return_date AS d, 'return' AS kind FROM bookings WHERE status IN ('confirmed','shipped','picked_up') AND return_date BETWEEN ? AND ?
                 ) x GROUP BY d ORDER BY d",
                [add_days($today, 1), add_days($today, 7), add_days($today, 1), add_days($today, 7)]
            ),
            'inventory' => DB::one(
                "SELECT COUNT(*) AS total,
                        SUM(status = 'active') AS active,
                        SUM(status = 'maintenance') AS maintenance,
                        (SELECT COUNT(DISTINCT costume_id) FROM bookings WHERE status IN " . BookingService::OUT_SQL . ") AS on_rent
                   FROM costumes WHERE status <> 'retired'"
            ),
            'month' => DB::one(
                "SELECT COUNT(*) AS bookings, COALESCE(SUM(rent_amount + extra_amount), 0) AS revenue
                   FROM bookings WHERE status IN " . BookingService::RENTED_SQL . " AND DATE_FORMAT(confirmed_at, '%Y-%m') = ?",
                [date('Y-m')]
            ),
        ]);
    }

    public function notifications(): void
    {
        $items = Notifier::recent(Auth::id(), 80);
        Notifier::markAllRead(Auth::id());
        $this->admin('notifications', ['title' => 'การแจ้งเตือน', 'items' => $items]);
    }
}
