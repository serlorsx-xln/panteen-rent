<?php
declare(strict_types=1);

/**
 * Unit + service tests. They run against the separate test database (panteen_test),
 * which tests/run.php rebuilds from schema.sql + demo data before every run.
 */
use App\Core\DB;
use App\Core\Validator;
use App\Services\Appointments;
use App\Services\Availability;
use App\Services\BookingException;
use App\Services\BookingService as BS;
use App\Services\Catalog;
use App\Services\Notifier;
use App\Services\Pricing;
use App\Services\PromptPay;
use App\Services\Reminders;
use App\Services\Settings;

$today = today();
$staffId = (int) DB::value("SELECT id FROM users WHERE email = 'staff@panteen.test'");
$members = array_column(DB::all("SELECT id FROM users WHERE role = 'member' AND status = 'active' AND email_verified_at IS NOT NULL ORDER BY id"), 'id');
$members = array_map('intval', $members);
$newCostume = function (array $o = []): array {
    static $n = 0;
    $n++;
    $id = DB::insert('costumes', $o + [
        'code' => 'TS-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'category_id' => (int) DB::value('SELECT id FROM categories ORDER BY id LIMIT 1'),
        'name' => 'ชุดทดสอบ ' . $n, 'size_label' => 'M', 'rent_price' => 1000, 'extra_day_price' => 200, 'deposit' => 1500, 'late_fee_per_day' => 150, 'status' => 'active',
    ]);
    return DB::one('SELECT * FROM costumes WHERE id = ?', [$id]);
};
$book = fn(array $c, int $uid, string $p, string $e, string $r, string $channel = 'online') => BS::create([
    'user_id' => $uid, 'costume_id' => (int) $c['id'], 'event_date' => $e, 'pickup_date' => $p, 'return_date' => $r, 'channel' => $channel, 'actor_id' => $channel === 'counter' ? $staffId : $uid,
]);
$D = fn(int $n) => add_days($today, $n);

/* ------------------------------------------------------------------ */
T::group('ตัวช่วยวันที่และรูปแบบ (helpers)');
T::eq(days_between('2026-12-13', '2026-12-15'), 2, 'days_between นับส่วนต่างวัน');
T::eq(add_days('2026-12-31', 1), '2027-01-01', 'add_days ข้ามปี');
T::eq(add_days('2028-02-28', 1), '2028-02-29', 'add_days ปีอธิกสุรทิน');
T::eq(thai_date('2026-12-13'), '13 ธ.ค. 2569', 'thai_date เป็นปี พ.ศ.');
T::ok(str_contains(thai_date_full('2026-11-21'), 'เสาร์'), 'thai_date_full มีชื่อวัน (21 พ.ย. 2569 = วันเสาร์)');
T::eq(money(1690), '1,690 บาท', 'money ไม่มีทศนิยมเมื่อเป็นจำนวนเต็ม');
T::eq(phone_fmt('0812345678'), '081-234-5678', 'phone_fmt มือถือ');
T::eq(phone_fmt('021234567'), '02-123-4567', 'phone_fmt เบอร์บ้าน กทม.');
T::eq(initials(['first_name' => 'เฟิร์น', 'last_name' => 'ศรีสุข', 'nickname' => null]), 'เฟ', 'initials เก็บพยัญชนะหลังสระหน้า (เ) ไว้ด้วย');
T::eq(initials(['first_name' => 'ปวีณา', 'last_name' => 'ทองดี', 'nickname' => 'มิ้นท์']), 'ม', 'initials ใช้ชื่อเล่นก่อน');
T::eq(initials(['first_name' => 'anna', 'last_name' => 'x', 'nickname' => null]), 'A', 'initials ภาษาอังกฤษเป็นตัวใหญ่');
T::eq(e('<b>"x"</b>'), '&lt;b&gt;&quot;x&quot;&lt;/b&gt;', 'e() escape HTML');

