<?php
declare(strict_types=1);

/**
 * Delivery, return by post, return inspection and disputes.
 * Runs after unit.php on the same test database; its own dresses use codes SH-001, SH-002, …
 */
use App\Controllers\Admin\FulfilmentController;
use App\Core\DB;
use App\Services\Addresses;
use App\Services\Availability;
use App\Services\BookingException;
use App\Services\BookingService as BS;
use App\Services\Fulfilment;
use App\Services\Inspection;
use App\Services\Reminders;
use App\Services\Settings;
use App\Services\Shipping;

$staffId = (int) DB::value("SELECT id FROM users WHERE email = 'staff@panteen.test'");
$members = array_map('intval', array_column(DB::all("SELECT id FROM users WHERE role = 'member' AND status = 'active' AND email_verified_at IS NOT NULL ORDER BY id"), 'id'));
$D = fn(int $n) => add_days(today(), $n);
$newCostume = function (array $o = []): array {
    static $n = 0;
    $n++;
    $id = DB::insert('costumes', $o + [
        'code' => 'SH-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'category_id' => (int) DB::value('SELECT id FROM categories ORDER BY id LIMIT 1'),
        'name' => 'ชุดทดสอบจัดส่ง ' . $n, 'size_label' => 'M', 'rent_price' => 1000, 'extra_day_price' => 200, 'deposit' => 1500, 'late_fee_per_day' => 150, 'status' => 'active',
    ]);
    return DB::one('SELECT * FROM costumes WHERE id = ?', [$id]);
};
$addr = fn(string $province, string $postcode) => Shipping::cleanAddress([
    'recipient' => 'ผู้รับทดสอบ', 'phone' => '081-234-5678', 'line1' => '1 ถนนทดสอบ (ที่อยู่ตัวอย่าง)',
    'subdistrict' => 'ตำบลทดสอบ', 'district' => 'อำเภอทดสอบ', 'province' => $province, 'postcode' => $postcode,
])[0];
$up = $addr('เชียงใหม่', '50200');
$metro = $addr('นนทบุรี', '11000');
$shipBook = fn(array $c, int $uid, int $p, int $r, string $delivery, string $return, ?array $a) => BS::create([
    'user_id' => $uid, 'costume_id' => (int) $c['id'], 'event_date' => $D($p + 1), 'pickup_date' => $D($p), 'return_date' => $D($r),
    'delivery_method' => $delivery, 'return_method' => $return, 'address' => $a, 'channel' => 'counter', 'actor_id' => $staffId,
]);
$feeUp = (float) Settings::get('ship_fee_upcountry');
$daysUp = Settings::int('ship_days_upcountry');

/* ------------------------------------------------------------------ */
T::group('จัดส่ง: เขตพื้นที่ ค่าส่ง และวันที่ร้านต้องส่งออก (Shipping)');
T::eq([Shipping::zone('นนทบุรี'), Shipping::zone('เชียงใหม่'), Shipping::zone('ไม่มีจังหวัดนี้')], ['metro', 'upcountry', null], 'แยกเขต กทม.-ปริมณฑล / ต่างจังหวัด');
$p = Shipping::plan($D(20), $D(22));
T::eq([$p['ship_by'], $p['block_from'], $p['delivery_fee'], $p['return_fee'], $p['zone']], [null, $D(20), 0.0, 0.0, null], 'รับ-คืนที่ร้าน: ไม่มีวันส่งออก ไม่มีค่าส่ง');
$p = Shipping::plan($D(20), $D(22), 'post', 'post', 'upcountry');
T::eq([$p['ship_by'], $p['block_from'], $p['block_until'], $p['delivery_fee'], $p['return_fee']],
    [$D(20 - $daysUp), $D(20 - $daysUp), Availability::blockUntil($D(22), $daysUp), $feeUp, $feeUp], 'ส่ง-คืนพัสดุต่างจังหวัด: ส่งออกก่อนตามวันขนส่ง และกันชุดเผื่อขากลับ');
