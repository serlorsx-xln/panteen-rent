<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * The rental lifecycle.
 *
 *   pending_payment ──slip──▶ awaiting_verification ──approve──▶ confirmed ──pickup──▶ picked_up ──return──▶ returned
 *        │   ▲                        │ reject (back to pending, new hold)      │  ▲                  │  ▲
 *        │   └────────────────────────┘                    ship (parcel) ──▶ shipped  post back ──▶ returning
 *        │                                     delivery failed ◀──┘ (back to confirmed: resend, pick up or cancel)
 *        ├── hold runs out ──▶ expired
 *        └── cancel (member: until N days before the dress leaves; staff: any time before it leaves) ──▶ cancelled
 *
 * Shipping steps live in Fulfilment, inspection and disputes in Inspection; both end here in markReturned().
 */
final class BookingService
{
    /** Bookings that hold the dress or have it out, from the unpaid hold to picked up (a SQL list) */
    public const ACTIVE_SQL = "('pending_payment','awaiting_verification','confirmed','shipped','picked_up','returning')";

    /** Bookings that count as a rental: confirmed, on its way, out with the customer, or back (a SQL list) */
    public const RENTED_SQL = "('confirmed','shipped','picked_up','returning','returned')";

    /** The dress is away from the shop (a SQL list) */
    public const OUT_SQL = "('shipped','picked_up','returning')";

