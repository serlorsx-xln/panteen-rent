<?php
/** @var array $s @var array $mail */
$v = fn($k) => old($k, $s[$k] ?? '');
$f = function (string $k, string $label, string $type = 'text', string $hint = '', array $attr = []) use ($v) {
    $a = '';
    foreach ($attr as $ak => $av) { $a .= ' ' . $ak . '="' . e((string) $av) . '"'; }
    $cls = in_array($type, ['number', 'time', 'tel'], true) ? 'input num' : 'input';
    $input = '<input class="' . $cls . '" id="s-' . $k . '" name="' . $k . '" type="' . $type . '" value="' . e((string) $v($k)) . '"' . $a . ' ' . invalid_attr($k) . '>';
    return '<div class="field"><label class="label" for="s-' . $k . '">' . e($label) . '</label>' . $input . (field_error($k) ?: ($hint ? '<span class="hint">' . e($hint) . '</span>' : '')) . '</div>';
};
?>
<div class="page-head"><div><h1>ตั้งค่าร้าน</h1><p class="muted">ข้อมูลที่ลูกค้าเห็น ช่องทางรับเงิน และกติกาการเช่า</p></div></div>
<div class="formgrid">
<form class="stack" style="gap:14px" method="post" action="<?= e(url('/admin/settings')) ?>" novalidate>
  <?= csrf_field() ?>
  <section class="box">
    <div class="box-head"><h2>ข้อมูลร้าน</h2></div>
    <div class="grid-2">
      <?= $f('shop_name', 'ชื่อร้าน', 'text', '', ['maxlength' => 100]) ?>
      <?= $f('shop_phone', 'เบอร์โทรร้าน', 'tel') ?>
      <?= $f('shop_line', 'LINE', 'text', 'เช่น @panteenrent') ?>
      <?= $f('shop_instagram', 'Instagram', 'text', 'ไม่ต้องใส่ @') ?>
    </div>
    <div style="margin-top:12px"><?= $f('shop_bio', 'คำอธิบายร้าน (ใต้ชื่อร้านหน้าแรก)', 'text', 'ไม่เกิน 160 ตัวอักษร', ['maxlength' => 160]) ?></div>
    <div class="field" style="margin-top:12px"><label class="label" for="s-shop_address">ที่อยู่</label><textarea class="input" id="s-shop_address" name="shop_address" rows="2" maxlength="300" <?= invalid_attr('shop_address') ?>><?= e($v('shop_address')) ?></textarea><?= field_error('shop_address') ?></div>
    <div class="grid-3" style="margin-top:12px">
      <?= $f('shop_hours', 'เวลาเปิดปิด (ข้อความ)') ?>
      <?= $f('open_time', 'เปิด', 'time') ?>
      <?= $f('close_time', 'ปิด', 'time') ?>
    </div>
    <p class="hint" style="margin-top:6px">เวลาเปิด–ปิดใช้สร้างช่วงเวลานัดลองชุด</p>
  </section>

  <section class="box">
    <div class="box-head"><h2>รับเงิน</h2></div>
    <div class="grid-2">
      <?= $f('promptpay_id', 'พร้อมเพย์', 'text', 'เบอร์มือถือ 10 หลัก หรือเลขประจำตัว 13 หลัก ใช้สร้าง QR ให้ลูกค้าสแกน', ['inputmode' => 'numeric']) ?>
      <?= $f('promptpay_name', 'ชื่อบัญชีพร้อมเพย์') ?>
      <?= $f('bank_name', 'ธนาคาร (โอนปกติ)') ?>
      <?= $f('bank_account', 'เลขบัญชี') ?>
    </div>
  </section>

  <section class="box">
    <div class="box-head"><h2>กติกาการเช่า</h2></div>
    <div class="grid-3">
      <?= $f('base_days', 'ค่าเช่ารวมกี่วัน', 'number', '', ['min' => 1, 'max' => 14]) ?>
      <?= $f('max_days', 'เช่าได้สูงสุด (วัน)', 'number', '', ['min' => 1, 'max' => 30]) ?>
      <?= $f('cleaning_days', 'วันซักหลังคืน', 'number', 'ชุดยังไม่ว่างในวันเหล่านี้', ['min' => 0, 'max' => 7]) ?>
      <?= $f('hold_hours', 'ล็อกชุดรอโอน (ชม.)', 'number', 'ไม่โอนในเวลานี้ การจองหมดอายุเอง', ['min' => 1, 'max' => 168]) ?>
      <?= $f('cancel_before_days', 'ยกเลิกเองได้ก่อนรับ (วัน)', 'number', '', ['min' => 0, 'max' => 30]) ?>
      <?= $f('min_lead_days', 'จองล่วงหน้าอย่างน้อย (วัน)', 'number', '0 = จองรับวันนี้ได้', ['min' => 0, 'max' => 30]) ?>
      <?= $f('booking_horizon_days', 'จองล่วงหน้าได้ไกลสุด (วัน)', 'number', '', ['min' => 7, 'max' => 730]) ?>
      <?= $f('default_pickup_time', 'เวลารับตั้งต้น', 'time') ?>
      <?= $f('default_return_time', 'เวลาคืนตั้งต้น', 'time') ?>
      <?= $f('remind_before_days', 'เตือนก่อนรับ/คืน (วัน)', 'number', 'ส่งอีเมลและแจ้งเตือนอัตโนมัติ', ['min' => 0, 'max' => 7]) ?>
    </div>
    <div class="field" style="margin-top:12px"><label class="label" for="s-rental_terms">เงื่อนไขการเช่า (ลูกค้าต้องกดยอมรับก่อนจอง)</label><textarea class="input" id="s-rental_terms" name="rental_terms" rows="6" maxlength="3000" <?= invalid_attr('rental_terms') ?>><?= e($v('rental_terms')) ?></textarea><?= field_error('rental_terms') ?: '<span class="hint">ขึ้นบรรทัดใหม่ = ข้อใหม่</span>' ?></div>
  </section>

  <section class="box" id="shipping">
    <div class="box-head"><h2>จัดส่งและส่งคืนทางพัสดุ</h2></div>
    <?php $chk = fn(string $k, string $label) => '<label class="check"><input type="checkbox" name="' . $k . '" value="1" ' . ($v($k) === '1' ? 'checked' : '') . '><span>' . e($label) . '</span></label>'; ?>
    <div class="stack" style="gap:8px">
      <?= $chk('ship_enabled', 'ส่งชุดถึงบ้านลูกค้าทางพัสดุ') ?>
      <?= $chk('messenger_enabled', 'ส่งด่วนด้วยเมสเซนเจอร์ (เฉพาะกรุงเทพฯ และปริมณฑล)') ?>
      <?= $chk('return_post_enabled', 'ให้ลูกค้าส่งชุดคืนทางพัสดุ') ?>
    </div>
    <div class="grid-3" style="margin-top:14px">
      <?= $f('ship_fee_metro', 'ค่าส่งกรุงเทพฯ/ปริมณฑล (บาท/เที่ยว)', 'number', '', ['min' => 0, 'max' => 2000]) ?>
      <?= $f('ship_fee_upcountry', 'ค่าส่งต่างจังหวัด (บาท/เที่ยว)', 'number', '', ['min' => 0, 'max' => 2000]) ?>
      <?= $f('messenger_fee', 'ค่าเมสเซนเจอร์ (บาท)', 'number', '', ['min' => 0, 'max' => 5000]) ?>
      <?= $f('ship_days_metro', 'ขนส่งใช้เวลา กทม. (วัน)', 'number', 'ร้านส่งออกล่วงหน้าเท่านี้', ['min' => 1, 'max' => 7]) ?>
      <?= $f('ship_days_upcountry', 'ขนส่งใช้เวลา ต่างจังหวัด (วัน)', 'number', '', ['min' => 1, 'max' => 7]) ?>
      <?= $f('ship_cutoff', 'ส่งออกได้วันนี้ถ้าจองก่อน', 'time', 'จองหลังเวลานี้ นับวันส่งเป็นพรุ่งนี้') ?>
      <?= $f('arrival_report_hours', 'แจ้งสภาพตอนได้รับภายใน (ชม.)', 'number', 'หลังพัสดุถึงลูกค้า', ['min' => 1, 'max' => 168]) ?>
      <?= $f('dispute_days', 'โต้แย้งค่าเสียหายได้ภายใน (วัน)', 'number', 'หลังร้านตรวจสภาพ', ['min' => 1, 'max' => 30]) ?>
    </div>
    <p class="hint" style="margin-top:6px">ค่าส่งคิดแยกขาไปและขากลับ ระยะขนส่งใช้กันชุดไว้ก่อนวันรับและหลังวันคืน ไม่ให้คิวซ้อน</p>
  </section>

  <section class="box">
    <div class="box-head"><h2>โหมดตัวอย่าง</h2></div>
    <label class="check"><input type="checkbox" name="is_demo" value="1" <?= $v('is_demo') === '1' ? 'checked' : '' ?>><span>แสดงแถบ “ข้อมูลตัวอย่าง” ด้านบนทุกหน้า และบัญชีทดลองในหน้าเข้าสู่ระบบ</span></label>
    <p class="hint" style="margin-top:6px">ปิดเมื่อเปิดร้านจริง</p>
  </section>
  <div><button class="btn btn-lg" type="submit">บันทึกการตั้งค่า</button></div>
