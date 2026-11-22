<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Moving the dress by parcel or messenger, both ways.
 *
 *   out : confirmed ──ship──▶ shipped ──delivered──▶ picked_up
 *                        ▲        └──failed / lost──▶ confirmed (resend, switch to pickup at the shop, or cancel)
 *   back: picked_up ──customer posts it──▶ returning ──shop receives it──▶ (inspection) ──▶ returned
 *         picked_up ──a parcel arrives nobody announced──▶ returning (received)
 *         returning ──carrier lost it──▶ returning (lost) ──▶ inspection closes it without late fees
 */
final class Fulfilment
{
    /** @return array<string, mixed>|null */
    public static function lastShipment(int $bookingId, string $direction): ?array
    {
        return DB::one('SELECT * FROM shipments WHERE booking_id = ? AND direction = ? ORDER BY id DESC LIMIT 1', [$bookingId, $direction]);
    }

    /** @return list<array<string, mixed>> */
    public static function shipments(int $bookingId): array
    {
        return DB::all('SELECT * FROM shipments WHERE booking_id = ? ORDER BY id', [$bookingId]);
    }

    /** @return array<string, mixed> */
    private static function lock(int $bookingId): array
    {
        $b = DB::one('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
        if (!$b) {
            throw new BookingException('ไม่พบการจองนี้');
        }
        return $b;
    }

    /** @param array<string, mixed> $b */
    private static function need(array $b, string $status, string $what): void
    {
        if ($b['status'] !== $status) {
            throw new BookingException($what . 'ไม่ได้ เพราะการจองอยู่ในสถานะ “' . Status::label(Status::BOOKING, $b['status']) . '”');
        }
    }

    private static function validDate(string $d): bool
    {
        $x = \DateTime::createFromFormat('!Y-m-d', $d);
        return $x !== false && $x->format('Y-m-d') === $d;
    }

    /**
     * "Flash Express เลขพัสดุ TH0123…" for messages
     * @param array<string, mixed> $s
     */
    public static function describe(array $s): string
    {
        return Shipping::carrierName($s['carrier']) . ($s['tracking_no'] ? ' เลขพัสดุ ' . $s['tracking_no'] : '');
    }

    /**
     * Staff packed the dress and handed it to the carrier.
     * @return array<string, mixed>
     */
    public static function ship(int $bookingId, int $staffId, string $carrier, string $tracking, string $note = ''): array
    {
        return DB::transaction(function () use ($bookingId, $staffId, $carrier, $tracking, $note) {
            $b = self::lock($bookingId);
            self::need($b, 'confirmed', 'ส่งพัสดุ');
            if (!BookingService::ships($b)) {
                throw new BookingException('การจองนี้ลูกค้ารับชุดที่ร้าน');
            }
            if ($b['delivery_method'] === 'post' && $carrier === 'messenger') {
                throw new BookingException('การจองนี้ส่งพัสดุ เลือกบริษัทขนส่ง');
            }
            if (today() > $b['return_date']) {
                throw new BookingException('เลยวันคืนชุดของการจองนี้แล้ว ให้แก้ไขวันหรือยกเลิกก่อน');
            }
            [$no, $err] = Shipping::cleanTracking($carrier, $tracking);
            if ($err) {
                throw new BookingException($err, ['tracking_no' => $err]);
            }
            DB::insert('shipments', [
                'booking_id' => $bookingId, 'direction' => 'out', 'carrier' => $carrier, 'tracking_no' => $no,
                'shipped_on' => today(), 'note' => trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null, 'created_by' => $staffId,
            ]);
            // sent earlier than planned: the dress is off the rail from today
            DB::update('bookings', ['status' => 'shipped', 'block_from' => min((string) $b['block_from'], today())], 'id = :id', ['id' => $bookingId]);
            $s = self::lastShipment($bookingId, 'out');
            BookingService::log($bookingId, $staffId, 'shipped', 'ส่งชุดออกจากร้าน · ' . self::describe($s));
            $full = BookingService::find($bookingId);
            $page = Shipping::trackingPage($carrier);
            Notifier::user((int) $full['user_id'], 'shipped', 'ร้านส่งชุด ' . $full['costume_name'] . ' แล้ว', self::describe($s) . ' · ควรถึงภายใน ' . thai_date($full['pickup_date']),
                '/bookings/' . $bookingId, null, [
                    'subject' => 'ส่งชุดแล้ว ' . $full['booking_no'] . ($no ? ' · เลขพัสดุ ' . $no : ''),
                    'data' => BookingService::mailData($full, 'ชุดของคุณออกจากร้านแล้ว', array_values(array_filter([
                        self::describe($s) . ($page ? ' ติดตามได้ที่ ' . $page : ''),
                        'เมื่อได้รับ เปิดกล่องตรวจชุดและของที่มากับชุด แล้วกด “ได้รับชุดแล้ว” พร้อมถ่ายรูปสภาพชุดในหน้าการจอง',
                        'ถ้าพบปัญหา แจ้งร้านภายใน ' . Settings::int('arrival_report_hours') . ' ชั่วโมงหลังได้รับ เพื่อไม่ให้ถูกคิดค่าเสียหายที่ไม่ได้เกิดจากคุณ',
                        $full['return_method'] === 'post' ? 'เก็บกล่องและใบปะหน้าส่งคืนไว้ ใช้ส่งชุดกลับภายใน ' . thai_date($full['return_date']) : null,
                    ])), ['ดูการจอง', absolute_url('/bookings/' . $bookingId)], ['ขนส่ง' => self::describe($s)]),
                ]);
            return $full;
        });
    }

    /**
     * The parcel reached the customer: she confirmed it, or staff saw "delivered" on the carrier's page.
     * @return array<string, mixed>
     */
    public static function markDelivered(int $bookingId, int $actorId, bool $byCustomer): array
    {
        return DB::transaction(function () use ($bookingId, $actorId, $byCustomer) {
            $b = self::lock($bookingId);
            self::need($b, 'shipped', 'ยืนยันการได้รับชุด');
            $s = self::lastShipment($bookingId, 'out');
            if ($s) {
                DB::update('shipments', ['status' => 'delivered', 'arrived_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $s['id']]);
            }
            DB::update('bookings', ['status' => 'picked_up', 'picked_up_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $bookingId]);
            BookingService::log($bookingId, $actorId, 'delivered', $byCustomer ? 'ลูกค้ายืนยันว่าได้รับชุดแล้ว' : 'ร้านยืนยันว่าพัสดุถึงลูกค้าแล้ว (ตามระบบติดตามพัสดุ)');
            $full = BookingService::find($bookingId);
            $back = $full['return_method'] === 'post'
                ? 'ส่งพัสดุคืนภายใน ' . thai_date($full['return_date']) . ' ด้วยใบปะหน้าที่แนบในกล่อง'
                : 'คืนชุดที่ร้านภายใน ' . thai_date($full['return_date']) . ' ' . substr((string) $full['return_time'], 0, 5) . ' น.';
            if ($byCustomer) {
                Notifier::staff('delivered', 'ลูกค้าได้รับชุดแล้ว ' . $full['booking_no'], $full['costume_code'] . ' · ' . full_name($full), '/admin/bookings/' . $bookingId);
            } else {
                Notifier::user((int) $full['user_id'], 'delivered', 'พัสดุถึงคุณแล้ว', $back . ' · ตรวจสภาพชุดและแจ้งปัญหาได้ภายใน ' . Settings::int('arrival_report_hours') . ' ชั่วโมง', '/bookings/' . $bookingId);
            }
            return $full;
        });
    }

    /**
     * The carrier could not deliver (nobody home, wrong address, sent back) or lost the parcel.
     * The booking goes back to "confirmed" so staff can resend, switch to pickup at the shop or cancel.
     * @return array<string, mixed>
     */
    public static function deliveryFailed(int $bookingId, int $staffId, string $reason, bool $lost = false): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new BookingException('ระบุสาเหตุที่ส่งไม่สำเร็จ', ['reason' => 'ระบุสาเหตุ']);
        }
        return DB::transaction(function () use ($bookingId, $staffId, $reason, $lost) {
            $b = self::lock($bookingId);
            self::need($b, 'shipped', 'บันทึกว่าส่งไม่สำเร็จ');
            $s = self::lastShipment($bookingId, 'out');
            if ($s) {
                DB::update('shipments', ['status' => $lost ? 'lost' : 'failed', 'note' => mb_substr($reason, 0, 255)], 'id = :id', ['id' => $s['id']]);
            }
            DB::update('bookings', ['status' => 'confirmed'], 'id = :id', ['id' => $bookingId]);
            BookingService::log($bookingId, $staffId, 'delivery_failed', ($lost ? 'พัสดุขาไปสูญหาย: ' : 'ส่งไม่สำเร็จ: ') . $reason);
            $full = BookingService::find($bookingId);
            Notifier::user((int) $full['user_id'], 'delivery_failed', ($lost ? 'พัสดุของการจอง ' : 'ส่งชุดไม่สำเร็จ การจอง ') . $full['booking_no'] . ($lost ? ' สูญหายระหว่างขนส่ง' : ''),
                $reason . ' · ร้านจะติดต่อเพื่อส่งใหม่ ให้มารับที่ร้าน หรือคืนเงิน', '/bookings/' . $bookingId, null, [
                    'subject' => ($lost ? 'พัสดุสูญหาย ' : 'ส่งชุดไม่สำเร็จ ') . $full['booking_no'],
                    'data' => BookingService::mailData($full, $lost ? 'พัสดุสูญหายระหว่างขนส่ง' : 'ขนส่งส่งชุดให้คุณไม่สำเร็จ', [
                        'สาเหตุ: ' . $reason,
                        'ร้านจะติดต่อคุณเพื่อเลือกว่าจะให้ส่งใหม่ มารับที่ร้านเอง หรือยกเลิกพร้อมคืนเงิน',
                        'ถ้าที่อยู่หรือเบอร์โทรไม่ถูกต้อง แจ้งร้านได้ทาง LINE ' . Settings::get('shop_line'),
                    ], ['ดูการจอง', absolute_url('/bookings/' . $bookingId)]),
                ]);
            return $full;
        });
    }

    /**
     * The customer will come to the shop instead (delivery failed, or she asked). The delivery fee is refunded
     * when no parcel has gone out yet; after a failed attempt the courier was already paid.
     * @return array<string, mixed>
     */
    public static function switchToPickup(int $bookingId, int $staffId): array
    {
        return DB::transaction(function () use ($bookingId, $staffId) {
            $b = self::lock($bookingId);
            self::need($b, 'confirmed', 'เปลี่ยนเป็นรับที่ร้าน');
            if (!BookingService::ships($b)) {
                throw new BookingException('การจองนี้รับชุดที่ร้านอยู่แล้ว');
            }
            $attempted = self::lastShipment($bookingId, 'out') !== null;
            $refund = $attempted ? 0.0 : (float) $b['delivery_fee'];
            DB::update('bookings', ['delivery_method' => 'pickup', 'ship_by_date' => null, 'block_from' => min((string) $b['block_from'], (string) $b['pickup_date'])],
                'id = :id', ['id' => $bookingId]);
            if ($refund > 0) {
                DB::insert('payments', ['booking_id' => $bookingId, 'kind' => 'refund', 'method' => 'transfer', 'amount' => $refund,
                    'status' => 'approved', 'reviewed_by' => $staffId, 'reviewed_at' => date('Y-m-d H:i:s'), 'created_by' => $staffId]);
            }
            Appointments::syncForBooking($bookingId, $staffId);
            BookingService::log($bookingId, $staffId, 'switch_pickup', 'เปลี่ยนเป็นรับชุดที่ร้าน' . ($refund > 0 ? ' · คืนค่าจัดส่ง ' . money($refund) : ''));
            $full = BookingService::find($bookingId);
            Notifier::user((int) $full['user_id'], 'switch_pickup', 'เปลี่ยนเป็นรับชุดที่ร้าน การจอง ' . $full['booking_no'],
                'มารับ ' . thai_date($full['pickup_date']) . ' เวลา ' . substr((string) $full['pickup_time'], 0, 5) . ' น.' . ($refund > 0 ? ' · ร้านคืนค่าจัดส่ง ' . money($refund) : ''),
                '/bookings/' . $bookingId);
            return $full;
        });
    }

    /**
     * The customer (or staff on her behalf) posted the dress back. Late days stop on the day it was posted.
     * @return array<string, mixed>
     */
    public static function postBack(int $bookingId, int $actorId, string $carrier, string $tracking, string $postedOn, bool $byStaff = false): array
    {
        if (!self::validDate($postedOn)) {
            throw new BookingException('เลือกวันที่ฝากส่ง', ['posted_on' => 'เลือกวันที่ฝากส่ง']);
        }
        return DB::transaction(function () use ($bookingId, $actorId, $carrier, $tracking, $postedOn, $byStaff) {
            $b = self::lock($bookingId);
            self::need($b, 'picked_up', 'แจ้งส่งชุดคืน');
            if ($postedOn > today() || $postedOn < substr((string) $b['picked_up_at'], 0, 10)) {
                throw new BookingException('วันที่ฝากส่งต้องอยู่ระหว่างวันที่ได้รับชุดถึงวันนี้', ['posted_on' => 'วันที่ไม่ถูกต้อง']);
            }
            // staff receiving a parcel nobody announced may not have its number (a torn label)
            [$no, $err] = $byStaff && trim($tracking) === '' && isset(Shipping::CARRIERS[$carrier]) ? [null, null] : Shipping::cleanTracking($carrier, $tracking);
            if ($err) {
                throw new BookingException($err, ['tracking_no' => $err]);
            }
            DB::insert('shipments', [
                'booking_id' => $bookingId, 'direction' => 'back', 'carrier' => $carrier, 'tracking_no' => $no,
                'shipped_on' => $postedOn, 'created_by' => $actorId,
            ]);
            // a parcel posted late arrives late: keep the dress blocked until it can be back and cleaned
            $back = Shipping::transitDays('post', $b['ship_zone'] ?? 'upcountry');
            $until = max((string) $b['block_until'], Availability::blockUntil($postedOn, $back));
            DB::update('bookings', ['status' => 'returning', 'return_method' => 'post', 'block_until' => $until], 'id = :id', ['id' => $bookingId]);
            DB::run("UPDATE appointments SET status = 'cancelled' WHERE booking_id = ? AND type = 'return' AND status = 'scheduled'", [$bookingId]);
            $s = self::lastShipment($bookingId, 'back');
            $late = max(0, days_between($b['return_date'], $postedOn));
            BookingService::log($bookingId, $actorId, 'posted_back', ($byStaff ? 'ร้านบันทึกแทนลูกค้า: ' : '') . 'ส่งชุดคืนทางพัสดุ ' . thai_date($postedOn)
                . ' · ' . self::describe($s) . ($b['return_method'] === 'store' ? ' (เดิมเลือกคืนที่ร้าน)' : '') . ($late ? " · ช้า $late วัน" : ''));
            $full = BookingService::find($bookingId);
            Notifier::staff('posted_back', 'ลูกค้าส่งชุดคืนแล้ว ' . $full['booking_no'], self::describe($s) . ' · ฝากส่ง ' . thai_date($postedOn), '/admin/bookings/' . $bookingId);
            if ($byStaff) {
                Notifier::user((int) $full['user_id'], 'posted_back', 'ร้านบันทึกการส่งคืนของคุณแล้ว', self::describe($s), '/bookings/' . $bookingId);
            }
            return $full;
        });
    }

    /**
     * The return parcel is at the shop. A parcel nobody announced can be received straight from "picked up":
     * staff type the posting date from the postmark or the carrier's page.
     * @return array<string, mixed>
     */
    public static function receiveBack(int $bookingId, int $staffId, ?string $postedOn = null, string $carrier = 'other', string $tracking = ''): array
    {
        if ($postedOn !== null && $postedOn !== '' && !self::validDate($postedOn)) {
            throw new BookingException('วันที่ฝากส่งไม่ถูกต้อง', ['posted_on' => 'วันที่ไม่ถูกต้อง']);
        }
        return DB::transaction(function () use ($bookingId, $staffId, $postedOn, $carrier, $tracking) {
            $b = self::lock($bookingId);
            if ($b['status'] === 'picked_up') {
                if (!$postedOn) {
                    throw new BookingException('ใส่วันที่ลูกค้าฝากส่ง (ดูจากตราประทับหรือระบบติดตามพัสดุ)', ['posted_on' => 'ใส่วันที่ฝากส่ง']);
                }
                self::postBack($bookingId, $staffId, isset(Shipping::CARRIERS[$carrier]) ? $carrier : 'other', $tracking, $postedOn, true);
                $b = self::lock($bookingId);
            }
            self::need($b, 'returning', 'รับพัสดุคืน');
            $s = self::lastShipment($bookingId, 'back');
            if (!$s || $s['status'] === 'received') {
                throw new BookingException('รับพัสดุคืนของการจองนี้ไปแล้ว');
            }
            $set = ['status' => 'received', 'arrived_at' => date('Y-m-d H:i:s')];
            $fix = '';
            if ($postedOn && $postedOn !== $s['shipped_on'] && $postedOn <= today()) {
                $set['shipped_on'] = $postedOn;
                $fix = ' · แก้วันฝากส่งจาก ' . thai_date($s['shipped_on']) . ' เป็น ' . thai_date($postedOn) . ' ตามตราประทับ';
            }
            DB::update('shipments', $set, 'id = :id', ['id' => $s['id']]);
            BookingService::log($bookingId, $staffId, 'received_back', 'ร้านได้รับพัสดุคืนแล้ว รอตรวจสภาพ' . $fix);
            $full = BookingService::find($bookingId);
            Notifier::user((int) $full['user_id'], 'received_back', 'ร้านได้รับชุดคืนแล้ว', 'กำลังตรวจสภาพชุด จะแจ้งยอดคืนเงินมัดจำเร็วที่สุด', '/bookings/' . $bookingId);
            return $full;
        });
    }

    /**
     * The return parcel is lost in transit. The customer posted it (tracking number on record), so she is not
     * charged for lateness; the shop claims from the carrier and closes the rental at inspection.
     * @return array<string, mixed>
     */
    public static function returnLost(int $bookingId, int $staffId, string $note): array
    {
        $note = trim($note);
        if ($note === '') {
            throw new BookingException('ระบุรายละเอียด เช่น เลขเคลมหรือผลการติดตามกับขนส่ง', ['reason' => 'ระบุรายละเอียด']);
        }
        return DB::transaction(function () use ($bookingId, $staffId, $note) {
            $b = self::lock($bookingId);
            self::need($b, 'returning', 'บันทึกพัสดุสูญหาย');
            $s = self::lastShipment($bookingId, 'back');
            if (!$s || $s['status'] !== 'in_transit') {
                throw new BookingException('บันทึกได้เฉพาะพัสดุที่ยังอยู่ระหว่างขนส่ง');
            }
            DB::update('shipments', ['status' => 'lost', 'note' => mb_substr($note, 0, 255)], 'id = :id', ['id' => $s['id']]);
            BookingService::log($bookingId, $staffId, 'return_lost', 'พัสดุคืนสูญหาย: ' . $note);
            $full = BookingService::find($bookingId);
            Notifier::user((int) $full['user_id'], 'return_lost', 'พัสดุส่งคืนของการจอง ' . $full['booking_no'] . ' สูญหาย',
                'ร้านกำลังติดตามกับขนส่ง คุณไม่ต้องทำอะไรเพิ่ม เราจะแจ้งผลและยอดคืนมัดจำ', '/bookings/' . $bookingId);
            return $full;
        });
    }

    /** @param array<string, mixed> $b */
    public static function arrivalWindowOpen(array $b): bool
    {
        return $b['status'] === 'picked_up' && $b['arrival_reported_at'] === null && $b['picked_up_at'] !== null
            && strtotime((string) $b['picked_up_at']) + Settings::int('arrival_report_hours') * 3600 >= time();
    }

    /**
     * The customer recorded the state the dress arrived in (photos, and a note when something is wrong),
     * so the return inspection does not charge her for it.
     * @return array<string, mixed>
     */
    public static function reportArrival(int $bookingId, int $userId, string $note): array
    {
        return DB::transaction(function () use ($bookingId, $userId, $note) {
            $b = self::lock($bookingId);
            if (!self::arrivalWindowOpen($b)) {
                throw new BookingException('แจ้งสภาพชุดตอนได้รับได้ภายใน ' . Settings::int('arrival_report_hours') . ' ชั่วโมงหลังได้รับชุด และแจ้งได้ครั้งเดียว ติดต่อร้านถ้ามีปัญหาเพิ่ม');
            }
            $note = mb_substr(trim($note), 0, 500);
            DB::update('bookings', ['arrival_note' => $note !== '' ? $note : null, 'arrival_reported_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $bookingId]);
            BookingService::log($bookingId, $userId, 'arrival', $note !== '' ? 'ลูกค้าแจ้งปัญหาตอนได้รับชุด: ' . $note : 'ลูกค้าส่งรูปสภาพชุดตอนได้รับ ไม่มีปัญหา');
            $full = BookingService::find($bookingId);
            if ($note !== '') {
                Notifier::staff('arrival_issue', 'ลูกค้าแจ้งปัญหาชุด ' . $full['booking_no'], $note, '/admin/bookings/' . $bookingId);
            }
            return $full;
        });
    }
}
