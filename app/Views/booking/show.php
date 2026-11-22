<?php
/** @var array $b @var ?string $payload @var array $payments @var array $receipts @var array $events @var array $cancel
 *  @var array $shipments @var array $photos @var array $damage @var array $dispute @var array $accessories */
use App\Core\View;
use App\Services\BookingService;
use App\Services\Fulfilment;
use App\Services\Inspection;
use App\Services\Shipping;
use App\Services\Status;

$errors = form_errors();
$st = $b['status'];
$pendingSlip = array_values(array_filter($payments, fn($p) => $p['kind'] === 'booking' && $p['status'] === 'pending'))[0] ?? null;
$rejected = array_values(array_filter($payments, fn($p) => $p['kind'] === 'booking' && $p['status'] === 'rejected'))[0] ?? null;
$ships = BookingService::ships($b);
$posts = $b['return_method'] === 'post';
$out = array_values(array_filter($shipments, fn($s) => $s['direction'] === 'out'));
$backs = array_values(array_filter($shipments, fn($s) => $s['direction'] === 'back'));
$lastOut = $out ? end($out) : null;
$lastBack = $backs ? end($backs) : null;
$base = '/bookings/' . $b['id'];
?>
<div class="col">
  <nav class="crumbs" aria-label="ตำแหน่งหน้า"><a href="<?= e(url('/bookings')) ?>"><?= icon('arrow-left', 'ic ic-sm') ?> การจองของฉัน</a></nav>
  <div class="page-head spread">
    <div>
      <h1>การจอง <span class="num"><?= e($b['booking_no']) ?></span></h1>
      <p>จองเมื่อ <?= e(thai_datetime($b['created_at'])) ?></p>
    </div>
    <?= Status::badge(Status::BOOKING, $st) ?>
  </div>
  <?php \App\Core\View::partial('steps', ['b' => $b]); ?>

  <div class="booking-grid">
    <div>
      <?php if ($st === 'pending_payment'): ?>
      <section class="panel" id="pay" aria-labelledby="h-pay">
        <div class="spread" style="margin-bottom:14px">
          <div class="panel-title" style="margin:0"><?= icon('qr-code') ?><h2 id="h-pay">ชำระเงินด้วยพร้อมเพย์</h2></div>
          <?php if ($b['hold_expires_at']): ?>
          <span class="countdown" data-countdown="<?= e($b['hold_expires_at']) ?>"><?= icon('hourglass', 'ic ic-sm') ?><span data-countdown-text>ชำระภายใน <?= e(thai_datetime($b['hold_expires_at'])) ?></span></span>
          <?php endif; ?>
        </div>
        <?php if ($rejected): ?>
        <p class="form-error" style="margin:0 0 14px"><?= icon('circle-alert') ?><span>สลิปครั้งก่อนไม่ผ่าน: <?= e($rejected['reject_reason'] ?? 'ร้านตรวจไม่พบยอดเงิน') ?> แนบสลิปใหม่ได้เลย</span></p>
        <?php endif; ?>
        <div class="pay">
          <div class="qr">
            <span class="qr-head">PromptPay</span>
            <div data-qr="<?= e($payload ?? '') ?>" data-amount="<?= e(number_format((float) $b['total_amount'])) ?>"><?php if (!$payload): ?><p class="small muted">ตั้งค่าเลขพร้อมเพย์ร้านไม่ถูกต้อง โอนตามเลขบัญชีด้านข้างแทน</p><?php endif; ?></div>
            <span class="qr-name"><?= e(setting('promptpay_name')) ?><br><?= e(\App\Services\PromptPay::display(setting('promptpay_id'))) ?></span>
          </div>
          <div>
            <p class="muted small">ยอดที่ต้องชำระ</p>
            <p class="amount-due"><?= e(number_format((float) $b['total_amount'])) ?><small>บาท</small></p>
            <p class="small muted" style="margin-top:6px">สแกน QR ด้วยแอปธนาคาร ยอดเงินจะขึ้นให้อัตโนมัติ หรือโอนเข้าบัญชี <?= e(setting('bank_name')) ?> <b class="nowrap"><?= e(setting('bank_account')) ?></b>
              <button class="btn btn-xs btn-ghost" type="button" data-copy="<?= e(preg_replace('/\D/', '', setting('bank_account'))) ?>"><?= icon('copy', 'ic ic-sm') ?>คัดลอก</button></p>
            <form method="post" action="<?= e(url('/bookings/' . $b['id'] . '/slip')) ?>" enctype="multipart/form-data" style="margin-top:16px">
              <?= csrf_field() ?>
              <label class="drop" data-drop>
                <input type="file" name="slip" accept="image/jpeg,image/png,image/webp" required aria-describedby="slip-hint">
                <?= icon('upload', 'ic ic-lg') ?>
                <b data-drop-label>แนบรูปสลิปการโอน</b>
                <span class="small muted" id="slip-hint">JPG PNG หรือ WEBP ไม่เกิน 5 MB</span>
              </label>
              <img class="drop-preview" alt="ตัวอย่างสลิปที่เลือก" hidden data-drop-preview style="margin-top:10px">
              <?= field_error('slip') ?>
              <div class="row" style="margin-top:12px">
                <label class="sr-only" for="method">ช่องทางที่โอน</label>
                <select class="select" id="method" name="method" style="width:auto">
                  <option value="promptpay">โอนผ่านพร้อมเพย์</option>
                  <option value="transfer">โอนเข้าบัญชีธนาคาร</option>
                </select>
                <button class="btn" type="submit" style="flex:1"><?= icon('check') ?>ส่งสลิปให้ร้าน</button>
              </div>
            </form>
          </div>
        </div>
      </section>
      <?php elseif ($st === 'awaiting_verification'): ?>
      <section class="panel" aria-labelledby="h-wait">
        <div class="panel-title"><?= icon('hourglass') ?><h2 id="h-wait">ร้านกำลังตรวจสลิปของคุณ</h2></div>
        <p class="muted">ส่งสลิปเมื่อ <?= e($pendingSlip ? thai_datetime($pendingSlip['created_at']) : '–') ?> ปกติร้านตรวจภายใน 1–2 ชั่วโมงในเวลาทำการ เมื่อยืนยันแล้วคุณจะได้อีเมลและใบเสร็จในหน้านี้ ชุดยังล็อกไว้ให้คุณระหว่างนี้</p>
        <?php if ($pendingSlip): ?><p style="margin-top:12px"><a class="btn btn-sm btn-ghost" href="<?= e(url('/slips/' . $pendingSlip['id'])) ?>" target="_blank" rel="noopener"><?= icon('image', 'ic ic-sm') ?>ดูสลิปที่ส่ง</a></p><?php endif; ?>
      </section>
      <?php elseif ($st === 'confirmed' && $ships): ?>
      <section class="panel" aria-labelledby="h-next">
        <div class="panel-title"><?= icon('package') ?><h2 id="h-next">ร้านกำลังเตรียมพัสดุ</h2></div>
        <?php if ($lastOut && in_array($lastOut['status'], ['failed', 'lost'], true)): ?>
        <p class="form-error" style="margin:0 0 12px"><?= icon('circle-alert') ?><span><?= $lastOut['status'] === 'lost' ? 'พัสดุครั้งก่อนสูญหายระหว่างขนส่ง' : 'ส่งครั้งก่อนไม่สำเร็จ' ?>: <?= e($lastOut['note'] ?? '') ?> ร้านจะติดต่อคุณเพื่อส่งใหม่ ให้มารับที่ร้าน หรือคืนเงิน</span></p>
        <?php endif; ?>
        <p>ร้านส่งออก <b><?= e(thai_date_full($b['ship_by_date'])) ?></b> · ถึงคุณภายใน <b><?= e(thai_date($b['pickup_date'])) ?></b></p>
        <p class="muted small" style="margin-top:6px">เราแพ็กชุดพร้อมถ่ายรูปสภาพก่อนส่ง และแจ้งเลขพัสดุทางอีเมลทันทีที่ส่งออก ถ้าที่อยู่หรือเบอร์โทรผิด แจ้งร้านทาง LINE <?= e(setting('shop_line')) ?> ก่อนวันส่ง</p>
      </section>
      <?php elseif ($st === 'confirmed'): ?>
      <section class="panel" aria-labelledby="h-next">
        <div class="panel-title"><?= icon('calendar-check') ?><h2 id="h-next">นัดรับชุด <?= e(relative_day($b['pickup_date'])) ?></h2></div>
        <p><b><?= e(thai_date_full($b['pickup_date'])) ?></b> เวลา <?= e(substr((string) $b['pickup_time'], 0, 5)) ?> น. ที่ร้าน</p>
        <p class="muted small" style="margin-top:6px">นำบัตรประชาชนหรือบัตรนักศึกษามาแสดงตอนรับชุด ร้านจะให้ลองและตรวจสภาพชุดด้วยกันก่อนรับไป</p>
      </section>
      <?php elseif ($st === 'shipped'): ?>
      <section class="panel" aria-labelledby="h-ship">
        <div class="panel-title"><?= icon('truck') ?><h2 id="h-ship">ชุดกำลังเดินทางถึงคุณ</h2></div>
        <?php if ($lastOut) { View::partial('parcel', ['s' => $lastOut]); } ?>
        <p class="muted small" style="margin-top:12px">ควรถึงภายใน <?= e(thai_date_full($b['pickup_date'])) ?> เมื่อได้รับ เปิดกล่องตรวจชุด<?= $accessories ? 'และของที่มากับชุด (' . e(implode(', ', $accessories)) . ')' : '' ?> แล้วกดปุ่มด้านล่าง</p>
        <form method="post" action="<?= e(url($base . '/received')) ?>" style="margin-top:14px">
          <?= csrf_field() ?>
          <button class="btn" type="submit"><?= icon('package-check') ?>ได้รับชุดแล้ว</button>
        </form>
      </section>
      <?php elseif ($st === 'picked_up'):
          $lateDays = max(0, days_between($b['return_date'], today())); ?>
      <?php if ($ships && Fulfilment::arrivalWindowOpen($b)):
          $until = date('Y-m-d H:i:s', strtotime((string) $b['picked_up_at']) + (int) setting('arrival_report_hours') * 3600); ?>
      <section class="panel" id="arrival" aria-labelledby="h-arr">
        <div class="panel-title"><?= icon('camera') ?><h2 id="h-arr">ถ่ายรูปสภาพชุดตอนได้รับ</h2></div>
        <p class="muted small">ส่งรูปภายใน <?= e(thai_datetime($until)) ?> ถ้าพบคราบ รอยขาด หรือของขาด เขียนบอกด้วย ร้านจะไม่คิดค่าเสียหายจุดที่มีอยู่ก่อนถึงมือคุณ</p>
        <form method="post" action="<?= e(url($base . '/arrival')) ?>" enctype="multipart/form-data" style="margin-top:14px">
          <?= csrf_field() ?>
          <?php View::partial('photo-input', ['id' => 'arrival-photos', 'label' => 'แนบรูปชุดทั้งตัว และจุดที่พบปัญหา', 'hint' => 'JPG PNG หรือ WEBP']); ?>
          <div class="field" style="margin-top:12px">
            <label class="label" for="arrival_note">พบปัญหาอะไรไหม <span class="opt">(เว้นว่างได้ถ้าชุดเรียบร้อย)</span></label>
            <textarea class="textarea" id="arrival_note" name="arrival_note" maxlength="500" placeholder="เช่น มีคราบเล็กที่ชายกระโปรงด้านหลัง เข็มกลัดไม่ได้มากับกล่อง" <?= invalid_attr('arrival_note') ?>><?= e(old('arrival_note')) ?></textarea>
            <?= field_error('arrival_note') ?>
          </div>
          <button class="btn btn-ghost" type="submit" style="margin-top:12px"><?= icon('send') ?>ส่งรูปสภาพชุดให้ร้าน</button>
        </form>
      </section>
      <?php elseif ($b['arrival_reported_at']): ?>
      <p class="small muted" style="margin-bottom:14px"><?= icon('circle-check', 'ic ic-sm') ?> ส่งรูปสภาพตอนได้รับแล้วเมื่อ <?= e(thai_datetime($b['arrival_reported_at'])) ?><?= $b['arrival_note'] ? ' · ' . e($b['arrival_note']) : '' ?></p>
      <?php endif; ?>
      <section class="panel" id="return" aria-labelledby="h-ret">
        <div class="panel-title"><?= icon('rotate-ccw') ?><h2 id="h-ret"><?= $lateDays ? 'เลยกำหนดคืนชุด ' . $lateDays . ' วัน' : ($posts ? 'ส่งชุดคืน ' : 'คืนชุด ') . e(relative_day($b['return_date'])) ?></h2></div>
        <?php if ($posts): ?>
        <p>ฝากส่งพัสดุคืนภายใน <b><?= e(thai_date_full($b['return_date'])) ?></b> ร้านนับวันที่ฝากส่งเป็นวันคืน</p>
        <ol class="howto-list">
          <li>พับชุดใส่ถุงผ้าที่มากับกล่อง ไม่ต้องซักเอง<?= $accessories ? ' ใส่ของที่มากับชุดให้ครบ: ' . e(implode(', ', $accessories)) : '' ?></li>
          <li>ปิดกล่องเดิม แล้วติดใบปะหน้าส่งคืนที่ร้านแนบไว้ (ร้านจ่ายค่าส่งแล้ว)</li>
          <li>ฝากส่งที่<?= e(Shipping::carrierName($lastOut['carrier'] ?? 'thaipost')) ?>หรือจุดรับพัสดุ เก็บใบรับฝากไว้</li>
          <li>กรอกเลขพัสดุด้านล่างและแนบรูปใบรับฝาก เพื่อหยุดนับค่าปรับ</li>
        </ol>
        <?php else: ?>
        <p>กำหนดคืน <b><?= e(thai_date_full($b['return_date'])) ?></b> ก่อน <?= e(substr((string) $b['return_time'], 0, 5)) ?> น.</p>
        <?php endif; ?>
        <p class="muted small" style="margin-top:6px"><?= $lateDays ? 'ค่าปรับตอนนี้ ' . e(money($lateDays * (float) $b['late_fee_per_day'])) . ' (วันละ ' . e(money($b['late_fee_per_day'])) . ') หักจากเงินมัดจำ รีบ' . ($posts ? 'ส่งชุดคืน' : 'นำชุดมาคืน') . 'นะคะ' : 'คืนตรงเวลาและชุดสภาพดี รับเงินมัดจำคืนเต็ม ' . e(money($b['deposit_amount'])) ?></p>
        <?php $postForm = static function () use ($base): void { ?>
        <form method="post" action="<?= e(url($base . '/post-back')) ?>" enctype="multipart/form-data" style="margin-top:14px">
          <?= csrf_field() ?>
          <div class="grid-2">
            <div class="field">
              <label class="label" for="carrier">ส่งกับ</label>
              <select class="select" id="carrier" name="carrier" <?= invalid_attr('carrier') ?>>
                <?php foreach (Shipping::CARRIERS as $k => $c): ?><option value="<?= e($k) ?>" <?= old('carrier', 'thaipost') === $k ? 'selected' : '' ?>><?= e($c[0]) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label class="label" for="posted_on">วันที่ฝากส่ง</label>
              <input class="input" type="date" id="posted_on" name="posted_on" value="<?= e(old('posted_on', today())) ?>" max="<?= e(today()) ?>" required <?= invalid_attr('posted_on') ?>>
              <?= field_error('posted_on') ?>
            </div>
          </div>
          <div class="field">
            <label class="label" for="tracking_no">เลขพัสดุ <span class="opt">(เมสเซนเจอร์เว้นว่างได้)</span></label>
            <input class="input num" id="tracking_no" name="tracking_no" maxlength="40" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="เช่น EF123456789TH" value="<?= e(old('tracking_no')) ?>" <?= invalid_attr('tracking_no') ?>>
            <?= field_error('tracking_no') ?>
          </div>
          <div style="margin-top:12px"><?php View::partial('photo-input', ['id' => 'dropoff-photos', 'label' => 'แนบรูปใบรับฝากพัสดุ', 'hint' => 'ไม่บังคับ แต่ช่วยยืนยันวันฝากส่ง']); ?></div>
          <button class="btn" type="submit" style="margin-top:12px"><?= icon('send') ?>แจ้งร้านว่าส่งชุดคืนแล้ว</button>
        </form>
        <?php }; ?>
        <?php if ($posts): $postForm(); elseif (setting('return_post_enabled') === '1' && setting('ship_enabled') === '1'): ?>
        <details style="margin-top:14px">
          <summary class="small" style="cursor:pointer">มาคืนที่ร้านไม่ได้? ส่งพัสดุคืนเอง (ออกค่าส่งเอง)</summary>
          <?php $postForm(); ?>
        </details>
        <?php endif; ?>
      </section>
      <?php elseif ($st === 'returning'): ?>
      <section class="panel" aria-labelledby="h-back">
        <div class="panel-title"><?= icon('truck') ?><h2 id="h-back"><?= $lastBack && $lastBack['status'] === 'received' ? 'ร้านได้รับชุดคืนแล้ว กำลังตรวจสภาพ' : ($lastBack && $lastBack['status'] === 'lost' ? 'พัสดุส่งคืนสูญหาย' : 'ชุดกำลังเดินทางกลับร้าน') ?></h2></div>
        <?php if ($lastBack) { View::partial('parcel', ['s' => $lastBack]); } ?>
        <p class="muted small" style="margin-top:12px"><?= $lastBack && $lastBack['status'] === 'lost'
            ? 'คุณฝากส่งพร้อมเลขพัสดุแล้ว จึงไม่ต้องรับผิดชอบเรื่องคืนช้า ร้านกำลังติดตามและเคลมกับขนส่ง จะแจ้งผลและยอดคืนมัดจำ'
            : 'ร้านจะตรวจสภาพชุดภายใน 1 วันทำการหลังได้รับ แล้วโอนเงินมัดจำคืนพร้อมใบสรุปการคืนชุดทางอีเมล' ?></p>
      </section>
      <?php elseif ($st === 'returned'): ?>
      <section class="panel" aria-labelledby="h-done">
        <div class="panel-title"><?= icon('circle-check') ?><h2 id="h-done">คืนชุดเรียบร้อย</h2></div>
        <dl class="kv">
          <dt>คืนเมื่อ</dt><dd><?= e(thai_datetime($b['returned_at'])) ?></dd>
          <dt>คืนช้า</dt><dd><?= (int) $b['late_days'] ? (int) $b['late_days'] . ' วัน · ปรับ ' . e(money($b['late_fee'])) : 'ตรงเวลา' ?></dd>
          <?php if ((float) $b['damage_fee'] > 0): ?><dt>ค่าเสียหาย</dt><dd><?= e(money($b['damage_fee'])) ?><?= $damage ? '' : ' · ' . e($b['damage_note']) ?></dd><?php endif; ?>
          <dt>ได้มัดจำคืน</dt><dd><?= e(money($b['deposit_refund'])) ?></dd>
          <?php if ((float) $b['extra_charge'] > 0): ?><dt>ชำระเพิ่ม</dt><dd><?= e(money($b['extra_charge'])) ?></dd><?php endif; ?>
          <?php if ((float) $b['damage_waived'] > 0): ?><dt>ร้านลดให้</dt><dd><?= e(money($b['damage_waived'])) ?> (คืนเงินแล้ว)</dd><?php endif; ?>
        </dl>
        <?php if ($damage): ?>
        <table class="damage-table" style="margin-top:16px">
          <caption class="sr-only">รายการค่าเสียหาย</caption>
          <thead><tr><th scope="col">ที่พบตอนตรวจ</th><th scope="col" class="r">บาท</th></tr></thead>
          <tbody>
            <?php foreach ($damage as $d): ?>
            <tr><td><?= e($d['label']) ?><?= (int) $d['qty'] > 1 ? ' ×' . (int) $d['qty'] : '' ?><?php if ($d['note']): ?><br><span class="small muted"><?= e($d['note']) ?></span><?php endif; ?></td><td class="r num"><?= e(money($d['amount'], false)) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </section>
      <?php if ($b['dispute_at']): ?>
      <section class="panel" id="dispute" aria-labelledby="h-disp">
        <div class="spread" style="margin-bottom:10px">
          <div class="panel-title" style="margin:0"><?= icon('scale') ?><h2 id="h-disp">ข้อโต้แย้งค่าเสียหาย</h2></div>
          <?= Status::badge(Status::DISPUTE, $b['dispute_resolved_at'] ? 'resolved' : 'open') ?>
        </div>
        <p class="small muted">คุณแจ้งเมื่อ <?= e(thai_datetime($b['dispute_at'])) ?></p>
        <p style="margin-top:4px"><?= e($b['dispute_note']) ?></p>
        <?php if ($b['dispute_resolved_at']): ?>
        <p style="margin-top:12px"><b>คำตอบจากร้าน</b> <span class="small muted"><?= e(thai_datetime($b['dispute_resolved_at'])) ?></span><br><?= e($b['dispute_reply']) ?></p>
        <?php else: ?>
        <p class="small muted" style="margin-top:12px">ร้านจะทบทวนรูปตอนส่งมอบ ตอนได้รับ และตอนตรวจคืน แล้วตอบภายใน 2 วันทำการ</p>
        <?php endif; ?>
      </section>
      <?php elseif ($dispute[0]): ?>
      <details class="panel" id="dispute" <?= form_errors() ? 'open' : '' ?>>
        <summary style="cursor:pointer;font-weight:600"><?= icon('scale', 'ic ic-sm') ?> ไม่เห็นด้วยกับค่าเสียหาย</summary>
        <p class="small muted" style="margin-top:10px">แจ้งได้ครั้งเดียวภายใน <?= e(thai_datetime(Inspection::disputeDeadline($b))) ?> ร้านจะทบทวนจากรูปทุกขั้นตอน ถ้ามีรูปตอนได้รับชุดหรือรูปอื่นที่ช่วยยืนยัน แนบมาด้วย</p>
        <form method="post" action="<?= e(url($base . '/dispute')) ?>" enctype="multipart/form-data" style="margin-top:12px">
          <?= csrf_field() ?>
          <div class="field">
            <label class="label" for="dispute_note">เหตุผล</label>
            <textarea class="textarea" id="dispute_note" name="dispute_note" maxlength="500" required minlength="10" placeholder="เช่น คราบนี้มีอยู่ก่อนแล้ว ดูได้จากรูปที่ส่งตอนได้รับชุด" <?= invalid_attr('dispute_note') ?>><?= e(old('dispute_note')) ?></textarea>
            <?= field_error('dispute_note') ?>
          </div>
          <div style="margin-top:12px"><?php View::partial('photo-input', ['id' => 'dispute-photos', 'label' => 'แนบรูปประกอบ', 'hint' => 'ไม่บังคับ']); ?></div>
          <button class="btn btn-ghost" type="submit" style="margin-top:12px"><?= icon('send') ?>ส่งข้อโต้แย้งให้ร้าน</button>
        </form>
      </details>
      <?php endif; ?>
      <?php else: ?>
      <section class="panel" aria-labelledby="h-closed">
        <div class="panel-title"><?= icon('calendar-x') ?><h2 id="h-closed"><?= $st === 'expired' ? 'การจองหมดเวลาชำระ' : 'การจองถูกยกเลิก' ?></h2></div>
        <p class="muted"><?= $st === 'expired' ? 'ไม่ได้ชำระเงินภายในเวลาที่กำหนด ระบบจึงปล่อยชุดให้คนอื่นจอง ถ้ายังต้องการชุดนี้ จองใหม่ได้เลยถ้าวันยังว่าง' : e('เหตุผล: ' . ($b['cancel_reason'] ?: '–')) ?></p>
        <?php if ($st === 'cancelled' && (float) $b['refund_amount'] > 0): ?><p style="margin-top:8px">ร้านคืนเงิน <?= e(money($b['refund_amount'])) ?></p><?php endif; ?>
        <p style="margin-top:12px"><a class="btn btn-sm btn-ghost" href="<?= e(url('/dress/' . rawurlencode($b['costume_code']), ['date' => $b['event_date'] >= today() ? $b['event_date'] : null])) ?>">ดูชุดนี้อีกครั้ง</a></p>
      </section>
      <?php endif; ?>

      <?php if (array_filter($photos) || ($shipments && !in_array($st, ['shipped', 'returning'], true))): ?>
      <section class="panel" aria-labelledby="h-cond">
        <div class="panel-title"><?= icon('camera') ?><h2 id="h-cond">พัสดุและรูปสภาพชุด</h2></div>
        <?php if (!in_array($st, ['shipped', 'returning'], true)): foreach ($shipments as $s): ?><?php View::partial('parcel', ['s' => $s]); ?><?php endforeach; endif; ?>
        <?php View::partial('photo-strip', ['photos' => $photos]); ?>
      </section>
      <?php endif; ?>

      <?php if ($receipts): ?>
      <section class="panel" aria-labelledby="h-rc">
        <div class="panel-title"><?= icon('receipt') ?><h2 id="h-rc">ใบเสร็จ</h2></div>
        <ul class="mail-list">
          <?php foreach ($receipts as $r): ?>
          <li><a href="<?= e(url('/receipts/' . $r['id'])) ?>" target="_blank" rel="noopener">
            <span><b class="num"><?= e($r['receipt_no']) ?></b> · <?= e(Status::RECEIPT[$r['kind']] ?? $r['kind']) ?><br><span class="small muted"><?= e(thai_datetime($r['issued_at'])) ?></span></span>
            <span class="num"><?= e(money(abs((float) $r['amount']))) ?> <?= icon('printer', 'ic ic-sm') ?></span>
          </a></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <section class="panel" aria-labelledby="h-tl">
        <div class="panel-title"><?= icon('list-checks') ?><h2 id="h-tl">ประวัติการจอง</h2></div>
        <ol class="timeline">
          <?php foreach ($events as $ev): ?>
          <li><?= e($ev['message']) ?><time datetime="<?= e($ev['created_at']) ?>"><?= e(thai_datetime($ev['created_at'])) ?></time></li>
          <?php endforeach; ?>
        </ol>
      </section>

      <?php if ($cancel[0]): ?>
      <details class="panel">
        <summary style="cursor:pointer;font-weight:600">ยกเลิกการจองนี้</summary>
        <form method="post" action="<?= e(url('/bookings/' . $b['id'] . '/cancel')) ?>" style="margin-top:12px" data-confirm="ยืนยันยกเลิกการจอง <?= e($b['booking_no']) ?>? ชุดจะถูกปล่อยให้คนอื่นจองทันที">
          <?= csrf_field() ?>
          <div class="field">
            <label class="label" for="reason">เหตุผล <span class="opt">(ไม่บังคับ)</span></label>
            <input class="input" id="reason" name="reason" maxlength="200" placeholder="เช่น เลื่อนวันงาน ได้ชุดแล้ว">
          </div>
          <p class="hint" style="margin-top:8px"><?= $st === 'pending_payment' ? 'ยังไม่ได้ชำระเงิน ยกเลิกได้ทันที' : 'ร้านคืนเงินเต็มจำนวน' . ($ships ? 'รวมค่าจัดส่ง' : '') . 'ภายใน 3 วันทำการ ไปยังบัญชีที่โอนมา' ?></p>
          <button class="btn btn-danger" type="submit" style="margin-top:12px"><?= icon('ban') ?>ยกเลิกการจอง</button>
        </form>
      </details>
      <?php elseif (in_array($st, ['awaiting_verification', 'confirmed'], true)): ?>
      <p class="small muted" style="margin-top:14px"><?= icon('info', 'ic ic-sm') ?> <?= e($cancel[1]) ?> โทร <?= e(setting('shop_phone')) ?></p>
      <?php endif; ?>
    </div>

    <aside class="booking-aside">
      <div class="panel">
        <a class="mini-post" href="<?= e(url('/dress/' . rawurlencode($b['costume_code']))) ?>" style="text-decoration:none">
          <img src="<?= e(mini($b['image'])) ?>" alt="">
          <div><b><?= e($b['costume_name']) ?></b><span class="small muted"><?= e($b['costume_code']) ?> · <?= e($b['size_label'] === 'FREE' ? 'ฟรีไซส์' : 'ไซส์ ' . $b['size_label']) ?></span></div>
        </a>
        <dl class="kv" style="margin-top:16px">
          <dt>วันงาน</dt><dd><?= e(thai_date($b['event_date'])) ?></dd>
          <?php foreach (BookingService::handoverLines($b) as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= nl2br(e($v)) ?></dd><?php endforeach; ?>
          <dt>ระยะเช่า</dt><dd><?= (int) $b['rental_days'] ?> วัน</dd>
        </dl>
        <div class="strip">
          <div class="strip-row"><span>ค่าเช่า</span><span><?= e(money($b['rent_amount'])) ?></span></div>
          <?php if ((float) $b['extra_amount'] > 0): ?><div class="strip-row"><span>วันเพิ่ม</span><span><?= e(money($b['extra_amount'])) ?></span></div><?php endif; ?>
          <?php if ((float) $b['delivery_fee'] > 0): ?><div class="strip-row"><span>ค่าจัดส่ง</span><span><?= e(money($b['delivery_fee'])) ?></span></div><?php endif; ?>
          <?php if ((float) $b['return_fee'] > 0): ?><div class="strip-row"><span>ค่าส่งคืน</span><span><?= e(money($b['return_fee'])) ?></span></div><?php endif; ?>
          <div class="strip-row"><span>เงินมัดจำ</span><span><?= e(money($b['deposit_amount'])) ?></span></div>
          <div class="strip-row strip-total"><span>รวม</span><span class="num"><?= e(number_format((float) $b['total_amount'])) ?> <small>บาท</small></span></div>
        </div>
        <?php if ($b['note']): ?><p class="small muted" style="margin-top:12px">ฝากถึงร้าน: <?= e($b['note']) ?></p><?php endif; ?>
      </div>
    </aside>
  </div>
</div>
<?php if ($st === 'pending_payment'): ?><script src="<?= e(asset('js/qrcode.js')) ?>"></script><?php endif; ?>
