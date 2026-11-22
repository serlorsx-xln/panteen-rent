<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Scheduled jobs: expire unpaid holds and send reminders.
 * Run by cron (cli/cron.php) and also lazily from web requests at most every 10 minutes.
 * Every reminder carries a dedupe key, so running twice never sends twice.
 */
final class Reminders
{
    public static function lazyRun(): void
    {
        $file = STORAGE_PATH . '/cache/last-cron.txt';
        $last = is_file($file) ? (int) file_get_contents($file) : 0;
        if (time() - $last < 600) {
            return;
        }
        @file_put_contents($file, (string) time());
        try {
            self::run();
        } catch (\Throwable $e) {
            error_log('[reminders] ' . $e->getMessage());
        }
    }

    /** @return array<string,int> counts per job */
    public static function run(?string $today = null): array
    {
        $today ??= today();
        $ahead = add_days($today, Settings::int('remind_before_days'));
        $out = ['expired' => BookingService::expireHolds(), 'pickup' => 0, 'return' => 0, 'overdue' => 0, 'hold' => 0, 'fitting' => 0, 'parcel' => 0];

        foreach (DB::all("SELECT id FROM bookings WHERE status = 'confirmed' AND delivery_method = 'pickup' AND pickup_date BETWEEN ? AND ?", [$today, $ahead]) as $r) {
            $b = BookingService::find((int) $r['id']);
            $when = relative_day($b['pickup_date']);
            if (Notifier::user((int) $b['user_id'], 'remind_pickup', "{$when}รับชุด {$b['costume_name']}", 'เวลา ' . substr((string) $b['pickup_time'], 0, 5) . ' น. ที่ร้าน · เลขที่ ' . $b['booking_no'],
                '/bookings/' . $b['id'], 'pickup:' . $b['id'] . ':' . $b['pickup_date'], [
                    'subject' => "เตือน: {$when}รับชุด " . $b['booking_no'],
                    'data' => BookingService::mailData($b, "{$when}มารับชุดที่ร้านนะ", ['รับชุดวันที่ ' . thai_date_full($b['pickup_date']) . ' เวลา ' . substr((string) $b['pickup_time'], 0, 5) . ' น.', 'นำบัตรประชาชนมาด้วยเพื่อยืนยันตัวตน'],
                        ['ดูการจอง', absolute_url('/bookings/' . $b['id'])]),
                ])) {
                $out['pickup']++;
            }
        }

        foreach (DB::all("SELECT id FROM bookings WHERE status = 'picked_up' AND return_date BETWEEN ? AND ?", [$today, $ahead]) as $r) {
            $b = BookingService::find((int) $r['id']);
            $when = relative_day($b['return_date']);
            $post = $b['return_method'] === 'post';
            $how = $post
                ? ['ฝากส่งพัสดุคืนภายใน ' . thai_date_full($b['return_date']) . ' ด้วยกล่องและใบปะหน้าส่งคืนที่แนบไป', 'ฝากส่งแล้วกรอกเลขพัสดุและแนบรูปใบรับฝากในหน้าการจอง ร้านนับวันคืนจากวันที่ฝากส่ง']
                : ['คืนชุดที่ร้านภายใน ' . thai_date_full($b['return_date']) . ' เวลา ' . substr((string) $b['return_time'], 0, 5) . ' น.'];
            $how[] = 'คืนช้าคิดค่าปรับวันละ ' . money($b['late_fee_per_day']) . ' หักจากเงินมัดจำ';
            if (Notifier::user((int) $b['user_id'], 'remind_return', "{$when}ครบกำหนด" . ($post ? 'ส่งชุดคืน' : 'คืนชุด'),
                ($post ? 'ฝากส่งพัสดุคืนภายในวันนี้' : 'คืนภายใน ' . substr((string) $b['return_time'], 0, 5) . ' น.') . ' คืนช้าคิดวันละ ' . money($b['late_fee_per_day']),
                '/bookings/' . $b['id'], 'return:' . $b['id'] . ':' . $b['return_date'] . ':' . $today, [
                    'subject' => "เตือน: {$when}ครบกำหนดคืนชุด " . $b['booking_no'],
                    'data' => BookingService::mailData($b, "{$when}ครบกำหนดคืนชุด", $how,
                        ['ดูการจอง', absolute_url('/bookings/' . $b['id'])]),
                ])) {
                $out['return']++;
            }
        }

        foreach (DB::all("SELECT id FROM bookings WHERE status = 'picked_up' AND return_date < ?", [$today]) as $r) {
            $b = BookingService::find((int) $r['id']);
            $late = days_between($b['return_date'], $today);
            $fee = $late * (float) $b['late_fee_per_day'];
            if (Notifier::user((int) $b['user_id'], 'overdue', "เลยกำหนดคืนชุด $late วัน", 'ค่าปรับสะสม ' . money($fee) . ($b['return_method'] === 'post' ? ' ฝากส่งพัสดุคืนแล้วแจ้งเลขพัสดุโดยเร็ว' : ' คืนชุดโดยเร็วที่สุด'),
                '/bookings/' . $b['id'], 'overdue:' . $b['id'] . ':' . $today, [
                    'subject' => "เลยกำหนดคืนชุด $late วัน · " . $b['booking_no'],
                    'data' => BookingService::mailData($b, "เลยกำหนดคืนชุดมาแล้ว $late วัน", ['ค่าปรับสะสมถึงวันนี้ ' . money($fee) . ' (วันละ ' . money($b['late_fee_per_day']) . ')', $b['return_method'] === 'post' ? 'กรุณาฝากส่งพัสดุคืนโดยเร็ว แล้วกรอกเลขพัสดุในหน้าการจอง ค่าปรับนับถึงวันที่ฝากส่งและหักจากเงินมัดจำ' : 'กรุณานำชุดมาคืนโดยเร็ว ค่าปรับจะหักจากเงินมัดจำ'],
                        ['ดูการจอง', absolute_url('/bookings/' . $b['id'])]),
                ])) {
                $out['overdue']++;
                Notifier::staff('overdue', 'ค้างคืน ' . $b['booking_no'] . " ($late วัน)", full_name($b) . ' · ' . $b['phone'], '/admin/bookings/' . $b['id'], 'overdue:' . $b['id'] . ':' . $today);
            }
        }

        // Parcels: dispatch due or late, deliveries the customer has not confirmed, return parcels slow in transit.
        // Staff only, once a day each; the shipping queue shows the same work.
        foreach (DB::all("SELECT id FROM bookings WHERE status = 'confirmed' AND delivery_method <> 'pickup' AND ship_by_date <= ?", [$today]) as $r) {
            $b = BookingService::find((int) $r['id']);
            $late = $b['ship_by_date'] < $today;
            if (Notifier::staff('ship_due', ($late ? 'ส่งพัสดุช้า ' : 'ต้องส่งพัสดุวันนี้ ') . $b['booking_no'],
                $b['costume_code'] . ' · ถึงลูกค้าภายใน ' . thai_date($b['pickup_date'], false, false) . ' · ' . ($b['ship_province'] ?? ''),
                '/admin/bookings/' . $b['id'], 'ship:' . $b['id'] . ':' . $today)) {
                $out['parcel']++;
            }
        }
        foreach (DB::all("SELECT id FROM bookings WHERE status = 'shipped' AND pickup_date < ?", [$today]) as $r) {
            $b = BookingService::find((int) $r['id']);
            if (Notifier::staff('ship_unconfirmed', 'ยังไม่ยืนยันว่าพัสดุถึง ' . $b['booking_no'], 'เลยวันที่ควรถึงแล้ว ตรวจสถานะกับขนส่งแล้วกด “ถึงลูกค้าแล้ว” หรือ “ส่งไม่สำเร็จ”',
                '/admin/bookings/' . $b['id'], 'unconfirmed:' . $b['id'] . ':' . $today)) {
                $out['parcel']++;
            }
        }
        foreach (DB::all(
            "SELECT b.id, b.ship_zone, s.shipped_on FROM bookings b JOIN shipments s ON s.booking_id = b.id AND s.direction = 'back' AND s.status = 'in_transit'
              WHERE b.status = 'returning'"
        ) as $r) {
            $limit = add_days((string) $r['shipped_on'], Shipping::transitDays('post', $r['ship_zone']) + 2);
            if ($limit >= $today) {
                continue;
            }
            $b = BookingService::find((int) $r['id']);
            if (Notifier::staff('return_slow', 'พัสดุคืนยังไม่ถึงร้าน ' . $b['booking_no'], 'ฝากส่ง ' . thai_date((string) $r['shipped_on'], false, false) . ' ติดตามกับขนส่ง ถ้าหายให้กด “พัสดุคืนหาย”',
                '/admin/bookings/' . $b['id'], 'slow:' . $b['id'] . ':' . $today)) {
                $out['parcel']++;
            }
        }

        // Holds that run out within 3 hours.
        $soon = date('Y-m-d H:i:s', time() + 3 * 3600);
        foreach (DB::all("SELECT id FROM bookings WHERE status = 'pending_payment' AND hold_expires_at BETWEEN NOW() AND ?", [$soon]) as $r) {
            $b = BookingService::find((int) $r['id']);
            if (Notifier::user((int) $b['user_id'], 'remind_pay', 'อีกไม่นานการจองจะหมดเวลาชำระ', 'ชำระและแนบสลิปก่อน ' . thai_datetime($b['hold_expires_at']),
                '/bookings/' . $b['id'], 'hold:' . $b['id'] . ':' . $b['hold_expires_at'])) {
                $out['hold']++;
            }
        }

        foreach (DB::all("SELECT * FROM appointments WHERE type = 'fitting' AND status = 'scheduled' AND DATE(starts_at) BETWEEN ? AND ?", [$today, $ahead]) as $a) {
            $d = substr($a['starts_at'], 0, 10);
            if (Notifier::user((int) $a['user_id'], 'remind_fitting', relative_day($d) . 'มีนัดลองชุด ' . substr($a['starts_at'], 11, 5) . ' น.', 'ถ้ามาไม่ได้ ยกเลิกนัดในหน้านัดหมาย',
                '/account/appointments', 'fitting:' . $a['id'])) {
                $out['fitting']++;
            }
        }
        return $out;
    }
}
