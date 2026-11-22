<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Receipts are snapshots: items are frozen as JSON when issued, so later price edits never change a printed receipt.
 */
final class Receipts
{
    /** @return array<string, mixed> */
    public static function issueBooking(int $bookingId, ?int $staffId): array
    {
        $existing = DB::one("SELECT * FROM receipts WHERE booking_id = ? AND kind = 'booking'", [$bookingId]);
        if ($existing) {
            return $existing;
        }
        $b = BookingService::find($bookingId);
        $items = [
            ['label' => 'ค่าเช่าชุด ' . $b['costume_code'] . ' ' . $b['costume_name'] . ' (แพ็กเกจ ' . min($b['rental_days'], Settings::int('base_days')) . ' วัน)', 'amount' => (float) $b['rent_amount']],
        ];
        if ((float) $b['extra_amount'] > 0) {
            $items[] = ['label' => 'ค่าเช่าวันเพิ่ม ' . ($b['rental_days'] - Settings::int('base_days')) . ' วัน', 'amount' => (float) $b['extra_amount']];
        }
        if ((float) $b['delivery_fee'] > 0) {
            $items[] = ['label' => 'ค่าจัดส่ง ' . Shipping::DELIVERY[$b['delivery_method']] . ($b['ship_zone'] ? ' (' . Shipping::ZONE[$b['ship_zone']] . ')' : ''), 'amount' => (float) $b['delivery_fee']];
        }
        if ((float) $b['return_fee'] > 0) {
            $items[] = ['label' => 'ค่าใบปะหน้าส่งพัสดุคืน', 'amount' => (float) $b['return_fee']];
        }
        $items[] = ['label' => 'เงินมัดจำ (คืนเมื่อคืนชุดตามเงื่อนไข)', 'amount' => (float) $b['deposit_amount']];
        $method = DB::value("SELECT method FROM payments WHERE booking_id = ? AND kind = 'booking' AND status = 'approved' ORDER BY id DESC LIMIT 1", [$bookingId]);
        $id = DB::insert('receipts', [
            'receipt_no' => Counter::code('RC', 'receipt'),
            'booking_id' => $bookingId,
            'kind' => 'booking',
            'amount' => (float) $b['total_amount'],
            'items_json' => json_encode(['items' => $items, 'method' => $method, 'total_label' => 'รับชำระแล้ว'], JSON_UNESCAPED_UNICODE),
            'issued_by' => $staffId,
        ]);
        BookingService::log($bookingId, $staffId, 'receipt', 'ออกใบเสร็จค่าเช่าและมัดจำ');
        return DB::one('SELECT * FROM receipts WHERE id = ?', [$id]);
    }

    /** @return array<string, mixed> */
    public static function issueSettlement(int $bookingId, ?int $staffId): array
    {
        $existing = DB::one("SELECT * FROM receipts WHERE booking_id = ? AND kind = 'settlement'", [$bookingId]);
        if ($existing) {
            return $existing;
        }
        $b = BookingService::find($bookingId);
        $items = [['label' => 'เงินมัดจำที่ร้านถือไว้', 'amount' => (float) $b['deposit_amount']]];
        if ((float) $b['late_fee'] > 0) {
            $items[] = ['label' => 'หัก ค่าปรับคืนช้า ' . $b['late_days'] . ' วัน × ' . money($b['late_fee_per_day'], false), 'amount' => -(float) $b['late_fee']];
        }
        $found = Inspection::items($bookingId);
        if ($found) {
            foreach ($found as $d) {
                $items[] = ['label' => 'หัก ค่าเสียหาย: ' . $d['label'] . ((int) $d['qty'] > 1 ? ' ×' . $d['qty'] : '') . ($d['note'] ? ' (' . $d['note'] . ')' : ''), 'amount' => -(float) $d['amount']];
            }
            $over = round(array_sum(array_column($found, 'amount')) - (float) $b['damage_fee'], 2);
            if ($over > 0) {
                $items[] = ['label' => 'ไม่คิดส่วนที่เกินมูลค่าชุด', 'amount' => $over];
            }
        } elseif ((float) $b['damage_fee'] > 0) {
            $items[] = ['label' => 'หัก ค่าเสียหาย: ' . $b['damage_note'], 'amount' => -(float) $b['damage_fee']];
        }
        $net = (float) $b['deposit_refund'] - (float) $b['extra_charge'];
        $id = DB::insert('receipts', [
            'receipt_no' => Counter::code('RC', 'receipt'),
            'booking_id' => $bookingId,
            'kind' => 'settlement',
            'amount' => $net,
            'items_json' => json_encode([
                'items' => $items,
                'deposit_refund' => (float) $b['deposit_refund'],
                'extra_charge' => (float) $b['extra_charge'],
                'total_label' => $net >= 0 ? 'คืนเงินมัดจำให้ลูกค้า' : 'ลูกค้าชำระเพิ่ม',
            ], JSON_UNESCAPED_UNICODE),
            'issued_by' => $staffId,
        ]);
        BookingService::log($bookingId, $staffId, 'receipt', 'ออกใบสรุปการคืนชุด');
        return DB::one('SELECT * FROM receipts WHERE id = ?', [$id]);
    }

    /**
     * After a dispute: the part of the damage charge the shop gave back.
     * @return array<string, mixed>
     */
    public static function issueAdjustment(int $bookingId, ?int $staffId): array
    {
        $existing = DB::one("SELECT * FROM receipts WHERE booking_id = ? AND kind = 'adjustment'", [$bookingId]);
        if ($existing) {
            return $existing;
        }
        $b = BookingService::find($bookingId);
        $waived = (float) $b['damage_waived'];
        $id = DB::insert('receipts', [
            'receipt_no' => Counter::code('RC', 'receipt'),
            'booking_id' => $bookingId,
            'kind' => 'adjustment',
            'amount' => $waived,
            'items_json' => json_encode([
                'items' => [
                    ['label' => 'ค่าเสียหายตามใบสรุปการคืนชุด', 'amount' => (float) $b['damage_fee']],
                    ['label' => 'ลดให้หลังทบทวนข้อโต้แย้ง', 'amount' => -$waived],
                ],
                'reply' => $b['dispute_reply'],
                'total_label' => 'คืนเงินให้ลูกค้า',
            ], JSON_UNESCAPED_UNICODE),
            'issued_by' => $staffId,
        ]);
        BookingService::log($bookingId, $staffId, 'receipt', 'ออกใบปรับปรุงค่าเสียหาย');
        return DB::one('SELECT * FROM receipts WHERE id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return DB::one('SELECT r.*, b.user_id, b.booking_no FROM receipts r JOIN bookings b ON b.id = r.booking_id WHERE r.id = ?', [$id]);
    }
}
