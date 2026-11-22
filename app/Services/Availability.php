<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Availability rules.
 *
 * A booking occupies the costume from block_from to block_until:
 *   block_from  = the day the dress leaves the shop (pickup_date, or the parcel's ship-by day)
 *   block_until = return_date + days the return parcel travels + cleaning days.
 * Two ranges [f1,u1] and [f2,u2] conflict when f1 <= u2 AND f2 <= u1.
 * Blocking bookings: awaiting slip check, confirmed, out for delivery, with the customer (until it actually
 * comes back + cleaning), posted back, returned (cleaning after the actual return), and pending payment
 * while its hold has not expired.
 */
final class Availability
{
    private static function d(string $date): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException('Bad date');
        }
        return $date;
    }

    /** SQL condition (alias b) for bookings that hold the costume. */
    public static function blockingSql(string $alias = 'b'): string
    {
        $now = date('Y-m-d H:i:s');
        return "($alias.status IN ('awaiting_verification','confirmed','shipped','picked_up','returning','returned')
                 OR ($alias.status = 'pending_payment' AND ($alias.hold_expires_at IS NULL OR $alias.hold_expires_at > '$now')))";
    }

    /** SQL expression (alias b) for the last day a booking keeps the costume unavailable. */
    public static function endSql(string $alias = 'b'): string
    {
        $clean = Settings::int('cleaning_days');
        $today = today();
        // Still with the customer after the return day: it cannot be back before today + its return trip + cleaning
        // (block_until - return_date is exactly that tail). Posted back: it can arrive any day, then needs cleaning.
        return "(CASE $alias.status
                   WHEN 'picked_up' THEN GREATEST($alias.block_until, DATE_ADD('$today', INTERVAL DATEDIFF($alias.block_until, $alias.return_date) DAY))
                   WHEN 'returning' THEN GREATEST($alias.block_until, DATE_ADD('$today', INTERVAL $clean DAY))
                   WHEN 'returned'  THEN DATE_ADD(DATE($alias.returned_at), INTERVAL $clean DAY)
                   ELSE $alias.block_until END)";
    }

    /** Last busy day for a return on $returnDate whose parcel takes $backDays to come back. */
    public static function blockUntil(string $returnDate, int $backDays = 0): string
    {
        return add_days($returnDate, $backDays + Settings::int('cleaning_days'));
    }

    /**
     * Bookings that would clash with renting $costumeId from $pickup to $return, when the dress has to leave
     * $outDays before pickup (parcel delivery) and takes $backDays to come back (posted return).
     * @return list<array<string, mixed>>
     */
    public static function conflicts(int $costumeId, string $pickup, string $return, ?int $ignoreBookingId = null, int $outDays = 0, int $backDays = 0): array
    {
        $p = self::d(add_days($pickup, -$outDays));
        $b = self::d(self::blockUntil($return, $backDays));
        $sql = 'SELECT b.id, b.booking_no, b.block_from, b.pickup_date, b.return_date, b.status, ' . self::endSql() . ' AS busy_until
                  FROM bookings b
                 WHERE b.costume_id = ? AND ' . self::blockingSql() . "
                   AND b.block_from <= '$b' AND " . self::endSql() . " >= '$p'";
        $params = [$costumeId];
        if ($ignoreBookingId) {
            $sql .= ' AND b.id <> ?';
            $params[] = $ignoreBookingId;
        }
        return DB::all($sql . ' ORDER BY b.block_from', $params);
    }

    public static function isFree(int $costumeId, string $pickup, string $return, ?int $ignoreBookingId = null, int $outDays = 0, int $backDays = 0): bool
    {
        return self::conflicts($costumeId, $pickup, $return, $ignoreBookingId, $outDays, $backDays) === [];
    }

    /**
     * SQL boolean expression (alias c = costumes) that is 1 when the costume is busy in [$pickup,$return].
     */
    public static function busyExistsSql(string $pickup, string $return): string
    {
        $p = self::d($pickup);
        $b = self::d(self::blockUntil($return));
        return "(c.status <> 'active' OR EXISTS (SELECT 1 FROM bookings b WHERE b.costume_id = c.id AND " . self::blockingSql()
            . " AND b.block_from <= '$b' AND " . self::endSql() . " >= '$p'))";
    }

    /**
     * Day-by-day state for a month calendar.
     * @return array<string,string> date => free|busy|past|off
     */
    public static function month(int $costumeId, int $year, int $month): array
    {
        $first = sprintf('%04d-%02d-01', $year, $month);
        $last = date('Y-m-t', strtotime($first));
        $costume = DB::one('SELECT status FROM costumes WHERE id = ?', [$costumeId]);
        // A day is "free" only when the whole default rental window around it is free
        // (pick up the day before, return the day after, plus cleaning) — the same rule
        // the dress page verdict and the "free on this date" filter use.
        $from = add_days($first, -1);
        $to = self::blockUntil(add_days($last, 1));
        $ranges = DB::all(
            'SELECT b.block_from AS s, ' . self::endSql() . ' AS e FROM bookings b
              WHERE b.costume_id = ? AND ' . self::blockingSql() . " AND b.block_from <= '$to' AND " . self::endSql() . " >= '$from'",
            [$costumeId]
        );
        $today = today();
        $horizon = add_days($today, Settings::int('booking_horizon_days'));
        $days = [];
        for ($d = $first; $d <= $last; $d = add_days($d, 1)) {
            if ($d < $today) {
                $state = 'past';
            } elseif (!$costume || $costume['status'] !== 'active' || $d > $horizon) {
                $state = 'off';
            } else {
                $state = 'free';
                [$wp, $wr] = Pricing::defaultWindow($d);
                $wb = self::blockUntil($wr);
                foreach ($ranges as $r) {
                    if ($r['s'] <= $wb && $r['e'] >= $wp) {
                        $state = 'busy';
                        break;
                    }
                }
            }
            $days[$d] = $state;
        }
        return $days;
    }

    /**
     * Busy ranges from today on (for the dress page "ช่วงที่ไม่ว่าง" list).
     * @return list<array<string, mixed>> rows of s (start) and e (end)
     */
    public static function upcomingBusy(int $costumeId, int $limit = 6): array
    {
        $today = today();
        return DB::all(
            'SELECT b.block_from AS s, ' . self::endSql() . ' AS e FROM bookings b
              WHERE b.costume_id = ? AND ' . self::blockingSql() . ' AND ' . self::endSql() . " >= '$today'
              ORDER BY b.block_from LIMIT " . (int) $limit,
            [$costumeId]
        );
    }

    /**
     * Derived inventory state for the back office.
     * @param array<string, mixed> $costume
     * @return array{state:string, booking?:array<string, mixed>}
     */
    public static function inventoryState(array $costume): array
    {
        if ($costume['status'] !== 'active') {
            return ['state' => $costume['status']];
        }
        $id = (int) $costume['id'];
        $onRent = DB::one("SELECT * FROM bookings WHERE costume_id = ? AND status = 'picked_up' ORDER BY pickup_date LIMIT 1", [$id]);
        if ($onRent) {
            return ['state' => 'on_rent', 'booking' => $onRent];
        }
        $moving = DB::one("SELECT * FROM bookings WHERE costume_id = ? AND status IN ('shipped','returning') ORDER BY pickup_date LIMIT 1", [$id]);
        if ($moving) {
            return ['state' => 'in_transit', 'booking' => $moving];
        }
        $clean = Settings::int('cleaning_days');
        $today = today();
        $cleaning = DB::one(
            "SELECT * FROM bookings WHERE costume_id = ? AND status = 'returned'
               AND DATE_ADD(DATE(returned_at), INTERVAL $clean DAY) >= '$today' ORDER BY returned_at DESC LIMIT 1",
            [$id]
        );
        if ($cleaning) {
            return ['state' => 'cleaning', 'booking' => $cleaning];
        }
        $booked = DB::one(
            'SELECT * FROM bookings b WHERE b.costume_id = ? AND ' . self::blockingSql() . " AND b.status <> 'returned'
               AND b.block_from <= '$today' AND b.block_until >= '$today' LIMIT 1",
            [$id]
        );
        if ($booked) {
            return ['state' => 'booked', 'booking' => $booked];
        }
        return ['state' => 'available'];
    }
}