$p = Shipping::plan($D(20), $D(22), 'messenger', 'store', 'metro');
T::eq([$p['out_days'], $p['ship_by'], $p['delivery_fee']], [0, $D(20), (float) Settings::get('messenger_fee')], 'เมสเซนเจอร์ส่งถึงในวันรับชุด');
T::ok(isset(Shipping::check(Shipping::plan($D(20), $D(22), 'messenger', 'store', 'upcountry'))['delivery_method']), 'เมสเซนเจอร์ไปต่างจังหวัดไม่ได้');
T::ok(isset(Shipping::check(Shipping::plan($D(20), $D(22), 'post', 'store', null))['address']), 'ส่งพัสดุต้องมีที่อยู่');
T::ok(isset(Shipping::check(Shipping::plan($D(1), $D(3), 'post', 'store', 'upcountry'))['pickup_date']) || $daysUp < 2, 'ส่งพัสดุไม่ทันวันรับชุด → บอกวันเร็วที่สุดที่ได้รับ');
$cut = Settings::get('ship_cutoff');
Settings::set(['ship_cutoff' => '00:00']);
$today1 = Shipping::plan($D(1), $D(3), 'post', 'store', 'metro');
T::ok(Settings::int('ship_days_metro') !== 1 || isset(Shipping::check($today1)['pickup_date']), 'ลูกค้าจองหลังเวลาตัดรอบ ส่งวันนี้ไม่ทัน');
T::eq(Settings::int('ship_days_metro') !== 1 ? [] : Shipping::check($today1, true), [], 'พนักงานหน้าร้านยังส่งวันนี้ได้หลังเวลาตัดรอบ');
Settings::set(['ship_cutoff' => $cut, 'ship_enabled' => '0']);
T::ok(isset(Shipping::check(Shipping::plan($D(20), $D(22), 'post', 'store', 'metro'))['delivery_method']), 'ปิดบริการจัดส่งแล้ว เลือกส่งพัสดุไม่ได้');
T::eq(Shipping::check(Shipping::plan($D(20), $D(22))), [], 'ปิดบริการจัดส่งแล้ว รับที่ร้านยังจองได้');
Settings::set(['ship_enabled' => '1']);
[, $err] = Shipping::cleanAddress(['recipient' => '', 'phone' => '12', 'province' => 'X', 'postcode' => '0123']);
T::eq(array_keys($err), ['recipient', 'line1', 'subdistrict', 'district', 'phone', 'province', 'postcode'], 'ที่อยู่ไม่ครบ บอกทุกช่องที่ผิด');
T::eq([$metro['phone'], $metro['postcode']], ['0812345678', '11000'], 'เก็บเบอร์โทรเป็นตัวเลขล้วน');
T::eq(Shipping::cleanTracking('thaipost', 'ef 123-456-789 th'), ['EF123456789TH', null], 'เลขพัสดุไปรษณีย์ไทย ตัดช่องว่างและขีด เป็นตัวใหญ่');
T::ok(Shipping::cleanTracking('thaipost', 'EF12345TH')[1] !== null, 'เลขพัสดุไปรษณีย์ไทยผิดรูปแบบ ถูกปฏิเสธ');
T::eq([Shipping::cleanTracking('jnt', '800000000123')[1], Shipping::cleanTracking('jnt', '80000000012')[1] !== null], [null, true], 'J&T ต้องเป็นตัวเลข 12 หลัก');
T::eq(Shipping::cleanTracking('messenger', ''), [null, null], 'เมสเซนเจอร์ไม่ต้องมีเลขพัสดุ');
T::ok(Shipping::cleanTracking('dhl', 'X')[1] !== null, 'บริษัทขนส่งที่ไม่รู้จัก ถูกปฏิเสธ');

/* ------------------------------------------------------------------ */
T::group('จองแบบส่งพัสดุ: ค่าส่ง ที่อยู่ และล็อกชุดรวมวันขนส่ง');
$S1 = $newCostume(['replacement_value' => 3000, 'accessories' => 'สไบ, เข็มขัด']);
$uid = $members[0];
T::throws(fn() => $shipBook($S1, $uid, 20, 22, 'post', 'post', null), 'ส่งพัสดุโดยไม่มีที่อยู่ จองไม่ได้', BookingException::class);
$b = $shipBook($S1, $uid, 20, 22, 'post', 'post', $up);
T::eq([$b['delivery_method'], $b['return_method'], $b['ship_zone'], $b['ship_by_date'], (float) $b['delivery_fee'], (float) $b['return_fee']],
    ['post', 'post', 'upcountry', $D(20 - $daysUp), $feeUp, $feeUp], 'บันทึกวิธีส่ง-คืน เขต วันส่งออก และค่าส่งสองขา');