/* ------------------------------------------------------------------ */
T::group('ราคาและการคืนเงินมัดจำ (Pricing)');
$c = ['rent_price' => 1000, 'extra_day_price' => 200, 'deposit' => 1500, 'late_fee_per_day' => 150];
T::eq(Pricing::rentalDays('2026-12-12', '2026-12-14'), 3, 'นับวันเช่าแบบรวมวันรับและวันคืน (12–14 = 3 วัน)');
$q = Pricing::quote($c, '2026-12-12', '2026-12-14', 3);
T::eq([$q['days'], $q['extra_days'], $q['rent'], $q['extra'], $q['total']], [3, 0, 1000.0, 0.0, 2500.0], 'เช่า 3 วัน = ค่าเช่า + มัดจำ ไม่มีวันเพิ่ม');
$q = Pricing::quote($c, '2026-12-12', '2026-12-16', 3);
T::eq([$q['days'], $q['extra_days'], $q['extra'], $q['subtotal'], $q['total']], [5, 2, 400.0, 1400.0, 2900.0], 'เช่า 5 วัน คิดวันเพิ่ม 2 วัน');
$q = Pricing::quote($c, '2026-12-12', '2026-12-12', 3);
T::eq([$q['days'], $q['extra_days'], $q['total']], [1, 0, 2500.0], 'เช่าวันเดียวยังคิดราคาแพ็กเกจเต็ม');
$s = Pricing::settle(1500, 150, '2026-12-14', '2026-12-14');
T::eq([$s['late_days'], $s['deposit_refund'], $s['extra_charge']], [0, 1500.0, 0.0], 'คืนตรงเวลา คืนมัดจำเต็ม');
$s = Pricing::settle(1500, 150, '2026-12-14', '2026-12-13');
T::eq([$s['late_days'], $s['late_fee']], [0, 0.0], 'คืนก่อนกำหนด ไม่มีค่าปรับติดลบ');
$s = Pricing::settle(1500, 150, '2026-12-14', '2026-12-17', 200);
T::eq([$s['late_days'], $s['late_fee'], $s['fines'], $s['deposit_refund'], $s['extra_charge']], [3, 450.0, 650.0, 850.0, 0.0], 'คืนช้า 3 วัน + ค่าเสียหาย หักจากมัดจำ');
$s = Pricing::settle(500, 150, '2026-12-14', '2026-12-20', 300);
T::eq([$s['fines'], $s['deposit_refund'], $s['extra_charge']], [1200.0, 0.0, 700.0], 'ค่าปรับเกินมัดจำ → คืนมัดจำ 0 และเก็บเพิ่ม');
$s = Pricing::settle(1500, 150, '2026-12-14', '2026-12-14', -50);
T::eq($s['damage_fee'], 0.0, 'ค่าเสียหายติดลบถูกปัดเป็น 0');
T::eq(Pricing::defaultWindow(add_days(today(), 20)), [add_days(today(), 19), add_days(today(), 21)], 'ช่วงเช่าตั้งต้น = ก่อนงาน 1 วัน ถึงหลังงาน 1 วัน');
T::eq(Pricing::defaultWindow(today()), [today(), add_days(today(), 1)], 'งานวันนี้ → รับชุดวันนี้ (ไม่ย้อนไปเมื่อวาน)');

/* ------------------------------------------------------------------ */
T::group('QR พร้อมเพย์ (EMVCo)');
T::eq(PromptPay::crc16('123456789'), '29B1', 'CRC16-CCITT ของ "123456789" = 29B1 (ค่ามาตรฐาน)');
$p = PromptPay::payload('0812345678', 1690);
T::ok(str_starts_with($p, '000201010212'), 'เริ่มด้วย payload format + dynamic QR (มียอด)');
T::ok(str_contains($p, '0016A000000677010111') && str_contains($p, '01130066812345678'), 'AID พร้อมเพย์ + เบอร์มือถือแปลงเป็น 0066…');
T::ok(str_contains($p, '5303764') && str_contains($p, '54071690.00') && str_contains($p, '5802TH'), 'สกุลเงิน 764 (บาท), ยอด 1690.00, ประเทศ TH');
T::eq(substr($p, -4), PromptPay::crc16(substr($p, 0, -4)), 'ท้าย payload คือ CRC ที่ถูกต้อง');
$p2 = PromptPay::payload('1234567890123');
T::ok(str_contains($p2, '0213' . '1234567890123') && str_starts_with($p2, '000201010211'), 'เลขประจำตัว 13 หลัก ใช้ tag 02 และเป็น static QR เมื่อไม่ระบุยอด');
T::throws(fn() => PromptPay::payload('12345'), 'เบอร์/เลขที่ไม่ถูกต้องถูกปฏิเสธ', \InvalidArgumentException::class);

/* ------------------------------------------------------------------ */
T::group('ตรวจข้อมูลฟอร์ม (Validator)');
$v = Validator::make(['email' => 'not-an-email', 'phone' => '12', 'n' => '99', 'size' => 'XXL', 'hex' => 'red', 'slug' => 'Bad Slug', 'd' => '2026-02-30'],
    ['email' => 'required|email', 'phone' => 'required|phone', 'n' => 'required|between:1,10', 'size' => 'required|in:S,M,L', 'hex' => 'required|hex', 'slug' => 'required|slug', 'd' => 'required|date', 'missing' => 'required']);
foreach (['email', 'phone', 'n', 'size', 'hex', 'slug', 'd', 'missing'] as $f) {
    T::ok(isset($v->errors()[$f]), "ปฏิเสธค่าไม่ถูกต้อง: $f", $v->errors()[$f] ?? '');
}
$v = Validator::make(['email' => 'a@b.co', 'phone' => '081-234-5678', 'n' => '5', 'size' => 'M', 'hex' => '#A1B2C3', 'slug' => 'good-slug', 'd' => '2026-02-28', 'opt' => ''],
    ['email' => 'required|email', 'phone' => 'required|phone', 'n' => 'required|between:1,10', 'size' => 'required|in:S,M,L', 'hex' => 'required|hex', 'slug' => 'required|slug', 'd' => 'required|date', 'opt' => 'nullable|email']);
T::ok(!$v->fails(), 'ค่าที่ถูกต้องผ่านทั้งหมด', json_encode($v->errors(), JSON_UNESCAPED_UNICODE));
$v = Validator::make(['email' => 'member@panteen.test'], ['email' => 'required|email|unique:users,email']);
T::ok($v->fails(), 'unique: อีเมลซ้ำถูกปฏิเสธ');
$v = Validator::make(['password' => 'short', 'password_confirmation' => 'x'], ['password' => 'required|password', 'password_confirmation' => 'required|same:password']);
T::ok(isset($v->errors()['password'], $v->errors()['password_confirmation']), 'รหัสผ่านสั้นและยืนยันไม่ตรงถูกปฏิเสธ');

