<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Reports for any period [from, to] (inclusive dates).
 * - rentals:      bookings whose pickup date falls in the period
 * - finance:      money that actually moved in the period (approved payments by reviewed date)
 * - appointments: appointments whose time falls in the period
 */
final class Reports
{
    /** Bookings that became a rental (paid), whatever stage they are at now */
    private const RENTED = ['confirmed', 'shipped', 'picked_up', 'returning', 'returned'];

    /** @return array{string, string} [from, to] */
    public static function period(?string $from, ?string $to): array
    {
        $ok = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);
        $from = $ok($from) ? $from : date('Y-m-01');
        $to = $ok($to) ? $to : date('Y-m-t');
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        return [$from, $to];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, rented: int, revenue: float, days: int, avg: float|int, late: int,
     *               by_status: array<string, int>, by_category: list<array{name: mixed, color: mixed, count: int, revenue: float}>,
     *               by_costume: list<array{code: mixed, name: mixed, count: int, revenue: float}>, by_channel: array<string, int>,
     *               series: array<string, int>, live_statuses: string}
     */
    public static function rentals(string $from, string $to): array
    {
        $live = BookingService::RENTED_SQL;
        $rows = DB::all(
            "SELECT b.*, c.code AS costume_code, c.name AS costume_name, cat.name AS category_name, cat.color AS category_color,
                    u.first_name, u.last_name, u.nickname
               FROM bookings b JOIN costumes c ON c.id = b.costume_id JOIN categories cat ON cat.id = c.category_id JOIN users u ON u.id = b.user_id
              WHERE b.pickup_date BETWEEN ? AND ? ORDER BY b.pickup_date, b.id",
            [$from, $to]
        );
        $byStatus = array_fill_keys(array_keys(Status::BOOKING), 0);
        $byCategory = [];
        $byCostume = [];
        $byChannel = ['online' => 0, 'counter' => 0];
        $days = 0;
        $revenue = 0.0;
        foreach ($rows as $r) {
            $byStatus[$r['status']]++;
            if (!in_array($r['status'], self::RENTED, true)) {
                continue;
            }
            $amount = (float) $r['rent_amount'] + (float) $r['extra_amount'];
            $byCategory[$r['category_name']] ??= ['name' => $r['category_name'], 'color' => $r['category_color'], 'count' => 0, 'revenue' => 0.0];
            $byCategory[$r['category_name']]['count']++;
            $byCategory[$r['category_name']]['revenue'] += $amount;
            $key = $r['costume_code'];
            $byCostume[$key] ??= ['code' => $key, 'name' => $r['costume_name'], 'count' => 0, 'revenue' => 0.0];
            $byCostume[$key]['count']++;
            $byCostume[$key]['revenue'] += $amount;
            $byChannel[$r['channel']]++;
            $days += (int) $r['rental_days'];
            $revenue += $amount;
        }
        usort($byCategory, fn($a, $b) => $b['count'] <=> $a['count'] ?: $b['revenue'] <=> $a['revenue']);
        usort($byCostume, fn($a, $b) => $b['count'] <=> $a['count'] ?: $b['revenue'] <=> $a['revenue']);
        $rented = array_sum(array_intersect_key($byStatus, array_flip(self::RENTED)));
        $late = DB::value("SELECT COUNT(*) FROM bookings WHERE status = 'returned' AND late_days > 0 AND pickup_date BETWEEN ? AND ?", [$from, $to]);

        // Weekly buckets for the bar chart
        $series = [];
        foreach ($rows as $r) {
            if (in_array($r['status'], self::RENTED, true)) {
                $k = $r['pickup_date'];
                $series[$k] = ($series[$k] ?? 0) + 1;
            }
        }
        return [
            'rows' => $rows,
            'rented' => $rented,
            'revenue' => $revenue,
            'days' => $days,
            'avg' => $rented ? $revenue / $rented : 0,
            'late' => (int) $late,
            'by_status' => $byStatus,
            'by_category' => $byCategory,
            'by_costume' => array_slice($byCostume, 0, 10),
            'by_channel' => $byChannel,
            'series' => $series,
            'live_statuses' => $live,
        ];
    }

    /**
     * @return array{tx: list<array<string, mixed>>, received: float, received_booking: float, received_fine: float, refunded: float,
     *               net: float, rent: float, shipping: float, deposit_in: float, methods: array<string, float>, fines_late: float, fines_damage: float,
     *               settled_count: int, cancel_refunds: float, deposit_held_now: float, income: float}
     */
    public static function finance(string $from, string $to): array
    {
        $tx = DB::all(
            "SELECT p.*, b.booking_no, b.rent_amount, b.extra_amount, b.deposit_amount, b.delivery_fee, b.return_fee, u.first_name, u.last_name, u.nickname,
                    r.first_name AS reviewer_first, r.nickname AS reviewer_nick
               FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN users u ON u.id = b.user_id
               LEFT JOIN users r ON r.id = p.reviewed_by
              WHERE p.status = 'approved' AND DATE(p.reviewed_at) BETWEEN ? AND ?
              ORDER BY p.reviewed_at, p.id",
            [$from, $to]
        );
        $in = ['booking' => 0.0, 'fine' => 0.0];
        $refund = 0.0;
        $methods = ['promptpay' => 0.0, 'transfer' => 0.0, 'cash' => 0.0];
        $rent = 0.0;
        $shipping = 0.0;
        $deposit = 0.0;
        foreach ($tx as $t) {
            $amt = (float) $t['amount'];
            if ($t['kind'] === 'refund') {
                $refund += $amt;
                continue;
            }
            $in[$t['kind']] += $amt;
            $methods[$t['method']] += $amt;
            if ($t['kind'] === 'booking') {
                $rent += (float) $t['rent_amount'] + (float) $t['extra_amount'];
                $shipping += (float) $t['delivery_fee'] + (float) $t['return_fee'];
                $deposit += (float) $t['deposit_amount'];
            }
        }
        $settled = DB::one(
            "SELECT COUNT(*) AS n, COALESCE(SUM(late_fee),0) AS late, COALESCE(SUM(damage_fee - damage_waived),0) AS damage,
                    COALESCE(SUM(deposit_amount),0) AS deposits, COALESCE(SUM(deposit_refund),0) AS refunded
               FROM bookings WHERE status = 'returned' AND DATE(returned_at) BETWEEN ? AND ?",
            [$from, $to]
        );
        $cancelRefunds = (float) DB::value(
            "SELECT COALESCE(SUM(refund_amount),0) FROM bookings WHERE status = 'cancelled' AND DATE(cancelled_at) BETWEEN ? AND ?",
            [$from, $to]
        );
        $held = (float) DB::value(
            "SELECT COALESCE(SUM(b.deposit_amount),0) FROM bookings b WHERE b.status IN ('confirmed','shipped','picked_up','returning')"
        );
        $receivedTotal = $in['booking'] + $in['fine'];
        return [
            'tx' => $tx,
            'received' => $receivedTotal,
            'received_booking' => $in['booking'],
            'received_fine' => $in['fine'],
            'refunded' => $refund,
            'net' => $receivedTotal - $refund,
            'rent' => $rent,
            'shipping' => $shipping,
            'deposit_in' => $deposit,
            'methods' => $methods,
            'fines_late' => (float) $settled['late'],
            'fines_damage' => (float) $settled['damage'],
            'settled_count' => (int) $settled['n'],
            'cancel_refunds' => $cancelRefunds,
            'deposit_held_now' => $held,
            // Income the shop keeps: rent + delivery fees + fines retained (late + damage after any waiver)
            'income' => $rent + $shipping + (float) $settled['late'] + (float) $settled['damage'],
        ];
    }

    /** @return array{rows: list<array<string, mixed>>, matrix: array<string, array<string, int>>, total: int, no_show_rate: float|int|null} */
    public static function appointments(string $from, string $to): array
    {
        $rows = DB::all(
            "SELECT a.*, u.first_name, u.last_name, u.nickname, u.phone, b.booking_no, c.code AS costume_code, c.name AS costume_name
               FROM appointments a JOIN users u ON u.id = a.user_id
               LEFT JOIN bookings b ON b.id = a.booking_id LEFT JOIN costumes c ON c.id = b.costume_id
              WHERE DATE(a.starts_at) BETWEEN ? AND ? ORDER BY a.starts_at",
            [$from, $to]
        );
        $matrix = [];
        foreach (array_keys(Status::APPOINTMENT_TYPE) as $t) {
            $matrix[$t] = array_fill_keys(array_keys(Status::APPOINTMENT), 0);
        }
        foreach ($rows as $r) {
            $matrix[$r['type']][$r['status']]++;
        }
        $fit = $matrix['fitting'];
        $closed = $fit['done'] + $fit['no_show'];
        return [
            'rows' => $rows,
            'matrix' => $matrix,
            'total' => count($rows),
            'no_show_rate' => $closed ? $fit['no_show'] / $closed : null,
        ];
    }

    /** A cell that starts like a formula (= + - @, tab, CR) would run as one when the file opens in Excel; a leading ' keeps it text. Numbers stay numbers. */
    public static function csvCell(mixed $v): mixed
    {
        return is_string($v) && $v !== '' && str_contains("=+-@\t\r", $v[0]) && !is_numeric($v) ? "'" . $v : $v;
    }

    /**
     * Stream a CSV download (UTF-8 BOM so Excel shows Thai correctly).
     * @param list<string> $header
     * @param list<list<mixed>> $rows
     */
    public static function csv(string $filename, array $header, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header, ',', '"', '\\');
        foreach ($rows as $r) {
            fputcsv($out, array_map([self::class, 'csvCell'], $r), ',', '"', '\\');
        }
        fclose($out);
        exit;
    }
}
