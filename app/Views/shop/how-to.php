<?php
use App\Services\Shipping;

/** How renting works, the shop's terms, and where to find it. The five steps are the same ones the home page tells. */
$steps = include APP_PATH . '/Views/partials/howto-steps.php';
$terms = array_filter(array_map('trim', explode("\n", (string) setting('rental_terms'))));
?>
<div class="wrap">
  <header class="list-head">
    <h1>วิธีเช่าและเงื่อนไข</h1>
    <p>จองออนไลน์ได้ทุกขั้นตอน เห็นวันว่างและราคาครบโดยไม่ต้องทักแชตถามคิว</p>
  </header>

  <section class="how guide-sec" aria-labelledby="g-steps">
    <div class="how-head"><h2 id="g-steps">เช่าชุดในห้าขั้น</h2></div>
    <ol class="how-rows">
      <?php foreach ($steps as [$head, $body]): ?>
      <li><b><?= e($head) ?></b><p><?= e($body) ?></p></li>
      <?php endforeach; ?>
    </ol>
  </section>

  <section class="how guide-sec" aria-labelledby="g-terms">
    <div class="how-head"><h2 id="g-terms">เงื่อนไขการเช่า</h2></div>
    <ul class="guide-list">
      <?php foreach ($terms as $t): ?><li><?= e($t) ?></li><?php endforeach; ?>
    </ul>
  </section>

  <section class="how guide-sec" aria-labelledby="g-time">
    <div class="how-head"><h2 id="g-time">ระยะเวลาและการยกเลิก</h2></div>
    <ul class="guide-list">
      <li>ค่าเช่ารวม <?= (int) setting('base_days') ?> วัน เช่าได้สูงสุด <?= (int) setting('max_days') ?> วันต่อครั้ง</li>
      <li>หลังคืนชุด ร้านใช้เวลาซักและตรวจสภาพ <?= (int) setting('cleaning_days') ?> วัน ก่อนให้เช่าต่อ</li>
      <li>ยกเลิกเองในหน้าการจองได้ก่อนวันรับชุดอย่างน้อย <?= (int) setting('cancel_before_days') ?> วัน หลังจากนั้นติดต่อร้าน</li>
    </ul>
  </section>

  <?php if (Shipping::enabled()): ?>
  <section class="how guide-sec" aria-labelledby="g-ship">
    <div class="how-head"><h2 id="g-ship">ส่งชุดถึงบ้านและส่งคืนทางพัสดุ</h2></div>
    <ul class="guide-list">
      <li>เลือกตอนจองได้ว่าจะรับชุดที่ร้านหรือให้ร้านส่งพัสดุ ค่าส่ง<?= e(Shipping::ZONE['metro']) ?> <?= e(money(setting('ship_fee_metro'))) ?> ต่างจังหวัด <?= e(money(setting('ship_fee_upcountry'))) ?> ต่อเที่ยว ราคารวมให้เห็นก่อนกดจอง</li>
      <li>ร้านส่งออกล่วงหน้าตามวันขนส่ง (<?= e(Shipping::ZONE['metro']) ?> <?= (int) setting('ship_days_metro') ?> วัน ต่างจังหวัด <?= (int) setting('ship_days_upcountry') ?> วัน) ให้ถึงภายในวันรับชุด จองหลัง <?= e(setting('ship_cutoff')) ?> น. นับวันส่งเริ่มจากวันถัดไป เลขพัสดุขึ้นในหน้าการจองทันทีที่ร้านส่ง</li>
      <?php if (Shipping::messengerEnabled()): ?><li>เมสเซนเจอร์ส่งถึงในวันรับชุด เฉพาะ<?= e(Shipping::ZONE['metro']) ?> ค่าส่ง <?= e(money(setting('messenger_fee'))) ?></li><?php endif; ?>
      <li>ได้รับกล่องแล้วกด “ได้รับชุดแล้ว” และถ้าเห็นอะไรผิดปกติ แจ้งพร้อมรูปภายใน <?= (int) setting('arrival_report_hours') ?> ชั่วโมง สิ่งที่แจ้งไว้จะขึ้นให้พนักงานเห็นตอนตรวจสภาพชุดคืน</li>
      <?php if (Shipping::returnPostEnabled()): ?><li>ส่งคืนทางพัสดุ: ใส่ชุดและอุปกรณ์ตามใบแพ็กของกลับในกล่องเดิม ติดใบปะหน้าส่งคืนที่ร้านแนบไว้ ฝากส่งภายในวันคืนชุด แล้วกรอกเลขพัสดุในหน้าการจอง วันคืนนับจากวันที่ฝากส่ง</li><?php endif; ?>
      <li>ร้านตรวจสภาพเมื่อพัสดุถึง คิดค่าเสียหายตามตารางราคาที่ประกาศไว้และไม่เกินมูลค่าชุด เห็นผลตรวจทีละรายการพร้อมรูป แล้วโอนเงินมัดจำส่วนที่เหลือคืน</li>
      <li>ไม่เห็นด้วยกับค่าเสียหาย กดโต้แย้งในหน้าการจองได้ภายใน <?= (int) setting('dispute_days') ?> วันหลังตรวจ ร้านตอบกลับในหน้าเดียวกัน และถ้าลดให้จะโอนคืนพร้อมออกใบปรับปรุง</li>
    </ul>
  </section>

  <?php endif; ?>
  <section class="how guide-sec" aria-labelledby="g-contact">
    <div class="how-head"><h2 id="g-contact">ติดต่อร้าน</h2></div>
    <div>
      <dl class="visit-facts">
        <div><dt>ที่อยู่</dt><dd><?= e(setting('shop_address')) ?></dd></div>
        <div><dt>เวลาเปิด</dt><dd><?= e(setting('shop_hours')) ?></dd></div>
        <div><dt>โทร</dt><dd><a href="tel:<?= e(preg_replace('/\D/', '', (string) setting('shop_phone'))) ?>"><?= e(setting('shop_phone')) ?></a></dd></div>
        <?php if (setting('shop_line')): ?><div><dt>LINE</dt><dd><?= e(setting('shop_line')) ?></dd></div><?php endif; ?>
      </dl>
      <div class="row">
        <a class="btn" href="<?= e(url('/dresses')) ?>">ดูชุดทั้งหมด</a>
        <a class="block-link" href="<?= e(url('/account/appointments')) ?>">นัดลองชุดที่ร้าน <?= icon('arrow-right') ?></a>
      </div>
    </div>
  </section>
</div>