/* ------------------------------------------------------------------ */
T::group('สถานะการจอง (state machine)');
$allowed = [
    ['pending_payment', 'awaiting_verification', true], ['pending_payment', 'confirmed', true], ['pending_payment', 'expired', true], ['pending_payment', 'cancelled', true],
    ['awaiting_verification', 'confirmed', true], ['awaiting_verification', 'pending_payment', true], ['confirmed', 'picked_up', true], ['picked_up', 'returned', true],
    ['pending_payment', 'picked_up', false], ['confirmed', 'returned', false], ['returned', 'picked_up', false], ['cancelled', 'confirmed', false], ['expired', 'confirmed', false], ['picked_up', 'cancelled', false],
];
foreach ($allowed as [$from, $to, $exp]) {
    T::eq(BS::canTransition($from, $to), $exp, "$from → $to " . ($exp ? 'ได้' : 'ไม่ได้'));
}

/* ------------------------------------------------------------------ */
T::group('ตรวจวันที่จอง (validateDates)');
$C = $newCostume();
$err = BS::validateDates($C, $D(30), $D(31), $D(29));
T::ok(isset($err['return_date']), 'วันคืนก่อนวันรับ ถูกปฏิเสธ');
$err = BS::validateDates($C, $D(40), $D(30), $D(32));
T::ok(isset($err['event_date']), 'วันงานอยู่นอกช่วงเช่า ถูกปฏิเสธ');
$err = BS::validateDates($C, $D(31), $D(30), $D(38));
T::ok(isset($err['return_date']), 'เช่าเกิน ' . Settings::int('max_days') . ' วัน ถูกปฏิเสธ');
$err = BS::validateDates($C, $D(0), $D(-1), $D(1));
T::ok(isset($err['pickup_date']), 'วันรับย้อนหลัง ถูกปฏิเสธ');
$err = BS::validateDates($C, $D(400), $D(399), $D(401));
T::ok(isset($err['event_date']), 'จองไกลเกินกำหนด ถูกปฏิเสธ');
$err = BS::validateDates($C, '2026-02-30', $D(1), $D(2));
T::ok(isset($err['event_date']), 'วันที่ไม่มีจริง (30 ก.พ.) ถูกปฏิเสธ');
$err = BS::validateDates(['status' => 'maintenance'] + $C, $D(31), $D(30), $D(32));
T::ok(isset($err['costume']), 'ชุดที่ซ่อมอยู่จองไม่ได้');
T::eq(BS::validateDates($C, $D(31), $D(30), $D(32)), [], 'ช่วงปกติผ่าน');

/* ------------------------------------------------------------------ */
T::group('ชุดว่าง/ไม่ว่าง (Availability + วันซัก)');
$clean = Settings::int('cleaning_days');
$A = $book($C, $members[0], $D(50), $D(51), $D(52));
T::eq($A['block_until'], $D(52 + $clean), 'ล็อกชุดถึงวันคืน + วันซัก');
T::ok(!Availability::isFree((int) $C['id'], $D(51), $D(53)), 'ช่วงที่ทับกัน ไม่ว่าง');
T::ok(!Availability::isFree((int) $C['id'], $D(52 + $clean), $D(54 + $clean)), 'รับในวันซักของคนก่อน ไม่ว่าง');
T::ok(Availability::isFree((int) $C['id'], $D(53 + $clean), $D(55 + $clean)), 'รับหลังวันซักเสร็จ ว่าง');
T::ok(!Availability::isFree((int) $C['id'], $D(49 - $clean), $D(50 - $clean)), 'คืน+ซักแล้วชนวันรับของคนถัดไป ไม่ว่าง');
T::ok(Availability::isFree((int) $C['id'], $D(46 - $clean), $D(49 - $clean)), 'คืน+ซักเสร็จก่อนวันรับของคนถัดไป ว่าง');
T::ok(Availability::isFree((int) $C['id'], $D(50), $D(52), (int) $A['id']), 'ไม่นับการจองของตัวเองตอนเลื่อนวัน');
$msg = T::throws(fn() => $book($C, $members[1], $D(51), $D(52), $D(53)), 'จองซ้ำช่วงเดียวกันถูกปฏิเสธ', BookingException::class);
T::ok(str_contains($msg, 'ไม่ว่าง'), 'ข้อความบอกว่าชุดไม่ว่าง', $msg);
DB::update('bookings', ['hold_expires_at' => date('Y-m-d H:i:s', time() - 60)], 'id = :id', ['id' => $A['id']]);
T::ok(Availability::isFree((int) $C['id'], $D(50), $D(52)), 'การจองที่หมดเวลาชำระ ไม่บล็อกชุดอีกต่อไป');
T::ok(BS::expireHolds() >= 1, 'expireHolds ปิดการจองที่หมดเวลา');
T::eq(BS::find((int) $A['id'])['status'], 'expired', 'สถานะเปลี่ยนเป็น “หมดเวลาชำระ”');
T::throws(fn() => BS::submitSlip((int) $A['id'], $members[0], 'x.jpg'), 'ส่งสลิปให้การจองที่หมดเวลาแล้วไม่ได้', BookingException::class);
// Calendar and the availability verdict must agree on every single day.
$CC = $newCostume();
$book($CC, $members[0], $D(40), $D(41), $D(42));
$mismatch = [];
foreach ([0, 1, 2] as $k) {
    $ym = date('Y-m', strtotime("first day of +$k month"));
    foreach (Availability::month((int) $CC['id'], (int) substr($ym, 0, 4), (int) substr($ym, 5)) as $day => $state) {
        if ($state === 'free' || $state === 'busy') {
            [$wp, $wr] = Pricing::defaultWindow($day);
            if (($state === 'free') !== Availability::isFree((int) $CC['id'], $wp, $wr)) {
                $mismatch[] = $day;
            }
        }
    }
}
T::eq($mismatch, [], 'ปฏิทิน “ว่าง” ตรงกับผลตรวจจริงทุกวัน (3 เดือน)');
$cal = Availability::month((int) $CC['id'], (int) substr($D(43), 0, 4), (int) substr($D(43), 5, 2));
T::eq($cal[$D(43)] ?? 'busy', 'busy', 'วันถัดจากวันซักชุดยังจองเป็นวันงานไม่ได้ (ต้องรับชุดก่อน 1 วัน)');