    public const TRANSITIONS = [
        'pending_payment'       => ['awaiting_verification', 'confirmed', 'cancelled', 'expired'],
        'awaiting_verification' => ['confirmed', 'pending_payment', 'cancelled'],
        'confirmed'             => ['shipped', 'picked_up', 'cancelled'],
        'shipped'               => ['picked_up', 'confirmed'],
        'picked_up'             => ['returning', 'returned'],
        'returning'             => ['returned'],
        'returned'              => [],
        'cancelled'             => [],
        'expired'               => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** @param array<string, mixed> $booking */
    private static function assertTransition(array $booking, string $to): void
    {
        if (!self::canTransition($booking['status'], $to)) {
            throw new BookingException('ทำรายการนี้ไม่ได้ เพราะการจองอยู่ในสถานะ “' . Status::label(Status::BOOKING, $booking['status']) . '”');
        }
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return DB::one(
            'SELECT b.*, c.code AS costume_code, c.name AS costume_name, c.size_label, c.late_fee_per_day, c.color_name,
                    c.replacement_value, c.accessories, c.status AS costume_status,
                    cat.name AS category_name, cat.color AS category_color,
                    (SELECT path FROM costume_images i WHERE i.costume_id = c.id ORDER BY sort_order, id LIMIT 1) AS image,
                    u.first_name, u.last_name, u.nickname, u.email, u.phone, u.line_id
               FROM bookings b
               JOIN costumes c ON c.id = b.costume_id
               JOIN categories cat ON cat.id = c.category_id
               JOIN users u ON u.id = b.user_id
              WHERE b.id = ?',
            [$id]
        );
    }

    public static function log(int $bookingId, ?int $actorId, string $type, string $message): void
    {
        DB::insert('booking_events', ['booking_id' => $bookingId, 'actor_id' => $actorId, 'type' => $type, 'message' => $message]);
    }

    /**
     * Validate the dates a customer asked for. Returns a list of field => message.
     * @param array<string, mixed> $costume
     * @return array<string, string>
     */
    public static function validateDates(array $costume, string $event, string $pickup, string $return, bool $staff = false): array
    {
        $errors = [];
        foreach (['event_date' => $event, 'pickup_date' => $pickup, 'return_date' => $return] as $f => $v) {
            $d = \DateTime::createFromFormat('!Y-m-d', $v);
            if (!$d || $d->format('Y-m-d') !== $v) {
                $errors[$f] = 'เลือกวันที่ให้ถูกต้อง';
            }
        }
        if ($errors) {
            return $errors;
        }
        $today = today();
        $earliest = add_days($today, $staff ? 0 : Settings::int('min_lead_days'));
        if ($pickup < $earliest) {
            $errors['pickup_date'] = 'วันรับชุดต้องไม่ก่อนวันที่ ' . thai_date($earliest);
        }
        if ($event < $pickup || $event > $return) {
            $errors['event_date'] = 'วันงานต้องอยู่ระหว่างวันรับชุดและวันคืนชุด';
        }
        if ($return < $pickup) {
            $errors['return_date'] = 'วันคืนชุดต้องไม่ก่อนวันรับชุด';
        }
        $max = Settings::int('max_days');
        if (Pricing::rentalDays($pickup, $return) > $max) {
            $errors['return_date'] = "เช่าได้สูงสุด $max วันต่อครั้ง";
        }
        $horizon = add_days($today, Settings::int('booking_horizon_days'));
        if ($event > $horizon) {
            $errors['event_date'] = 'จองล่วงหน้าได้ถึงวันที่ ' . thai_date($horizon);
        }
        if ($costume['status'] !== 'active') {
            $errors['costume'] = 'ชุดนี้งดให้เช่าชั่วคราว';
        }
        return $errors;
    }

    /**
     * Create a booking. The costume row is locked so two customers can never take the same dates.
     *
     * `address` is a checked address (Shipping::cleanAddress) and is needed whenever a parcel is involved.
     *
     * @param array{user_id:int, costume_id:int, event_date:string, pickup_date:string, return_date:string,
     *              pickup_time?:string, return_time?:string, note?:string, channel?:string, actor_id?:int,
     *              delivery_method?:string, return_method?:string, address?:array<string, mixed>|null} $in
     * @return array<string, mixed>
     */
    public static function create(array $in): array
    {
        $channel = $in['channel'] ?? 'online';
        $staff = $channel === 'counter';
        $actorId = $in['actor_id'] ?? $in['user_id'];

        return DB::transaction(function () use ($in, $channel, $staff, $actorId) {
            $costume = DB::one('SELECT * FROM costumes WHERE id = ? FOR UPDATE', [(int) $in['costume_id']]);
            if (!$costume) {
                throw new BookingException('ไม่พบชุดนี้');
            }
            // the customer row is locked too, so two bookings sent at once cannot both pass the unpaid-holds limit
            $customer = DB::one('SELECT * FROM users WHERE id = ? FOR UPDATE', [(int) $in['user_id']]);
            if (!$customer || $customer['status'] !== 'active') {
                throw new BookingException('บัญชีลูกค้านี้ใช้งานไม่ได้');
            }
            $errors = self::validateDates($costume, $in['event_date'], $in['pickup_date'], $in['return_date'], $staff);
            if ($errors) {
                throw new BookingException(reset($errors), $errors);
            }
            $address = $in['address'] ?? null;
            $plan = Shipping::plan($in['pickup_date'], $in['return_date'], $in['delivery_method'] ?? 'pickup', $in['return_method'] ?? 'store',
                $address ? Shipping::zone((string) $address['province']) : null);
            $errors = Shipping::check($plan, $staff);
            if ($errors) {
                throw new BookingException(reset($errors), $errors);
            }
            $ships = $plan['zone'] !== null;
            if (!$staff) {
                $open = (int) DB::value("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'pending_payment' AND hold_expires_at > NOW()", [$customer['id']]);
                if ($open >= 3) {
                    throw new BookingException('คุณมีรายการรอชำระเงินอยู่ 3 รายการแล้ว ชำระหรือยกเลิกรายการเดิมก่อนจองเพิ่ม');
                }
            }
            $conflicts = Availability::conflicts((int) $costume['id'], $in['pickup_date'], $in['return_date'], null, $plan['out_days'], $plan['back_days']);
            if ($conflicts) {
                $c = $conflicts[0];
                throw new BookingException(
                    'ชุดนี้ไม่ว่างช่วง ' . thai_date($c['block_from'], false, false) . ' – ' . thai_date($c['busy_until'])
                    . ' (รวมวัน' . ($plan['out_days'] + $plan['back_days'] > 0 ? 'ขนส่งและวัน' : '') . 'ซักทำความสะอาด) ลองเลือกวันอื่นหรือชุดอื่น',
                    ['dates' => 'conflict']
                );
            }

            $q = Pricing::quote($costume, $in['pickup_date'], $in['return_date'], null, $plan['delivery_fee'], $plan['return_fee']);
            $hold = time() + Settings::int('hold_hours') * 3600;
            if ($plan['ship_by'] !== null) {
                // an unpaid parcel booking cannot wait past the day's dispatch cut-off: the parcel would miss the date
                $cutoff = strtotime($plan['ship_by'] . ' ' . Settings::get('ship_cutoff', '14:00') . ':00');
                if ($cutoff > time()) {
                    $hold = min($hold, $cutoff);
                }
            }
            $time = fn($v, $def) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $v) ? $v : $def;

            $id = DB::insert('bookings', [
                'booking_no'      => Counter::code('PT', 'booking'),
                'user_id'         => (int) $customer['id'],
                'costume_id'      => (int) $costume['id'],
                'created_by'      => $actorId,
                'channel'         => $channel,
                'event_date'      => $in['event_date'],
                'pickup_date'     => $in['pickup_date'],
                'pickup_time'     => $time($in['pickup_time'] ?? null, Settings::get('default_pickup_time')),
                'return_date'     => $in['return_date'],
                'return_time'     => $time($in['return_time'] ?? null, Settings::get('default_return_time')),
                'delivery_method' => $plan['delivery'],
                'return_method'   => $plan['return'],
                'ship_zone'       => $plan['zone'],
                'ship_by_date'    => $plan['ship_by'],
                'ship_recipient'  => $ships ? $address['recipient'] : null,
                'ship_phone'      => $ships ? $address['phone'] : null,
                'ship_address'    => $ships ? Shipping::addressText($address) : null,
                'ship_province'   => $ships ? $address['province'] : null,
                'ship_postcode'   => $ships ? $address['postcode'] : null,
                'block_from'      => $plan['block_from'],
                'block_until'     => $plan['block_until'],
                'rental_days'     => $q['days'],
                'rent_amount'     => $q['rent'],
                'extra_amount'    => $q['extra'],
                'deposit_amount'  => $q['deposit'],
                'delivery_fee'    => $q['delivery_fee'],
                'return_fee'      => $q['return_fee'],
                'total_amount'    => $q['total'],
                'status'          => 'pending_payment',
                'hold_expires_at' => date('Y-m-d H:i:s', $hold),
                'note'            => isset($in['note']) && $in['note'] !== '' ? mb_substr($in['note'], 0, 500) : null,
            ]);
            $b = self::find($id);
            self::log($id, $actorId, 'created', $channel === 'counter' ? 'พนักงานสร้างการจองที่หน้าร้าน' : 'ลูกค้าจองผ่านเว็บไซต์');

            if ($channel === 'online') {
                Notifier::user((int) $customer['id'], 'booking_created', 'จองชุด ' . $b['costume_name'] . ' แล้ว รอชำระเงิน',
                    'ชำระ ' . money($b['total_amount']) . ' ภายใน ' . thai_datetime($b['hold_expires_at']) . ' ไม่เช่นนั้นการจองจะถูกยกเลิกอัตโนมัติ',
                    '/bookings/' . $id, null, [
                        'subject' => 'รับการจอง ' . $b['booking_no'] . ' แล้ว รอชำระเงิน',
                        'data' => self::mailData($b, 'เราจองชุดไว้ให้คุณแล้ว', [
                            'ชำระเงินภายใน ' . thai_datetime($b['hold_expires_at']) . ' ด้วยการสแกน QR พร้อมเพย์ในหน้าการจอง แล้วแนบสลิป',
                            'ถ้าไม่ชำระภายในเวลานี้ ระบบจะปล่อยชุดให้คนอื่นจองโดยอัตโนมัติ',
                        ], ['ชำระเงินและแนบสลิป', absolute_url('/bookings/' . $id)]),
                    ]);
                Notifier::staff('booking_new', 'การจองใหม่ ' . $b['booking_no'], $b['costume_name'] . ' · วันงาน ' . thai_date($b['event_date'])
                    . ($plan['ship_by'] ? ' · ' . Shipping::DELIVERY[$plan['delivery']] . ' ส่งออกภายใน ' . thai_date($plan['ship_by']) : ''), '/admin/bookings/' . $id);
            }
            return $b;
        });
    }