T::eq((float) $b['total_amount'], 1000 + 1500 + 2 * $feeUp, 'ยอดชำระรวมค่าส่งไปและค่าส่งคืน');
T::eq([$b['ship_province'], $b['ship_recipient']], ['เชียงใหม่', 'ผู้รับทดสอบ'], 'การจองเก็บสำเนาที่อยู่ของตัวเอง');
T::throws(fn() => BS::create(['user_id' => $members[1], 'costume_id' => (int) $S1['id'], 'event_date' => $D(18), 'pickup_date' => $D(17), 'return_date' => $D(20 - $daysUp), 'channel' => 'counter', 'actor_id' => $staffId]),
    'คิวก่อนหน้าคืนชุดในวันที่ร้านต้องส่งพัสดุออก → ชุดไม่ว่าง', BookingException::class);
$early = BS::create(['user_id' => $members[1], 'costume_id' => (int) $S1['id'], 'event_date' => $D(9), 'pickup_date' => $D(8), 'return_date' => $D(10), 'channel' => 'counter', 'actor_id' => $staffId]);
T::eq($early['status'], 'pending_payment', 'คิวที่จบก่อนช่วงขนส่ง ยังจองได้');
BS::cancel((int) $early['id'], $staffId, 'ทดสอบ', true);

/* ------------------------------------------------------------------ */
T::group('ส่งชุด → ลูกค้าได้รับ → แจ้งสภาพ → ส่งคืนทางพัสดุ → ร้านรับ → ตรวจ');
$bid = (int) $b['id'];
T::throws(fn() => Fulfilment::ship($bid, $staffId, 'thaipost', 'EF123456789TH'), 'ส่งพัสดุก่อนลูกค้าชำระไม่ได้', BookingException::class);
$b = BS::recordCounterPayment($bid, $staffId, 'transfer');
T::eq($b['status'], 'confirmed', 'ชำระแล้ว รอส่งพัสดุ');
T::throws(fn() => Fulfilment::ship($bid, $staffId, 'messenger', ''), 'การจองแบบพัสดุ ส่งด้วยเมสเซนเจอร์ไม่ได้', BookingException::class);
T::throws(fn() => Fulfilment::ship($bid, $staffId, 'thaipost', '123'), 'เลขพัสดุผิดรูปแบบ ส่งไม่ได้', BookingException::class);
T::throws(fn() => BS::markPickedUp($bid, $staffId), 'การจองแบบส่งพัสดุ บันทึก “ลูกค้ามารับที่ร้าน” ไม่ได้', BookingException::class);
$b = Fulfilment::ship($bid, $staffId, 'thaipost', 'ef123456789th', 'แพ็กพร้อมสไบและเข็มขัด');
$out = Fulfilment::lastShipment($bid, 'out');
T::eq([$b['status'], $out['status'], $out['tracking_no'], $out['shipped_on']], ['shipped', 'in_transit', 'EF123456789TH', today()], 'ส่งแล้ว เก็บเลขพัสดุและวันส่ง');
T::ok((bool) DB::value("SELECT 1 FROM notifications WHERE user_id = ? AND type = 'shipped'", [$uid]), 'แจ้งลูกค้าว่าส่งแล้วพร้อมเลขพัสดุ');
T::throws(fn() => Inspection::complete($bid, $staffId, []), 'ตรวจคืนระหว่างที่ชุดยังไม่ถึงลูกค้าไม่ได้', BookingException::class);
$b = Fulfilment::markDelivered($bid, $uid, true);
T::eq([$b['status'], Fulfilment::lastShipment($bid, 'out')['status']], ['picked_up', 'delivered'], 'ลูกค้ากดได้รับชุด → กำลังเช่า');
T::ok(Fulfilment::arrivalWindowOpen($b), 'เปิดให้แจ้งสภาพตอนได้รับภายในเวลาที่กำหนด');
$b = Fulfilment::reportArrival($bid, $uid, 'กระดุมเม็ดบนหลุดมาตั้งแต่ในกล่อง');
T::ok($b['arrival_reported_at'] !== null && !Fulfilment::arrivalWindowOpen($b), 'บันทึกสภาพตอนได้รับ แล้วปิดช่องแจ้ง');
T::throws(fn() => Fulfilment::reportArrival($bid, $uid, 'แจ้งซ้ำ'), 'แจ้งสภาพตอนได้รับได้ครั้งเดียว', BookingException::class);
T::throws(fn() => Fulfilment::postBack($bid, $uid, 'thaipost', 'EF987654321TH', $D(1)), 'วันฝากส่งในอนาคตไม่ได้', BookingException::class);
T::throws(fn() => Fulfilment::postBack($bid, $uid, 'thaipost', '', today()), 'ลูกค้าแจ้งส่งคืนต้องมีเลขพัสดุ', BookingException::class);
$b = Fulfilment::postBack($bid, $uid, 'thaipost', 'EF987654321TH', today());
T::eq([$b['status'], Fulfilment::lastShipment($bid, 'back')['status']], ['returning', 'in_transit'], 'ลูกค้าแจ้งฝากส่งคืน → รอร้านรับพัสดุ');
T::throws(fn() => Inspection::complete($bid, $staffId, []), 'ยังไม่ได้รับพัสดุคืน ตรวจสภาพไม่ได้', BookingException::class);
$b = Fulfilment::receiveBack($bid, $staffId);
T::eq(Fulfilment::lastShipment($bid, 'back')['status'], 'received', 'ร้านกดได้รับพัสดุคืน');
T::throws(fn() => Fulfilment::receiveBack($bid, $staffId), 'รับพัสดุคืนซ้ำไม่ได้', BookingException::class);