/* ------------------------------------------------------------------ */
T::group('ขั้นตอนเช่าครบวงจร: จอง → สลิป → อนุมัติ → รับ → คืนช้า');
$C2 = $newCostume();
$uid = $members[2];
$b = $book($C2, $uid, $D(10), $D(11), $D(12));
T::eq([$b['status'], (float) $b['total_amount'], (int) $b['rental_days']], ['pending_payment', 2500.0, 3], 'สร้างการจอง รอชำระ 2,500 บาท 3 วัน');
T::ok($b['hold_expires_at'] > date('Y-m-d H:i:s'), 'มีเวลาล็อกชุดรอชำระ');
T::ok((int) DB::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'booking_created'", [$uid]) >= 1, 'แจ้งเตือนลูกค้าเมื่อจอง');
T::ok((int) DB::value("SELECT COUNT(*) FROM mail_outbox WHERE to_email = (SELECT email FROM users WHERE id = ?) AND subject LIKE ?", [$uid, '%' . $b['booking_no'] . '%']) >= 1, 'ส่งอีเมลยืนยันการจอง');
$b = BS::submitSlip((int) $b['id'], $uid, 'test-slip.jpg');
T::eq($b['status'], 'awaiting_verification', 'แนบสลิปแล้ว รอร้านตรวจ');
$pid = (int) DB::value("SELECT id FROM payments WHERE booking_id = ? AND status = 'pending'", [$b['id']]);
T::throws(fn() => BS::rejectPayment($pid, $staffId, '  '), 'ปฏิเสธสลิปต้องมีเหตุผล', BookingException::class);
$b = BS::rejectPayment($pid, $staffId, 'ยอดไม่ตรง');
T::eq($b['status'], 'pending_payment', 'สลิปไม่ผ่าน → กลับไปรอชำระ (ล็อกชุดต่อ)');
$b = BS::submitSlip((int) $b['id'], $uid, 'test-slip-2.jpg');
$pid = (int) DB::value("SELECT id FROM payments WHERE booking_id = ? AND status = 'pending'", [$b['id']]);
$b = BS::approvePayment($pid, $staffId);
T::eq($b['status'], 'confirmed', 'อนุมัติสลิป → ยืนยันการจอง');
T::throws(fn() => BS::approvePayment($pid, $staffId), 'อนุมัติสลิปเดิมซ้ำไม่ได้', BookingException::class);
$rc = DB::one("SELECT * FROM receipts WHERE booking_id = ? AND kind = 'booking'", [$b['id']]);
T::ok($rc && (float) $rc['amount'] === 2500.0, 'ออกใบเสร็จค่าเช่า+มัดจำ 2,500 บาท', json_encode($rc));
$items = json_decode((string) $rc['items_json'], true)['items'] ?? [];
T::eq(array_sum(array_column($items, 'amount')), 2500, 'รายการในใบเสร็จรวมได้เท่ายอด');
T::throws(fn() => BS::markReturned((int) $b['id'], $staffId), 'คืนชุดก่อนรับไม่ได้', BookingException::class);
$b = BS::markPickedUp((int) $b['id'], $staffId);
T::eq($b['status'], 'picked_up', 'บันทึกรับชุด');
T::throws(fn() => BS::cancel((int) $b['id'], $uid, 'x'), 'ยกเลิกหลังรับชุดแล้วไม่ได้', BookingException::class);
// Pretend the rental happened in the past and the customer returns 2 days late with damage.
DB::update('bookings', ['pickup_date' => $D(-5), 'event_date' => $D(-4), 'return_date' => $D(-3), 'block_until' => $D(-3 + $clean), 'picked_up_at' => $D(-5) . ' 13:00:00'], 'id = :id', ['id' => $b['id']]);
T::throws(fn() => BS::markReturned((int) $b['id'], $staffId, $D(-1), 300, ''), 'คิดค่าเสียหายต้องระบุรายละเอียด', BookingException::class);
T::throws(fn() => BS::markReturned((int) $b['id'], $staffId, $D(1)), 'วันที่คืนในอนาคตไม่ได้', BookingException::class);
$b = BS::markReturned((int) $b['id'], $staffId, $D(-1), 300, 'ซิปหลุด');
T::eq([$b['status'], (int) $b['late_days'], (float) $b['late_fee'], (float) $b['damage_fee'], (float) $b['deposit_refund'], (float) $b['extra_charge']],
    ['returned', 2, 300.0, 300.0, 900.0, 0.0], 'คืนช้า 2 วัน (300) + เสียหาย 300 → คืนมัดจำ 900');
T::ok((bool) DB::value("SELECT 1 FROM receipts WHERE booking_id = ? AND kind = 'settlement'", [$b['id']]), 'ออกใบสรุปการคืนชุด');
T::eq(substr((string) $b['returned_at'], 0, 10), $D(-1), 'บันทึกวันเวลาคืนจริงถูกต้อง');
T::throws(fn() => BS::markReturned((int) $b['id'], $staffId), 'คืนซ้ำไม่ได้', BookingException::class);
$events = array_column(DB::all('SELECT type FROM booking_events WHERE booking_id = ? ORDER BY id', [$b['id']]), 'type');
T::ok(count($events) >= 6, 'มีประวัติทุกขั้นตอน', implode(',', $events));

/* ------------------------------------------------------------------ */
T::group('จองหน้าร้าน รับเงินสด และเลื่อนวัน');
$C3 = $newCostume(['deposit' => 0, 'late_fee_per_day' => 100]);
$b = $book($C3, $members[3], $D(0), $D(1), $D(2), 'counter');
T::eq([$b['channel'], (int) $b['created_by']], ['counter', $staffId], 'การจองหน้าร้านบันทึกผู้สร้างเป็นพนักงาน');
$b = BS::recordCounterPayment((int) $b['id'], $staffId, 'cash');
T::eq($b['status'], 'confirmed', 'รับเงินสดแล้วยืนยันทันที');
T::eq((string) DB::value('SELECT method FROM payments WHERE booking_id = ?', [$b['id']]), 'cash', 'บันทึกช่องทางเงินสด');
$other = $book($C3, $members[4], $D(20), $D(21), $D(22));
T::throws(fn() => BS::reschedule((int) $b['id'], $staffId, $D(21), $D(20), $D(22), '13:00', '13:00', null), 'เลื่อนไปทับการจองอื่นไม่ได้', BookingException::class);
$b = BS::reschedule((int) $b['id'], $staffId, $D(5), $D(4), $D(6), '11:00', '15:00', 'ลูกค้าขอเลื่อน');
T::eq([$b['pickup_date'], $b['return_date'], substr((string) $b['pickup_time'], 0, 5), $b['block_until']], [$D(4), $D(6), '11:00', $D(6 + $clean)], 'เลื่อนวันสำเร็จ คำนวณวันล็อกใหม่');

/* ------------------------------------------------------------------ */
T::group('ยกเลิกการจอง');
$C4 = $newCostume();
$b = $book($C4, $members[5], $D(30), $D(31), $D(32));
T::eq(BS::memberCanCancel($b)[0], true, 'รอชำระอยู่ ยกเลิกเองได้');
$b = BS::recordCounterPayment((int) $b['id'], $staffId, 'transfer');
T::eq(BS::memberCanCancel($b)[0], true, 'ยืนยันแล้วแต่ก่อนกำหนด ' . Settings::int('cancel_before_days') . ' วัน ยกเลิกได้');
T::eq(BS::memberCanCancel(['pickup_date' => $D(1)] + $b)[0], false, 'ใกล้วันรับเกินไป ลูกค้ายกเลิกเองไม่ได้');
$b = BS::cancel((int) $b['id'], $staffId, 'ลูกค้าโทรมายกเลิก', true, 2500);
T::eq([$b['status'], (float) $b['refund_amount']], ['cancelled', 2500.0], 'พนักงานยกเลิกและบันทึกยอดคืนเงิน');
T::ok(Availability::isFree((int) $C4['id'], $D(31), $D(32)), 'ยกเลิกแล้ว ชุดว่างทันที');

/* ------------------------------------------------------------------ */
T::group('จำกัดการจองค้างชำระ');
$C5 = $newCostume();
$u = $members[6];
DB::run("UPDATE bookings SET status = 'cancelled' WHERE user_id = ? AND status = 'pending_payment'", [$u]);
for ($i = 0; $i < 3; $i++) {
    $book($C5, $u, $D(60 + $i * 10), $D(61 + $i * 10), $D(62 + $i * 10));
}
T::throws(fn() => $book($C5, $u, $D(100), $D(101), $D(102)), 'รอชำระครบ 3 รายการแล้ว จองเพิ่มไม่ได้', BookingException::class);
T::ok((bool) $book($C5, $u, $D(110), $D(111), $D(112), 'counter'), 'แต่พนักงานจองหน้าร้านให้ได้');

/* ------------------------------------------------------------------ */
T::group('จองพร้อมกันหลายคน (race condition)');
$C6 = $newCostume();
$at = microtime(true) + 1.2;
$procs = [];
$outs = [];
foreach (array_slice($members, 0, 6) as $m) {
    $cmd = [PHP_BINARY, __DIR__ . '/race.php', (string) $C6['id'], (string) $m, $D(70), $D(71), $D(72), (string) $at];
    $procs[] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['PANTEEN_DB_HOST' => (string) getenv('PANTEEN_DB_HOST'), 'PANTEEN_DB_PORT' => (string) getenv('PANTEEN_DB_PORT'), 'PANTEEN_DB_NAME' => getenv('PANTEEN_DB_NAME'), 'PANTEEN_MAIL_DRIVER' => 'log', 'PATH' => getenv('PATH')]);
    $outs[] = $pipes;
}
$results = [];
foreach ($outs as $i => $pp) {
    $results[] = trim(stream_get_contents($pp[1]) . stream_get_contents($pp[2]));
    proc_close($procs[$i]);
}
$okCount = count(array_filter($results, fn($r) => str_starts_with($r, 'OK')));
T::eq($okCount, 1, '6 คนกดจองชุดเดียวกันพร้อมกัน สำเร็จได้คนเดียว');
T::eq(count(array_filter($results, fn($r) => $r === 'BUSY')), 5, 'อีก 5 คนได้ข้อความว่าชุดไม่ว่าง', implode(' | ', $results));
T::eq((int) DB::value("SELECT COUNT(*) FROM bookings WHERE costume_id = ? AND status <> 'cancelled'", [$C6['id']]), 1, 'ฐานข้อมูลมีการจองเดียว');