</form>

<aside class="stack" style="gap:14px">
  <section class="box" id="mail">
    <div class="box-head"><h2>อีเมล</h2></div>
    <dl class="kv">
      <dt>โหมด</dt><dd><?= $mail['driver'] === 'smtp' ? 'ส่งจริงผ่าน SMTP' : 'บันทึกในกล่องจดหมายจำลอง' ?></dd>
      <?php if ($mail['driver'] === 'smtp'): ?><dt>เซิร์ฟเวอร์</dt><dd><?= e($mail['host']) ?></dd><dt>บัญชี</dt><dd><?= e($mail['user'] ?: '–') ?></dd><?php endif; ?>
      <dt>ผู้ส่ง</dt><dd><?= e($mail['from']) ?></dd>
    </dl>
    <p class="small muted" style="margin-top:10px">เปลี่ยนโหมดและรหัส SMTP ในไฟล์ <code>config/config.php</code> (ไม่เก็บรหัสผ่านในฐานข้อมูล) ดูวิธีตั้งค่า Gmail ใน README</p>
    <form method="post" action="<?= e(url('/admin/settings/test-mail')) ?>" style="margin-top:12px"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('send', 'ic ic-sm') ?>ส่งอีเมลทดสอบถึงฉัน</button></form>
    <p class="small" style="margin-top:8px"><a href="<?= e(url('/dev/mailbox')) ?>">เปิดกล่องจดหมายจำลอง</a></p>
  </section>
  <section class="box">
    <div class="box-head"><h2>งานอัตโนมัติ</h2></div>
    <?php $cron = @file_get_contents(STORAGE_PATH . '/cache/last-cron.txt'); ?>
    <p class="small">ปล่อยการจองที่หมดเวลาชำระ และส่งเตือนรับ–คืนชุด รันล่าสุด: <b><?= $cron ? e(thai_datetime(date('Y-m-d H:i:s', (int) trim($cron)))) : 'ยังไม่เคยรัน' ?></b></p>
    <p class="small muted" style="margin-top:6px">ระบบยังเช็กการจองที่หมดเวลาทุกครั้งที่มีคนเปิดหน้าเว็บ ตั้ง cron ให้รัน <code>php cli/cron.php</code> ทุก 10 นาทีเพื่อให้อีเมลเตือนออกตรงเวลา</p>
  </section>
  <?php if (setting('is_demo') === '1'): ?>
  <section class="box">
    <div class="box-head"><h2>ข้อมูลตัวอย่าง</h2></div>
    <p class="small muted">ล้างข้อมูลทั้งหมดแล้วสร้างข้อมูลตัวอย่างใหม่ให้ตรงกับวันนี้ ใช้ก่อนสาธิตงาน</p>
    <form method="post" action="<?= e(url('/admin/settings/reseed')) ?>" style="margin-top:10px" data-confirm="ลบข้อมูลทั้งหมดและสร้างข้อมูลตัวอย่างใหม่? ทุกคนจะถูกออกจากระบบ">
      <?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit"><?= icon('rotate-ccw', 'ic ic-sm') ?>รีเซ็ตข้อมูลตัวอย่าง</button>
    </form>
  </section>
  <?php endif; ?>
</aside>
</div>