// The customer was due back two days ago and posted it yesterday: late days stop at the posting date, not at arrival.
DB::update('bookings', ['return_date' => $D(-2)], 'id = :id', ['id' => $bid]);
DB::run("UPDATE shipments SET shipped_on = ? WHERE booking_id = ? AND direction = 'back'", [$D(-1), $bid]);
$tear = (int) DB::value("SELECT id FROM damage_types WHERE name = 'ตะเข็บแยก'");
$accFee = (float) DB::value("SELECT amount FROM damage_types WHERE area = ? AND fee_type = 'fixed' AND is_active = 1 ORDER BY sort_order, id LIMIT 1", [Inspection::ACCESSORY_AREA]);
$price = Inspection::price(BS::find($bid), [['type_id' => $tear, 'qty' => 2, 'amount' => '', 'note' => 'ใต้แขนซ้าย']], ['เข็มขัด', 'ของที่ไม่ได้อยู่ในรายการ']);
T::eq([count($price['items']), $price['total']], [2, 300.0 + $accFee], 'คิดราคาตามตาราง: ตะเข็บแยก 2 จุด + ของหาย 1 ชิ้น (ไม่นับของนอกรายการ)');
$big = Inspection::price(BS::find($bid), [['type_id' => $tear, 'qty' => 1, 'amount' => '99999', 'note' => '']], []);
T::eq([$big['total'], $big['capped']], [3000.0, true], 'ค่าเสียหายไม่เกินมูลค่าชุด');
T::ok(Inspection::price(BS::find($bid), [['type_id' => $tear, 'amount' => '-5']], [])['errors'] !== [], 'จำนวนเงินติดลบถูกปฏิเสธ');
T::throws(fn() => Inspection::complete($bid, $staffId, ['checks' => ['fabric']]), 'จุดตรวจไม่ผ่านแต่ไม่คิดเงินและไม่มีหมายเหตุ ปิดงานไม่ได้', BookingException::class);
$b = Inspection::complete($bid, $staffId, [
    'checks' => array_keys(Inspection::CHECKS), 'rows' => [['type_id' => $tear, 'qty' => 2, 'amount' => '', 'note' => 'ใต้แขนซ้าย']],
    'missing' => ['เข็มขัด'], 'outcome' => 'maintenance', 'note' => 'ส่งช่างเย็บ',
]);
T::eq([$b['status'], (int) $b['late_days'], (float) $b['late_fee'], (float) $b['damage_fee']], ['returned', 1, 150.0, 300.0 + $accFee], 'ปิดการเช่า: ช้า 1 วันนับถึงวันฝากส่ง + ค่าเสียหายตามตาราง');
T::eq((float) $b['deposit_refund'], 1500 - 150 - 300 - $accFee, 'คืนมัดจำหลังหักค่าปรับและค่าเสียหาย');
T::eq(count(Inspection::items($bid)), 2, 'เก็บรายการค่าเสียหายแยกทีละบรรทัด');
T::eq(DB::value('SELECT status FROM costumes WHERE id = ?', [$S1['id']]), 'maintenance', 'ผลตรวจ “ส่งซ่อม” ปิดรับจองชุดไว้ก่อน');
T::ok((bool) DB::value("SELECT 1 FROM receipts WHERE booking_id = ? AND kind = 'settlement'", [$bid]), 'ออกใบสรุปการคืนชุด');
DB::update('costumes', ['status' => 'active'], 'id = :id', ['id' => $S1['id']]);