/* ------------------------------------------------------------------ */
T::group('แจ้งเตือนอัตโนมัติ (Reminders) ไม่ส่งซ้ำ');
$C7 = $newCostume();
$b = $book($C7, $members[7], $D(1), $D(2), $D(3));
BS::recordCounterPayment((int) $b['id'], $staffId, 'cash');
$r1 = Reminders::run();
$n1 = (int) DB::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'remind_pickup'", [$members[7]]);
$r2 = Reminders::run();
$n2 = (int) DB::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'remind_pickup'", [$members[7]]);
T::ok($r1['pickup'] >= 1 && $n1 === 1, 'เตือนรับชุดพรุ่งนี้ 1 ครั้ง', json_encode($r1));
T::eq([$r2['pickup'], $n2], [0, 1], 'รันซ้ำไม่ส่งเตือนซ้ำ');
T::ok(Notifier::user($members[7], 'x', 'a', null, null, 'same-key') && !Notifier::user($members[7], 'x', 'a', null, null, 'same-key'), 'dedupe key กันแจ้งเตือนซ้ำ');

/* ------------------------------------------------------------------ */
T::group('นัดลองชุด');
$day = $D(3);
$slots = Appointments::fittingSlots($day);
T::ok(count($slots) >= 8, 'มีช่วงเวลานัดตามเวลาเปิดร้าน', (string) count($slots));
$t = $slots[2]['time'];
$cap = $slots[2]['left'];
for ($i = 0; $i < $cap; $i++) {
    Appointments::requestFitting($members[$i], $day, $t, 'ทดสอบ');
}
T::eq(array_column(Appointments::fittingSlots($day), 'left', 'time')[$t], 0, 'ช่วงเวลาเต็มเมื่อนัดครบจำนวน');
T::throws(fn() => Appointments::requestFitting($members[8] ?? $members[0], $day, $t, null), 'นัดเกินจำนวนในช่วงเดียวกันไม่ได้', BookingException::class);
T::throws(fn() => Appointments::requestFitting($members[0], $D(-1), '11:00', null), 'นัดย้อนหลังไม่ได้', BookingException::class);
T::throws(fn() => Appointments::requestFitting($members[0], $day, '03:00', null), 'นัดนอกเวลาเปิดร้านไม่ได้', BookingException::class);