    /**
     * Lines shared by booking e-mails.
     * @param array<string, mixed> $b
     * @param list<string> $lines
     * @param array{string, string}|null $button [label, url]
     * @param array<string, mixed> $extraItems
     * @return array{heading: string, lines: list<string>, items: array<string, mixed>, button: array{string, string}|null}
     */
    public static function mailData(array $b, string $heading, array $lines, ?array $button = null, array $extraItems = []): array
    {
        $items = [
            'เลขที่การจอง' => $b['booking_no'],
            'ชุด' => $b['costume_code'] . ' · ' . $b['costume_name'] . ' (ไซส์ ' . $b['size_label'] . ')',
            'วันงาน' => thai_date_full($b['event_date']),
        ];
        $items += self::handoverLines($b);
        $items['ค่าเช่า (' . $b['rental_days'] . ' วัน)'] = money((float) $b['rent_amount'] + (float) $b['extra_amount']);
        if ((float) $b['delivery_fee'] > 0) {
            $items['ค่าจัดส่ง'] = money($b['delivery_fee']);
        }
        if ((float) $b['return_fee'] > 0) {
            $items['ค่าใบปะหน้าส่งคืน'] = money($b['return_fee']);
        }
        $items['เงินมัดจำ (ได้คืน)'] = money($b['deposit_amount']);
        $items['ยอดชำระ'] = money($b['total_amount']);
        return ['heading' => $heading, 'lines' => $lines, 'items' => $items + $extraItems, 'button' => $button];
    }

    /**
     * How the dress reaches the customer and comes back, as label => text (mail, booking page, receipt).
     * @param array<string, mixed> $b
     * @return array<string, string>
     */
    public static function handoverLines(array $b): array
    {
        $t = fn($v) => substr((string) $v, 0, 5) . ' น.';
        $lines = [];
        if (($b['delivery_method'] ?? 'pickup') === 'pickup') {
            $lines['รับชุดที่ร้าน'] = thai_date($b['pickup_date']) . ' เวลา ' . $t($b['pickup_time']);
        } else {
            $lines['ได้รับชุดภายใน'] = thai_date($b['pickup_date']) . ' · ' . Shipping::DELIVERY[$b['delivery_method']]
                . ($b['ship_by_date'] ? ' (ร้านส่งออก ' . thai_date($b['ship_by_date']) . ')' : '');
            $lines['ส่งถึง'] = $b['ship_recipient'] . ' ' . phone_fmt((string) $b['ship_phone']) . ' · ' . str_replace("\n", ' · ', (string) $b['ship_address']);
        }
        if (($b['return_method'] ?? 'store') === 'post') {
            $lines['ส่งพัสดุคืนภายใน'] = thai_date($b['return_date']) . ' (นับวันที่ฝากส่ง ใช้ใบปะหน้าในกล่อง)';
        } else {
            $lines['คืนชุดที่ร้าน'] = thai_date($b['return_date']) . ' เวลา ' . $t($b['return_time']);
        }
        return $lines;
    }

    /** @param array<string, mixed> $b */
    public static function ships(array $b): bool
    {
        return ($b['delivery_method'] ?? 'pickup') !== 'pickup';
    }