/* ------------------------------------------------------------------ */
T::group('ลูกค้าโต้แย้งค่าเสียหาย');
T::eq(Inspection::canDispute($b)[0], true, 'โต้แย้งได้ภายในเวลาที่กำหนด');
T::throws(fn() => Inspection::dispute($bid, $uid, 'สั้นไป'), 'เหตุผลสั้นเกินไป', BookingException::class);
$b = Inspection::dispute($bid, $uid, 'ตะเข็บแยกอยู่ก่อนแล้ว มีรูปตอนเปิดกล่อง');
T::ok($b['dispute_at'] !== null && !Inspection::canDispute($b)[0], 'บันทึกข้อโต้แย้ง และโต้แย้งซ้ำไม่ได้');
T::throws(fn() => Inspection::resolve($bid, $staffId, 'ตรวจแล้ว', 99999), 'ลดค่าเสียหายเกินยอดที่คิดไม่ได้', BookingException::class);
T::throws(fn() => Inspection::resolve($bid, $staffId, '  ', 0), 'ตอบข้อโต้แย้งต้องมีข้อความ', BookingException::class);
$b = Inspection::resolve($bid, $staffId, 'ดูรูปตอนเปิดกล่องแล้ว ลดค่าตะเข็บให้', 300);
T::eq((float) $b['damage_waived'], 300.0, 'บันทึกยอดที่ลดให้');
T::ok((bool) DB::value("SELECT 1 FROM payments WHERE booking_id = ? AND kind = 'refund' AND amount = 300 AND status = 'approved'", [$bid]), 'บันทึกการคืนเงินส่วนที่ลด');
T::ok((bool) DB::value("SELECT 1 FROM receipts WHERE booking_id = ? AND kind = 'adjustment'", [$bid]), 'ออกใบปรับปรุงค่าเสียหาย');
T::throws(fn() => Inspection::resolve($bid, $staffId, 'ตอบซ้ำ', 0), 'ตอบข้อโต้แย้งที่ปิดแล้วซ้ำไม่ได้', BookingException::class);
DB::update('bookings', ['inspected_at' => date('Y-m-d H:i:s', time() - (Settings::int('dispute_days') + 1) * 86400), 'dispute_at' => null], 'id = :id', ['id' => $bid]);
T::eq(Inspection::canDispute(BS::find($bid))[0], false, 'เลยเวลาโต้แย้งแล้ว โต้แย้งไม่ได้');

/* ------------------------------------------------------------------ */
T::group('ส่งไม่สำเร็จ พัสดุหาย และเปลี่ยนเป็นรับที่ร้าน');
$S2 = $newCostume();
$b = $shipBook($S2, $members[1], 20, 22, 'post', 'store', $metro);
$b2 = (int) $b['id'];
BS::recordCounterPayment($b2, $staffId, 'cash');
$b = Fulfilment::switchToPickup($b2, $staffId);
T::eq([$b['delivery_method'], $b['ship_by_date']], ['pickup', null], 'ยังไม่ส่ง เปลี่ยนเป็นรับที่ร้านได้');
T::ok((bool) DB::value("SELECT 1 FROM payments WHERE booking_id = ? AND kind = 'refund' AND amount = ?", [$b2, $b['delivery_fee']]), 'ยังไม่ได้ส่ง คืนค่าจัดส่งให้ลูกค้า');
$S3 = $newCostume();
$b3 = (int) $shipBook($S3, $members[1], 20, 22, 'post', 'post', $metro)['id'];
BS::recordCounterPayment($b3, $staffId, 'cash');
Fulfilment::ship($b3, $staffId, 'kex', 'KEX0000000999');
T::throws(fn() => Fulfilment::deliveryFailed($b3, $staffId, ' '), 'ส่งไม่สำเร็จต้องระบุสาเหตุ', BookingException::class);
$b = Fulfilment::deliveryFailed($b3, $staffId, 'ไม่มีคนรับ ขนส่งตีกลับ');
T::eq([$b['status'], Fulfilment::lastShipment($b3, 'out')['status']], ['confirmed', 'failed'], 'ส่งไม่สำเร็จ → กลับไปรอส่งใหม่');
Fulfilment::ship($b3, $staffId, 'kex', 'KEX0000001000');
T::eq(count(Fulfilment::shipments($b3)), 2, 'ส่งรอบใหม่ได้ เก็บประวัติทุกรอบ');
Fulfilment::markDelivered($b3, $staffId, false);
Fulfilment::postBack($b3, $members[1], 'flash', 'TH0123456789', today());
T::throws(fn() => Fulfilment::returnLost($b3, $staffId, ''), 'พัสดุคืนหายต้องระบุรายละเอียด', BookingException::class);
Fulfilment::returnLost($b3, $staffId, 'ขนส่งยืนยันว่าหาย เลขเคลม C-1');
T::eq(Fulfilment::lastShipment($b3, 'back')['status'], 'lost', 'บันทึกพัสดุคืนหาย แล้วปิดงานตรวจได้');
$b = Inspection::complete($b3, $staffId, ['rows' => [['type_id' => $tear, 'qty' => 1, 'amount' => '5000', 'note' => 'ชุดหายระหว่างขนส่ง']], 'outcome' => 'retired']);
T::eq([$b['status'], DB::value('SELECT status FROM costumes WHERE id = ?', [$S3['id']])], ['returned', 'retired'], 'พัสดุหาย → ปิดการเช่าและเลิกให้เช่าชุด');