T::group('รายการชุด: ตัวกรอง ลิงก์ และการเรียง (Catalog)');
(function () use ($D, $newCostume, $book, $members) {
    $soon = $D(140);
    $f = Catalog::filters(['date' => $soon, 'size' => 'm', 'free' => '1', 'sort' => 'price_asc', 'max' => '1500', 'q' => '  ชุดราตรี  ', 'category' => 'Even<b>ing', 'page' => '-3']);
    T::eq($f['date'], $soon, 'รับวันงานที่ยังมาไม่ถึง');
    T::eq($f['size'], 'M', 'ไซส์ปรับเป็นตัวพิมพ์ใหญ่');
    T::ok($f['free'] === true, '“เฉพาะชุดที่ว่าง” ใช้ได้เมื่อมีวันงาน');
    T::eq($f['q'], 'ชุดราตรี', 'คำค้นถูกตัดช่องว่างหัวท้าย');
    T::eq($f['page'], 1, 'เลขหน้าติดลบกลับเป็นหน้า 1');
    T::ok(!preg_match('/[^a-z0-9-]/', $f['category']), 'ชื่อหมวดเหลือเฉพาะอักขระของ slug', $f['category']);
    $past = Catalog::filters(['date' => $D(-1), 'free' => '1']);
    T::ok($past['date'] === null && $past['free'] === false, 'วันงานย้อนหลังถูกทิ้ง และ “เฉพาะชุดที่ว่าง” ไม่มีผลเมื่อไม่มีวันงาน');
    $bad = Catalog::filters(['date' => '2026-13-45', 'size' => 'XXXL', 'sort' => 'rent_price; DROP TABLE costumes', 'max' => 'abc', 'q' => ['x'], 'color' => ['y']]);
    T::ok($bad['date'] === null && $bad['size'] === '' && $bad['sort'] === 'new' && $bad['max'] === 0 && $bad['q'] === '' && $bad['color'] === '', 'ค่าที่ไม่ถูกต้องทุกช่องถูกทิ้งและกลับเป็นค่าเริ่มต้น');

    $query = function (string $u): array { parse_str((string) parse_url($u, PHP_URL_QUERY), $q); return $q; };
    $q = $query(Catalog::listUrl('/dresses', $f, ['size' => null]));
    T::ok(($q['date'] ?? '') === $soon && !isset($q['size']) && ($q['free'] ?? '') === '1' && ($q['sort'] ?? '') === 'price_asc' && ($q['max'] ?? '') === '1500',
        'ลิงก์เอาตัวกรองออกทีละอย่าง ยังพาวันงานและตัวกรองที่เหลือไปด้วย', json_encode($q));
    $q = $query(Catalog::listUrl('/dresses', $f, ['date' => '', 'free' => null]));
    T::ok(array_key_exists('date', $q) && $q['date'] === '' && !isset($q['free']) && ($q['size'] ?? '') === 'M', 'ลิงก์ล้างวันงานส่ง date ว่าง เพื่อให้ร้านลืมวันที่จำไว้', json_encode($q));
    T::ok(!isset($query(Catalog::listUrl('/dresses', ['free' => true, 'size' => 'S']))['free']), '“เฉพาะชุดที่ว่าง” ไม่ถูกส่งต่อเมื่อไม่มีวันงาน');
    T::ok(!str_contains(Catalog::listUrl('/dresses', Catalog::filters([])), '?'), 'ไม่มีตัวกรองก็ไม่มี query string');
    T::ok(!isset($query(Catalog::listUrl('/dresses', Catalog::filters(['sort' => 'new'])))['sort']), 'การเรียงแบบเริ่มต้นไม่ถูกเขียนลงที่อยู่');
    T::eq($query(Catalog::listUrl('/c/evening', $f, ['page' => 3]))['page'] ?? '', '3', 'ลิงก์หน้าถัดไประบุเลขหน้า');

    $all = Catalog::search(Catalog::filters([]), null, 500);
    $asc = Catalog::search(Catalog::filters(['sort' => 'price_asc']), null, 500);
    $desc = Catalog::search(Catalog::filters(['sort' => 'price_desc']), null, 500);
    $price = fn(array $r) => array_map(fn($x) => (float) $x['rent_price'], $r['items']);
    $up = $price($asc); sort($up);
    $down = $price($desc); rsort($down);
    T::ok(count($asc['items']) > 20 && $price($asc) === $up, 'เรียงค่าเช่าจากน้อยไปมาก');
    T::ok($price($desc) === $down, 'เรียงค่าเช่าจากมากไปน้อย');
    $codes = function (array $r): array { $c = array_column($r['items'], 'code'); sort($c); return $c; };
    T::ok($codes($all) === $codes($asc) && $codes($all) === $codes($desc), 'เปลี่ยนการเรียงแล้วยังเป็นชุดเดิมทุกชุด');
    T::eq($all['total'], count($all['items']), 'จำนวนรวมตรงกับรายการที่ได้');
    T::ok($all['free_total'] === null, 'ยังไม่เลือกวันงาน ไม่มีตัวเลขชุดว่าง');
    $p1 = Catalog::search(Catalog::filters([]), null, 10);
    $p2 = Catalog::search(Catalog::filters(['page' => 2]), null, 10);
    T::ok(count($p1['items']) === 10 && $p1['pages'] === (int) ceil($all['total'] / 10) && !array_intersect(array_column($p1['items'], 'code'), array_column($p2['items'], 'code')), 'แบ่งหน้าไม่ซ้ำชุดกัน');
    foreach (['M', 'FREE'] as $sz) {
        $r = Catalog::search(Catalog::filters(['size' => $sz]), null, 500);
        T::ok($r['total'] > 0 && !array_filter($r['items'], fn($x) => $x['size_label'] !== $sz), "กรองไซส์ $sz ได้เฉพาะไซส์นั้น ({$r['total']} ชุด)");
    }
    $cap = Catalog::search(Catalog::filters(['max' => 900]), null, 500);
    T::ok($cap['total'] > 0 && max($price($cap)) <= 900, 'กรองค่าเช่าไม่เกิน 900 บาท');
    T::eq(Catalog::search(Catalog::filters(['q' => '100%_ไม่มีชุดนี้']), null, 500)['total'], 0, 'คำค้นที่มี % และ _ ไม่ถูกตีความเป็นอักขระแทน');

    // a dress taken on the event day: still listed (dimmed) until "only the free ones" is switched on
    $c = $newCostume(['name' => 'ชุดทดสอบรายการ']);
    $day = $D(141);
    $before = Catalog::search(Catalog::filters(['q' => $c['code'], 'date' => $day]), null, 500);
    T::ok($before['total'] === 1 && (int) $before['items'][0]['busy'] === 0 && $before['free_total'] === 1, 'ชุดที่ยังไม่มีใครจองวันงานนั้น แสดงว่าว่าง');
    $book($c, $members[0], $D(140), $day, $D(142), 'counter');
    $after = Catalog::search(Catalog::filters(['q' => $c['code'], 'date' => $day]), null, 500);
    T::ok($after['total'] === 1 && (int) $after['items'][0]['busy'] > 0 && $after['free_total'] === 0 && $after['match_total'] === 1, 'ถูกจองแล้ว: ยังอยู่ในรายการ แต่บอกว่าไม่ว่าง');
    $only = Catalog::search(Catalog::filters(['q' => $c['code'], 'date' => $day, 'free' => '1']), null, 500);
    T::ok($only['total'] === 0 && $only['items'] === [] && $only['match_total'] === 1, 'เปิด “เฉพาะชุดที่ว่าง” แล้วชุดที่ถูกจองหายจากรายการ');
    $later = Catalog::search(Catalog::filters(['q' => $c['code'], 'date' => $D(160), 'free' => '1']), null, 500);
    T::eq($later['total'], 1, 'วันงานอื่นที่ไม่ชนกัน ชุดเดิมกลับมาว่าง');
})();