    /**
     * Customer uploaded a transfer slip.
     * @return array<string, mixed>
     */
    public static function submitSlip(int $bookingId, int $userId, string $slipPath, string $method = 'promptpay'): array
    {
        return DB::transaction(function () use ($bookingId, $userId, $slipPath, $method) {
            $b = DB::one('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
            if (!$b) {
                throw new BookingException('ไม่พบการจองนี้');
            }
            if ($b['status'] === 'pending_payment' && $b['hold_expires_at'] && strtotime($b['hold_expires_at']) < time()) {
                self::expireOne($b);
                throw new BookingException('หมดเวลาชำระเงินแล้ว ชุดถูกปล่อยให้คนอื่นจอง กรุณาจองใหม่ถ้ายังว่าง');
            }
            self::assertTransition($b, 'awaiting_verification');
            DB::insert('payments', [
                'booking_id' => $bookingId,
                'kind' => 'booking',
                'method' => $method,
                'amount' => $b['total_amount'],
                'slip_path' => $slipPath,
                'status' => 'pending',
                'created_by' => $userId,
            ]);
            DB::update('bookings', ['status' => 'awaiting_verification', 'hold_expires_at' => null], 'id = :id', ['id' => $bookingId]);
            self::log($bookingId, $userId, 'slip', 'แนบสลิปการโอน ' . money($b['total_amount']));
            $full = self::find($bookingId);
            Notifier::staff('slip_new', 'สลิปใหม่รอตรวจ ' . $full['booking_no'], money($full['total_amount']) . ' · ' . full_name($full), '/admin/payments');
            Notifier::user((int) $full['user_id'], 'slip_received', 'ได้รับสลิปแล้ว ร้านกำลังตรวจสอบ', 'เราจะแจ้งผลทางอีเมลและในระบบ', '/bookings/' . $bookingId);
            return $full;
        });
    }

    /** @return array<string, mixed> */
    public static function approvePayment(int $paymentId, int $staffId): array
    {
        return DB::transaction(function () use ($paymentId, $staffId) {
            $p = DB::one('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
            if (!$p || $p['status'] !== 'pending') {
                throw new BookingException('สลิปนี้ถูกตรวจไปแล้ว');
            }
            $b = DB::one('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$p['booking_id']]);
            self::assertTransition($b, 'confirmed');
            DB::update('payments', ['status' => 'approved', 'reviewed_by' => $staffId, 'reviewed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $paymentId]);
            return self::confirm($b, $staffId, 'อนุมัติสลิปการโอน ' . money($p['amount']));
        });
    }

    /** @return array<string, mixed> */
    public static function rejectPayment(int $paymentId, int $staffId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new BookingException('ระบุเหตุผลที่สลิปไม่ผ่าน เพื่อให้ลูกค้าแก้ไขได้ถูก');
        }
        return DB::transaction(function () use ($paymentId, $staffId, $reason) {
            $p = DB::one('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
            if (!$p || $p['status'] !== 'pending') {
                throw new BookingException('สลิปนี้ถูกตรวจไปแล้ว');
            }
            $b = DB::one('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$p['booking_id']]);
            self::assertTransition($b, 'pending_payment');
            DB::update('payments', ['status' => 'rejected', 'reject_reason' => mb_substr($reason, 0, 255), 'reviewed_by' => $staffId, 'reviewed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $paymentId]);
            // Give the customer a fresh hold to send a correct slip.
            $hold = date('Y-m-d H:i:s', time() + Settings::int('hold_hours') * 3600);
            DB::update('bookings', ['status' => 'pending_payment', 'hold_expires_at' => $hold], 'id = :id', ['id' => $b['id']]);
            self::log((int) $b['id'], $staffId, 'slip_rejected', 'สลิปไม่ผ่าน: ' . $reason);
            $full = self::find((int) $b['id']);
            Notifier::user((int) $full['user_id'], 'slip_rejected', 'สลิปของการจอง ' . $full['booking_no'] . ' ไม่ผ่านการตรวจ', $reason, '/bookings/' . $full['id'], null, [
                'subject' => 'สลิปการจอง ' . $full['booking_no'] . ' ไม่ผ่านการตรวจสอบ',
                'data' => self::mailData($full, 'สลิปของคุณยังไม่ผ่าน', [
                    'เหตุผลจากร้าน: ' . $reason,
                    'ชุดยังจองไว้ให้คุณถึง ' . thai_datetime($hold) . ' แนบสลิปที่ถูกต้องอีกครั้งได้ในหน้าการจอง',
                ], ['แนบสลิปใหม่', absolute_url('/bookings/' . $full['id'])]),
            ]);
            return $full;
        });
    }

    /**
     * Counter booking paid in cash (or a transfer checked on the spot).
     * @return array<string, mixed>
     */
    public static function recordCounterPayment(int $bookingId, int $staffId, string $method = 'cash'): array
    {
        $method = isset(Status::PAYMENT_METHOD[$method]) ? $method : 'cash';
        return DB::transaction(function () use ($bookingId, $staffId, $method) {
            $b = DB::one('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
            if (!$b) {
                throw new BookingException('ไม่พบการจองนี้');
            }
            self::assertTransition($b, 'confirmed');
            DB::run("UPDATE payments SET status = 'rejected', reject_reason = 'รับชำระที่หน้าร้านแทน', reviewed_by = ?, reviewed_at = NOW() WHERE booking_id = ? AND status = 'pending'", [$staffId, $bookingId]);
            DB::insert('payments', [
                'booking_id' => $bookingId, 'kind' => 'booking', 'method' => $method,
                'amount' => $b['total_amount'], 'status' => 'approved', 'reviewed_by' => $staffId,
                'reviewed_at' => date('Y-m-d H:i:s'), 'created_by' => $staffId,
            ]);
            return self::confirm($b, $staffId, 'รับชำระที่หน้าร้าน (' . Status::PAYMENT_METHOD[$method] . ') ' . money($b['total_amount']));
        });
    }

    /**
     * @param array<string, mixed> $b
     * @return array<string, mixed>
     */
    private static function confirm(array $b, int $staffId, string $logMessage): array
    {
        DB::update('bookings', ['status' => 'confirmed', 'hold_expires_at' => null, 'confirmed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $b['id']]);
        self::log((int) $b['id'], $staffId, 'confirmed', $logMessage);
        $receipt = Receipts::issueBooking((int) $b['id'], $staffId);
        Appointments::syncForBooking((int) $b['id'], $staffId);
        $full = self::find((int) $b['id']);
        $ships = self::ships($full);
        Notifier::user((int) $full['user_id'], 'booking_confirmed', 'ยืนยันการจอง ' . $full['booking_no'] . ' แล้ว',
            $ships ? 'ร้านจะส่งชุดออกภายใน ' . thai_date($full['ship_by_date']) . ' ให้ถึงคุณภายใน ' . thai_date($full['pickup_date'])
                : 'รับชุด ' . thai_date($full['pickup_date']) . ' เวลา ' . substr((string) $full['pickup_time'], 0, 5) . ' น. ที่ร้าน', '/bookings/' . $full['id'], null, [
                'subject' => 'ยืนยันการจอง ' . $full['booking_no'] . ' · ใบเสร็จ ' . $receipt['receipt_no'],
                'data' => self::mailData($full, 'การจองของคุณได้รับการยืนยันแล้ว', [
                    'ร้านได้รับเงินเรียบร้อย ใบเสร็จเลขที่ ' . $receipt['receipt_no'] . ' ดูและพิมพ์ได้ในหน้าการจอง',
                    $ships ? 'ร้านจะแพ็กชุดและแจ้งเลขพัสดุให้ทางอีเมลทันทีที่ส่งออก ได้รับแล้วกด “ได้รับชุดแล้ว” และถ่ายรูปสภาพชุดในหน้าการจอง'
                        : 'มารับชุดที่ร้านตามวันและเวลาด้านล่าง เราจะส่งอีเมลเตือนอีกครั้งก่อนวันรับ 1 วัน',
                ], ['ดูใบเสร็จ', absolute_url('/receipts/' . $receipt['id'])]),
            ]);
        if ($ships) {
            Notifier::staff('ship_new', 'ต้องส่งพัสดุ ' . $full['booking_no'] . ' ภายใน ' . thai_date($full['ship_by_date']),
                $full['costume_code'] . ' · ' . $full['ship_province'], '/admin/bookings/' . $full['id']);
        }
        return $full;
    }

    /**
     * Can the member cancel this booking themselves? Returns [bool, reason].
     * @param array<string, mixed> $b
     * @return array{bool, string}
     */
    public static function memberCanCancel(array $b): array
    {
        if (!in_array($b['status'], ['pending_payment', 'awaiting_verification', 'confirmed'], true)) {
            return [false, 'การจองนี้ยกเลิกไม่ได้แล้ว'];
        }
        if ($b['status'] === 'pending_payment') {
            return [true, ''];
        }
        // counted from the day the dress leaves the shop: a parcel goes out before the pickup day
        $deadline = add_days(($b['ship_by_date'] ?? null) ?: $b['pickup_date'], -Settings::int('cancel_before_days'));
        if (today() > $deadline) {
            return [false, 'ยกเลิกเองได้ถึงวันที่ ' . thai_date($deadline) . ' เท่านั้น ติดต่อร้านถ้าจำเป็นต้องยกเลิก'];
        }
        return [true, ''];
    }

    /** @return array<string, mixed> */
    public static function cancel(int $bookingId, int $actorId, string $reason, bool $byStaff = false, ?float $refund = null): array
    {
        return DB::transaction(function () use ($bookingId, $actorId, $reason, $byStaff, $refund) {
            $b = DB::one('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
            if (!$b) {
                throw new BookingException('ไม่พบการจองนี้');
            }
            self::assertTransition($b, 'cancelled');
            if (!$byStaff) {
                [$ok, $why] = self::memberCanCancel($b);
                if (!$ok) {
                    throw new BookingException($why);
                }
            }
            $paid = (float) DB::value("SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = ? AND kind = 'booking' AND status = 'approved'", [$bookingId]);
            $refundAmount = $byStaff && $refund !== null ? max(0.0, min($paid, $refund)) : $paid;
            DB::run("UPDATE payments SET status = 'rejected', reject_reason = 'ยกเลิกการจอง', reviewed_by = ?, reviewed_at = NOW() WHERE booking_id = ? AND status = 'pending'", [$actorId, $bookingId]);
            if ($refundAmount > 0) {
                DB::insert('payments', [
                    'booking_id' => $bookingId, 'kind' => 'refund', 'method' => 'transfer', 'amount' => $refundAmount,
                    'status' => 'approved', 'reviewed_by' => $actorId, 'reviewed_at' => date('Y-m-d H:i:s'), 'created_by' => $actorId,
                ]);
            }
            $reason = trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : ($byStaff ? 'ร้านยกเลิก' : 'ลูกค้ายกเลิกเอง');
            DB::update('bookings', [
                'status' => 'cancelled', 'cancel_reason' => $reason, 'cancelled_by' => $actorId,
                'cancelled_at' => date('Y-m-d H:i:s'), 'hold_expires_at' => null, 'refund_amount' => $refundAmount > 0 ? $refundAmount : null,
            ], 'id = :id', ['id' => $bookingId]);
            DB::run("UPDATE appointments SET status = 'cancelled' WHERE booking_id = ? AND status = 'scheduled'", [$bookingId]);
            self::log($bookingId, $actorId, 'cancelled', 'ยกเลิก: ' . $reason . ($refundAmount > 0 ? ' · คืนเงิน ' . money($refundAmount) : ''));
            $full = self::find($bookingId);
            $lines = ['เหตุผล: ' . $reason];
            if ($refundAmount > 0) {
                $lines[] = 'ร้านจะโอนเงินคืน ' . money($refundAmount) . ' ภายใน 3 วันทำการ';
            }
            Notifier::user((int) $full['user_id'], 'booking_cancelled', 'ยกเลิกการจอง ' . $full['booking_no'] . ' แล้ว', $reason, '/bookings/' . $bookingId, null, [
                'subject' => 'ยกเลิกการจอง ' . $full['booking_no'] . ' แล้ว',
                'data' => self::mailData($full, 'การจองนี้ถูกยกเลิกแล้ว', $lines),
            ]);
            if (!$byStaff) {
                Notifier::staff('booking_cancelled', 'ลูกค้ายกเลิก ' . $full['booking_no'], $full['costume_name'], '/admin/bookings/' . $bookingId);
            }
            return $full;
        });
    }

    /** @return array<string, mixed> */
    public static function markPickedUp(int $bookingId, int $staffId): array
    {
        return DB::transaction(function () use ($bookingId, $staffId) {
            $b = DB::one('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
            if (!$b) {
                throw new BookingException('ไม่พบการจองนี้');
            }
            self::assertTransition($b, 'picked_up');
            if ($b['status'] === 'confirmed' && self::ships($b)) {
                throw new BookingException('การจองนี้เลือกให้ร้านจัดส่ง ถ้าลูกค้ามารับเองให้กด “เปลี่ยนเป็นรับที่ร้าน” ก่อน');
            }
            if ($b['status'] === 'shipped') {
                throw new BookingException('ชุดอยู่ระหว่างขนส่ง ใช้ปุ่ม “พัสดุถึงลูกค้าแล้ว” แทน');
            }
            if (today() > $b['return_date']) {
                throw new BookingException('เลยวันคืนชุดของการจองนี้แล้ว ให้ยกเลิกหรือแก้ไขวันก่อน');
            }
            DB::update('bookings', ['status' => 'picked_up', 'picked_up_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $bookingId]);
            DB::run("UPDATE appointments SET status = 'done' WHERE booking_id = ? AND type = 'pickup' AND status = 'scheduled'", [$bookingId]);
            self::log($bookingId, $staffId, 'picked_up', 'ลูกค้ารับชุดแล้ว');
            $full = self::find($bookingId);
            Notifier::user((int) $full['user_id'], 'picked_up', 'รับชุดแล้ว คืนภายใน ' . thai_date($full['return_date']) . ' ' . substr((string) $full['return_time'], 0, 5) . ' น.',
                'คืนช้าคิดค่าปรับวันละ ' . money($full['late_fee_per_day']), '/bookings/' . $bookingId);
            return $full;
        });
    }

    /**
     * Preview the settlement without saving (used by the return screen).
     * @param array<string, mixed> $b
     * @return array{late_days:int, late_fee:float, damage_fee:float, fines:float, deposit:float, deposit_refund:float, extra_charge:float}
     */
    public static function previewReturn(array $b, string $returnDate, float $damageFee = 0.0): array
    {
        return Pricing::settle((float) $b['deposit_amount'], (float) $b['late_fee_per_day'], $b['return_date'], $returnDate,
            Inspection::cap($damageFee, $b['replacement_value'] ?? null));
    }

    /**
     * Close a rental: the dress is back, handed in at the counter or as a posted parcel that has arrived.
     * Late days run to the day it was handed in or, for a parcel, to the day the customer posted it.
     * Damage is never more than the dress is worth (costumes.replacement_value).
     *
     * @param array{items?: list<array<string, mixed>>, checks?: list<string>, missing?: list<string>, outcome?: string, note?: string} $inspection
     *        from Inspection::complete(); empty for a quick return without the checklist
     * @return array<string, mixed>
     */
    public static function markReturned(int $bookingId, int $staffId, ?string $returnDate = null, float $damageFee = 0.0, string $damageNote = '', array $inspection = []): array
    {
        $returnDate ??= today();
        return DB::transaction(function () use ($bookingId, $staffId, $returnDate, $damageFee, $damageNote, $inspection) {
            $b = DB::one('SELECT b.*, c.late_fee_per_day, c.replacement_value FROM bookings b JOIN costumes c ON c.id = b.costume_id WHERE b.id = ? FOR UPDATE', [$bookingId]);
            if (!$b) {
                throw new BookingException('ไม่พบการจองนี้');
            }
            self::assertTransition($b, 'returned');
            $parcel = null;
            if ($b['status'] === 'returning') {
                $parcel = Fulfilment::lastShipment($bookingId, 'back');
                if (!$parcel || !in_array($parcel['status'], ['received', 'lost'], true)) {
                    throw new BookingException('ยังไม่ได้รับพัสดุคืน กด “ได้รับพัสดุคืน” ก่อนตรวจสภาพ');
                }
                $lateDate = (string) $parcel['shipped_on'];
                $returnedAt = $parcel['arrived_at'] ?? date('Y-m-d H:i:s');
            } else {
                if ($returnDate < substr((string) $b['picked_up_at'], 0, 10) || $returnDate > today()) {
                    throw new BookingException('วันที่คืนต้องอยู่ระหว่างวันที่รับชุดถึงวันนี้');
                }
                $lateDate = $returnDate;
                $returnedAt = $returnDate === today() ? date('Y-m-d H:i:s') : $returnDate . ' ' . substr((string) $b['return_time'], 0, 5) . ':00';
            }
            if ($damageFee > 0 && trim($damageNote) === '') {
                throw new BookingException('ระบุรายละเอียดความเสียหายเมื่อคิดค่าเสียหาย');
            }
            $damageFee = Inspection::cap($damageFee, $b['replacement_value']);
            $s = Pricing::settle((float) $b['deposit_amount'], (float) $b['late_fee_per_day'], $b['return_date'], $lateDate, $damageFee);
            $record = $inspection ? [
                'checks' => $inspection['checks'] ?? [],
                'missing' => $inspection['missing'] ?? [],
                'outcome' => $inspection['outcome'] ?? 'ready',
                'note' => $inspection['note'] ?? '',
                'parcel' => $parcel ? $parcel['status'] : null,
            ] : null;
            DB::update('bookings', [
                'status' => 'returned',
                'returned_at' => $returnedAt,
                'late_days' => $s['late_days'],
                'late_fee' => $s['late_fee'],
                'damage_fee' => $s['damage_fee'],
                'damage_note' => trim($damageNote) !== '' ? mb_substr(trim($damageNote), 0, 500) : null,
                'deposit_refund' => $s['deposit_refund'],
                'extra_charge' => $s['extra_charge'],
                'inspected_at' => date('Y-m-d H:i:s'),
                'inspected_by' => $staffId,
                'inspection_json' => $record ? json_encode($record, JSON_UNESCAPED_UNICODE) : null,
            ], 'id = :id', ['id' => $bookingId]);
            foreach ($inspection['items'] ?? [] as $it) {
                DB::insert('damage_items', [
                    'booking_id' => $bookingId, 'damage_type_id' => $it['type_id'], 'label' => mb_substr((string) $it['label'], 0, 200),
                    'qty' => (int) $it['qty'], 'amount' => $it['amount'], 'note' => $it['note'] !== '' ? mb_substr((string) $it['note'], 0, 255) : null,
                    'created_by' => $staffId,
                ]);
            }
            $method = $parcel ? 'transfer' : 'cash';
            if ($s['extra_charge'] > 0) {
                DB::insert('payments', ['booking_id' => $bookingId, 'kind' => 'fine', 'method' => $method, 'amount' => $s['extra_charge'],
                    'status' => 'approved', 'reviewed_by' => $staffId, 'reviewed_at' => date('Y-m-d H:i:s'), 'created_by' => $staffId]);
            }
            if ($s['deposit_refund'] > 0) {
                DB::insert('payments', ['booking_id' => $bookingId, 'kind' => 'refund', 'method' => $method, 'amount' => $s['deposit_refund'],
                    'status' => 'approved', 'reviewed_by' => $staffId, 'reviewed_at' => date('Y-m-d H:i:s'), 'created_by' => $staffId]);
            }
            DB::run("UPDATE appointments SET status = 'done' WHERE booking_id = ? AND type = 'return' AND status = 'scheduled'", [$bookingId]);
            $outcome = $record['outcome'] ?? 'ready';
            if ($outcome !== 'ready') {
                DB::update('costumes', ['status' => $outcome], 'id = :id', ['id' => $b['costume_id']]);
                self::log($bookingId, $staffId, 'costume_status', 'ตรวจแล้วตั้งสถานะชุดเป็น “' . Status::label(Status::COSTUME, $outcome) . '”');
            }
            $how = $parcel ? ($parcel['status'] === 'lost' ? 'พัสดุคืนสูญหาย' : 'รับพัสดุคืนแล้ว (ฝากส่ง ' . thai_date($lateDate) . ')') : 'คืนชุดแล้ว';
            $msg = $how . ($s['late_days'] ? ' (ช้า ' . $s['late_days'] . ' วัน ปรับ ' . money($s['late_fee']) . ')' : ' ตรงเวลา')
                . ($s['damage_fee'] > 0 ? ' · ค่าเสียหาย ' . money($s['damage_fee']) : '')
                . ' · คืนมัดจำ ' . money($s['deposit_refund']);
            self::log($bookingId, $staffId, 'returned', $msg);
            $receipt = Receipts::issueSettlement($bookingId, $staffId);
            $full = self::find($bookingId);
            $lines = [$s['late_days'] ? 'คืนช้า ' . $s['late_days'] . ' วัน คิดค่าปรับ ' . money($s['late_fee']) : 'คืนตรงเวลา ขอบคุณที่ดูแลชุดอย่างดี'];
            if ($s['damage_fee'] > 0) {
                $lines[] = 'ค่าเสียหาย ' . money($s['damage_fee']) . ': ' . $damageNote;
                $lines[] = 'ถ้าไม่เห็นด้วยกับค่าเสียหาย แจ้งโต้แย้งพร้อมรูปได้ในหน้าการจองภายใน ' . Settings::int('dispute_days') . ' วัน';
            }
            $lines[] = 'เงินมัดจำที่ได้คืน ' . money($s['deposit_refund']) . ($s['extra_charge'] > 0 ? ' และชำระเพิ่ม ' . money($s['extra_charge']) : '')
                . ($parcel && $s['deposit_refund'] > 0 ? ' ร้านโอนเข้าบัญชีของคุณภายใน 3 วันทำการ' : '');
            Notifier::user((int) $full['user_id'], 'returned', 'คืนชุดเรียบร้อย การจอง ' . $full['booking_no'] . ' ปิดแล้ว', end($lines), '/bookings/' . $bookingId, null, [
                'subject' => 'สรุปการคืนชุด ' . $full['booking_no'] . ' · ' . $receipt['receipt_no'],
                'data' => self::mailData($full, 'คืนชุดเรียบร้อยแล้ว', $lines, ['ดูใบสรุปการคืนชุด', absolute_url('/receipts/' . $receipt['id'])]),
            ]);
            return $full;
        });
    }

    /** @param array<string, mixed> $b */
    private static function expireOne(array $b): void
    {
        DB::update('bookings', ['status' => 'expired', 'hold_expires_at' => null], 'id = :id AND status = :st', ['id' => $b['id'], 'st' => 'pending_payment']);
        self::log((int) $b['id'], null, 'expired', 'ระบบยกเลิกอัตโนมัติ เพราะไม่ได้ชำระเงินภายในเวลาที่กำหนด');
        $full = self::find((int) $b['id']);
        Notifier::user((int) $full['user_id'], 'booking_expired', 'การจอง ' . $full['booking_no'] . ' หมดเวลาชำระเงิน', 'ชุดถูกปล่อยให้คนอื่นจองแล้ว ถ้ายังต้องการ ลองจองใหม่อีกครั้ง',
            '/bookings/' . $full['id'], 'expired:' . $full['id'], [
                'subject' => 'การจอง ' . $full['booking_no'] . ' หมดเวลาชำระเงิน',
                'data' => self::mailData($full, 'หมดเวลาชำระเงิน', ['เราไม่ได้รับสลิปภายในเวลาที่กำหนด จึงปล่อยชุดให้คนอื่นจอง', 'ถ้ายังว่าง คุณจองใหม่ได้ทันที'],
                    ['ดูชุดนี้อีกครั้ง', absolute_url('/dress/' . $full['costume_code'])]),
            ]);
    }

    /** Expire every pending booking whose hold has run out. Returns how many. */
    public static function expireHolds(): int
    {
        $rows = DB::all("SELECT * FROM bookings WHERE status = 'pending_payment' AND hold_expires_at IS NOT NULL AND hold_expires_at <= ?", [date('Y-m-d H:i:s')]);
        foreach ($rows as $b) {
            DB::transaction(fn() => self::expireOne($b));
        }
        return count($rows);
    }

    /**
     * Staff edits dates or note of a booking that has not been picked up.
     * @return array<string, mixed>
     */
    public static function reschedule(int $bookingId, int $staffId, string $event, string $pickup, string $return, string $pickupTime, string $returnTime, ?string $staffNote): array
    {
        return DB::transaction(function () use ($bookingId, $staffId, $event, $pickup, $return, $pickupTime, $returnTime, $staffNote) {
            $b = DB::one('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
            if (!$b || !in_array($b['status'], ['pending_payment', 'awaiting_verification', 'confirmed'], true)) {
                throw new BookingException('แก้ไขได้เฉพาะการจองที่ยังไม่รับชุด');
            }
            $costume = DB::one('SELECT * FROM costumes WHERE id = ? FOR UPDATE', [$b['costume_id']]);
            $errors = self::validateDates($costume, $event, $pickup, $return, true);
            if ($errors) {
                throw new BookingException(reset($errors), $errors);
            }
            // same delivery choice and fees as booked; only the dates move
            $plan = Shipping::plan($pickup, $return, $b['delivery_method'], $b['return_method'], $b['ship_zone']);
            $errors = Shipping::check(['delivery' => $b['delivery_method'], 'return' => $b['return_method'], 'zone' => $b['ship_zone'],
                'ship_by' => $plan['ship_by']], true);
            unset($errors['delivery_method'], $errors['return_method']); // a service switched off later does not undo a paid booking
            if ($errors) {
                throw new BookingException(reset($errors), $errors);
            }
            if (!Availability::isFree((int) $costume['id'], $pickup, $return, $bookingId, $plan['out_days'], $plan['back_days'])) {
                throw new BookingException('ช่วงวันใหม่ชนกับการจองอื่นของชุดนี้');
            }
            $q = Pricing::quote($costume, $pickup, $return, null, (float) $b['delivery_fee'], (float) $b['return_fee']);
            $paid = (float) DB::value("SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = ? AND kind = 'booking' AND status = 'approved'", [$bookingId]);
            if ($paid > 0 && abs($q['total'] - (float) $b['total_amount']) > 0.001) {
                throw new BookingException('ยอดเงินจะเปลี่ยนเป็น ' . money($q['total']) . ' แต่ลูกค้าชำระแล้ว ' . money($paid) . ' เลือกช่วงวันที่ราคาเท่าเดิม หรือยกเลิกแล้วจองใหม่');
            }
            $valid = fn($t) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) === 1;
            DB::update('bookings', [
                'event_date' => $event, 'pickup_date' => $pickup, 'return_date' => $return,
                'pickup_time' => $valid($pickupTime) ? $pickupTime : $b['pickup_time'],
                'return_time' => $valid($returnTime) ? $returnTime : $b['return_time'],
                'block_from' => $plan['block_from'], 'block_until' => $plan['block_until'], 'ship_by_date' => $plan['ship_by'], 'rental_days' => $q['days'],
                'rent_amount' => $q['rent'], 'extra_amount' => $q['extra'], 'deposit_amount' => $q['deposit'], 'total_amount' => $q['total'],
                'staff_note' => $staffNote !== null && trim($staffNote) !== '' ? mb_substr(trim($staffNote), 0, 500) : null,
            ], 'id = :id', ['id' => $bookingId]);
            if ($b['status'] === 'confirmed') {
                Appointments::syncForBooking($bookingId, $staffId);
            }
            self::log($bookingId, $staffId, 'rescheduled', 'แก้ไขวัน: รับ ' . thai_date($pickup) . ' คืน ' . thai_date($return));
            $full = self::find($bookingId);
            Notifier::user((int) $full['user_id'], 'rescheduled', 'ร้านแก้ไขวันของการจอง ' . $full['booking_no'],
                'รับ ' . thai_date($pickup) . ' ' . substr((string) $full['pickup_time'], 0, 5) . ' น. · คืน ' . thai_date($return) . ' ' . substr((string) $full['return_time'], 0, 5) . ' น.', '/bookings/' . $bookingId);
            return $full;
        });
    }
}