/* ------------------------------------------------------------------ */
T::group('สแกนชุด สมุดที่อยู่ และงานเตือนพัสดุ');
T::eq(FulfilmentController::lookup(strtolower((string) DB::value('SELECT booking_no FROM bookings WHERE id = ?', [$bid]))), ['go' => '/admin/bookings/' . $bid], 'สแกนเลขจอง → เปิดการจอง');
$hit = FulfilmentController::lookup('https://shop.example/dress/' . $S1['code']);
T::eq($hit['costume']['code'] ?? null, $S1['code'], 'สแกน QR ที่เป็นลิงก์ชุด → เจอชุด');
T::eq(FulfilmentController::lookup('ZZ-404'), ['none' => true], 'รหัสที่ไม่มี → ไม่พบ');
$u = $members[2];
DB::run('DELETE FROM addresses WHERE user_id = ?', [$u]);
$ids = [];
for ($i = 0; $i < Addresses::MAX; $i++) {
    $ids[] = Addresses::save($u, $metro + ['label' => 'ที่ ' . $i])[0];
}
T::eq(Addresses::save($u, $metro)[1]['form'] ?? null, 'เก็บที่อยู่ได้สูงสุด ' . Addresses::MAX . ' ที่อยู่ ลบที่อยู่เดิมก่อนเพิ่มใหม่', 'เก็บที่อยู่ได้ไม่เกินจำนวนที่กำหนด');
T::eq((int) DB::value('SELECT is_default FROM addresses WHERE id = ?', [$ids[0]]), 1, 'ที่อยู่แรกเป็นที่อยู่หลัก');
Addresses::delete($u, $ids[0]);
T::eq((int) DB::value('SELECT SUM(is_default) FROM addresses WHERE user_id = ?', [$u]), 1, 'ลบที่อยู่หลัก → ตั้งที่อยู่อื่นเป็นหลักแทน');
T::eq([Addresses::find($members[0], $ids[1]), Addresses::makeDefault($members[0], $ids[1]), Addresses::delete($members[0], $ids[1])], [null, false, false], 'แตะที่อยู่ของสมาชิกคนอื่นไม่ได้');
$S4 = $newCostume();
$b4 = (int) $shipBook($S4, $members[1], 20, 22, 'post', 'store', $up)['id'];
BS::recordCounterPayment($b4, $staffId, 'cash');
DB::update('bookings', ['ship_by_date' => today()], 'id = :id', ['id' => $b4]);
Reminders::run();
T::ok((bool) DB::value("SELECT 1 FROM notifications WHERE type = 'ship_due' AND link = ?", ['/admin/bookings/' . $b4]), 'เตือนพนักงานเมื่อถึงวันต้องส่งพัสดุ');
T::eq(Reminders::run()['parcel'], 0, 'เตือนพัสดุซ้ำในวันเดียวกันไม่ได้');