T::group('รูปชุดสี่ขนาด (media / large / thumb / mini)');
(function () {
    $img = (string) DB::value("SELECT path FROM costume_images WHERE path REGEXP '^costumes/[a-z]{2}-[0-9]{3}\\\\.jpg$' ORDER BY id LIMIT 1");
    T::ok($img !== '', 'ข้อมูลตัวอย่างมีรูปชุด', $img);
    T::ok(str_contains(thumb($img), '/thumb/') && str_contains(mini($img), '/mini/') && str_contains(large($img), '/large/') && !str_contains(media($img), '/thumb/'), 'thumb() mini() และ large() ชี้ไปยังไฟล์ขนาดของตัวเอง');
    $size = media_size($img);
    T::ok(is_array($size) && $size[0] > 600 && $size[1] > $size[0], 'media_size() อ่านขนาดจริงของรูป (แนวตั้ง)', json_encode($size));
    T::ok(media_size('costumes/no-such-file.jpg') === null && media_size(null) === null, 'รูปที่ไม่มีอยู่ได้ค่า null ไม่ทำให้หน้าเว็บพัง');
    T::ok(str_contains(media(null), 'img/placeholder.svg') && str_ends_with(thumb('costumes/no-such-file.jpg'), 'costumes/no-such-file.jpg') && str_ends_with(mini('costumes/no-such-file.jpg'), 'costumes/no-such-file.jpg') && str_ends_with(large('costumes/no-such-file.jpg'), 'costumes/no-such-file.jpg'), 'ไม่มีรูปใช้ภาพแทน และไม่มีไฟล์ย่อใช้ไฟล์หลัก');
    $missing = [];
    foreach (DB::all("SELECT path FROM costume_images WHERE path REGEXP '^costumes/[a-z]{2}-[0-9]{3}\\\\.jpg$'") as $r) {
        foreach (['', 'thumb/', 'mini/', 'large/'] as $tier) {
            $file = PUBLIC_PATH . '/media/' . dirname($r['path']) . '/' . $tier . basename($r['path']);
            if (!is_file($file) || filesize($file) < 5000) {
                $missing[] = $tier . basename($r['path']);
            }
        }
    }
    T::eq($missing, [], 'รูปชุดในข้อมูลตัวอย่างมีไฟล์ครบทั้งสี่ขนาด');
    foreach (['site/hero-2160.jpg', 'site/hero-4320.jpg', 'site/hero-m.jpg', 'site/fitting.jpg'] as $site) {
        T::ok(media_size($site) !== null, "ภาพประกอบหน้าร้าน $site อยู่ครบ");
    }
})();

T::group('ไฟล์ที่ส่งออกและไฟล์ที่รับเข้า');
(function () {
    T::eq(array_map([\App\Services\Reports::class, 'csvCell'], ['=HYPERLINK("x")', '+66 81', '@SUM(A1)', '-x', 'สมหญิง', '-120', '850', 1200, '']),
        ["'=HYPERLINK(\"x\")", "'+66 81", "'@SUM(A1)", "'-x", 'สมหญิง', '-120', '850', 1200, ''], 'CSV: ช่องที่ขึ้นต้นเหมือนสูตร Excel ถูกเติม \' ให้เป็นข้อความ ตัวเลขยังเป็นตัวเลข');
    // a 20 000 × 20 000 PNG header in a file of a few dozen bytes: must be refused before GD tries to decode it
    $ihdr = 'IHDR' . pack('NNCCCCC', 20000, 20000, 8, 2, 0, 0, 0);
    $png = "\x89PNG\r\n\x1a\n" . pack('N', 13) . $ihdr . pack('N', crc32($ihdr)) . pack('N', 0) . 'IEND' . pack('N', crc32('IEND'));
    $tmp = tempnam(sys_get_temp_dir(), 'pt-png');
    file_put_contents($tmp, $png);
    $r = \App\Core\Upload::image(['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($png), 'name' => 'big.png'], sys_get_temp_dir() . '/pt-up');
    T::ok(!$r['ok'] && str_contains($r['error'] ?? '', 'ล้านพิกเซล'), 'อัปโหลด: รูปที่ประกาศขนาดใหญ่เกินจริงถูกปฏิเสธก่อนถอดรหัส', json_encode($r, JSON_UNESCAPED_UNICODE));
    @unlink($tmp);
})();

T::group('ไอคอน');
(function () {
    $sprite = file_get_contents(BASE_PATH . '/public/assets/img/icons.svg');
    preg_match_all('/id="i-([a-z0-9-]+)"/', $sprite, $m);
    $have = array_flip($m[1]);
    $used = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH . '/app', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (substr($f->getFilename(), 0, 1) === '.' || $f->getExtension() !== 'php') continue;
        preg_match_all("/icon\\('([a-z0-9-]+)'/", file_get_contents($f->getPathname()), $mm);
        foreach ($mm[1] as $n) $used[$n] = true;
    }
    $missing = array_keys(array_diff_key($used, $have));
    T::ok(count($used) > 20, 'พบการเรียก icon() ในหน้าเว็บ (' . count($used) . ' ชื่อ)');
    T::eq($missing, [], 'ทุกไอคอนที่หน้าเว็บเรียกมีอยู่ใน icons.svg');
})();
