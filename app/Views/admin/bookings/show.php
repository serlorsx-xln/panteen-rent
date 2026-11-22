<?php
/** @var array $b @var array $payments @var array $receipts @var array $events @var ?array $preview @var array $conflicts @var float $paidTotal
 *  @var array $shipments @var array $photos @var array $damage @var ?array $inspection */
use App\Core\View;
use App\Services\BookingService;
use App\Services\Inspection;
use App\Services\Shipping;
use App\Services\Status;

$st = $b['status'];
$lateNow = $st === 'picked_up' ? max(0, days_between($b['return_date'], today())) : 0;
$ships = BookingService::ships($b);
$base = '/admin/bookings/' . $b['id'];
$lastOut = null;
$lastBack = null;
foreach ($shipments as $s) {
    if ($s['direction'] === 'out') { $lastOut = $s; } else { $lastBack = $s; }
}
$canInspect = $st === 'picked_up' || ($st === 'returning' && $lastBack && in_array($lastBack['status'], ['received', 'lost'], true));
$carrierSelect = static function (string $id, string $default = 'thaipost', bool $messenger = true): void { ?>
  <select class="select" id="<?= e($id) ?>" name="carrier">
    <?php foreach (Shipping::CARRIERS as $k => $c): if (!$messenger && $k === 'messenger') { continue; } ?><option value="<?= e($k) ?>" <?= $k === $default ? 'selected' : '' ?>><?= e($c[0]) ?></option><?php endforeach; ?>
  </select>
<?php };
?>
<nav class="crumbs"><a href="<?= e(url('/admin/bookings')) ?>"><?= icon('arrow-left', 'ic ic-sm') ?> การจองทั้งหมด</a></nav>
<div class="page-head">
  <div>
    <h1>การจอง <span class="num"><?= e($b['booking_no']) ?></span></h1>
    <p class="muted"><?= $b['channel'] === 'counter' ? 'จองที่หน้าร้าน' : 'ลูกค้าจองผ่านเว็บไซต์' ?> · <?= e(thai_datetime($b['created_at'])) ?></p>
  </div>
  <?= Status::badge(Status::BOOKING, $st) ?>
</div>
<?php \App\Core\View::partial('steps', ['b' => $b]); ?>

<div class="detail" style="margin-top:18px">
  <div>
    <?php if ($conflicts): ?>
    <div class="conflict"><?= icon('circle-alert') ?><span>ชุดนี้ชนกับการจอง <?= e(implode(', ', array_column($conflicts, 'booking_no'))) ?> ในช่วงเดียวกัน ตรวจและเลื่อนวันก่อนยืนยัน</span></div>
    <?php endif; ?>

    <div class="box">
      <div class="mini-post" style="grid-template-columns:96px minmax(0, 1fr)">
        <img src="<?= e(mini($b['image'])) ?>" alt="" style="width:96px;height:96px">
        <div>
          <b><?= e($b['costume_code']) ?> · <?= e($b['costume_name']) ?></b>
          <span class="small muted"><?= e($b['category_name']) ?> · <?= e($b['size_label'] === 'FREE' ? 'ฟรีไซส์' : 'ไซส์ ' . $b['size_label']) ?> · สี<?= e($b['color_name']) ?></span>
          <p class="small" style="margin-top:4px"><a href="<?= e(url('/admin/costumes/' . $b['costume_id'])) ?>">ข้อมูลชุด</a> · <a href="<?= e(url('/dress/' . rawurlencode($b['costume_code']))) ?>" target="_blank">ดูในหน้าร้าน</a></p>
        </div>
      </div>
      <dl class="kv" style="margin-top:16px">
        <dt>วันงาน</dt><dd><?= e(thai_date_full($b['event_date'])) ?></dd>
        <dt>รับชุด</dt><dd><?= e(Shipping::DELIVERY[$b['delivery_method']]) ?> · <?= e(thai_date($b['pickup_date'])) ?><?= $b['delivery_method'] === 'pickup' ? ' · ' . e(substr((string) $b['pickup_time'], 0, 5)) . ' น.' : '' ?><?= $b['picked_up_at'] ? ' <span class="faint">(' . ($ships ? 'ถึงลูกค้า ' : 'รับจริง ') . e(thai_datetime($b['picked_up_at'])) . ')</span>' : '' ?></dd>
        <?php if ($b['ship_by_date']): ?><dt>ต้องส่งออก</dt><dd><?= e(thai_date($b['ship_by_date'])) ?> ก่อน <?= e(setting('ship_cutoff')) ?> น.<?= $st === 'confirmed' && $b['ship_by_date'] < today() ? ' <span class="late">ส่งช้ากว่ากำหนด</span>' : '' ?></dd><?php endif; ?>
        <dt>คืนชุด</dt><dd><?= e(Shipping::RETURN[$b['return_method']]) ?> · <?= e(thai_date($b['return_date'])) ?><?= $b['return_method'] === 'store' ? ' · ' . e(substr((string) $b['return_time'], 0, 5)) . ' น.' : '' ?><?= $b['returned_at'] ? ' <span class="faint">(' . ($b['return_method'] === 'post' ? 'ร้านได้รับ ' : 'คืนจริง ') . e(thai_datetime($b['returned_at'])) . ')</span>' : '' ?><?= $lateNow ? ' <span class="late">เลยกำหนด ' . $lateNow . ' วัน</span>' : '' ?></dd>
        <?php if ($b['ship_address']): ?><dt>ที่อยู่จัดส่ง</dt><dd style="font-weight:400"><?= e($b['ship_recipient']) ?> · <span class="num"><?= e(phone_fmt((string) $b['ship_phone'])) ?></span><br><?= nl2br(e($b['ship_address'])) ?> <span class="faint">(<?= e(Shipping::ZONE[$b['ship_zone']] ?? '') ?>)</span></dd><?php endif; ?>
        <dt>ล็อกชุด</dt><dd><?= e(thai_date($b['block_from'])) ?> – <?= e(thai_date($b['block_until'])) ?> <span class="faint">(รวมวันขนส่งและวันซัก)</span></dd>
        <dt>ระยะเช่า</dt><dd><?= (int) $b['rental_days'] ?> วัน</dd>
        <?php if ($st === 'pending_payment' && $b['hold_expires_at']): ?><dt>รอชำระถึง</dt><dd><?= e(thai_datetime($b['hold_expires_at'])) ?></dd><?php endif; ?>
        <?php if ($b['note']): ?><dt>ลูกค้าฝากไว้</dt><dd style="font-weight:400"><?= e($b['note']) ?></dd><?php endif; ?>
        <?php if ($b['staff_note']): ?><dt>บันทึกร้าน</dt><dd style="font-weight:400"><?= e($b['staff_note']) ?></dd><?php endif; ?>
        <?php if ($st === 'cancelled'): ?><dt>เหตุผลยกเลิก</dt><dd style="font-weight:400"><?= e($b['cancel_reason'] ?: '–') ?><?= (float) $b['refund_amount'] > 0 ? ' · คืนเงิน ' . e(money($b['refund_amount'])) : '' ?></dd><?php endif; ?>
      </dl>
    </div>

    <div class="box" style="margin-top:14px">
      <div class="box-head"><h2>การเงิน</h2></div>
      <div class="tablewrap" style="border:0">
      <table class="table">
        <tbody>
          <tr><td>ค่าเช่า</td><td class="r num"><?= e(money($b['rent_amount'])) ?></td></tr>
          <?php if ((float) $b['extra_amount'] > 0): ?><tr><td>วันเพิ่ม</td><td class="r num"><?= e(money($b['extra_amount'])) ?></td></tr><?php endif; ?>
          <?php if ((float) $b['delivery_fee'] > 0): ?><tr><td>ค่าจัดส่ง</td><td class="r num"><?= e(money($b['delivery_fee'])) ?></td></tr><?php endif; ?>
          <?php if ((float) $b['return_fee'] > 0): ?><tr><td>ค่าส่งคืน</td><td class="r num"><?= e(money($b['return_fee'])) ?></td></tr><?php endif; ?>
          <tr><td>เงินมัดจำ</td><td class="r num"><?= e(money($b['deposit_amount'])) ?></td></tr>
          <tr><td><b>ยอดที่ต้องชำระ</b></td><td class="r num"><b><?= e(money($b['total_amount'])) ?></b></td></tr>
          <tr><td>ชำระแล้ว</td><td class="r num"><?= e(money($paidTotal)) ?></td></tr>
          <?php if ($st === 'returned'): ?>
          <tr><td>ค่าปรับคืนช้า (<?= (int) $b['late_days'] ?> วัน)</td><td class="r num"><?= e(money($b['late_fee'])) ?></td></tr>
          <tr><td>ค่าเสียหาย<?= $b['damage_note'] ? ' · ' . e($b['damage_note']) : '' ?></td><td class="r num"><?= e(money($b['damage_fee'])) ?></td></tr>
          <?php if ((float) $b['damage_waived'] > 0): ?><tr><td>ลดค่าเสียหายหลังลูกค้าโต้แย้ง (คืนเงินแล้ว)</td><td class="r num">−<?= e(money($b['damage_waived'])) ?></td></tr><?php endif; ?>
          <tr><td>คืนมัดจำให้ลูกค้า</td><td class="r num"><?= e(money($b['deposit_refund'])) ?></td></tr>
          <?php if ((float) $b['extra_charge'] > 0): ?><tr><td>เก็บเพิ่มจากลูกค้า</td><td class="r num"><?= e(money($b['extra_charge'])) ?></td></tr><?php endif; ?>
          <?php endif; ?>
        </tbody>
      </table>
      </div>
      <?php if ($payments): ?>
      <h3 style="font-size:14px;margin:16px 0 8px">รายการรับ–จ่ายเงิน</h3>
      <ul class="rows">
        <?php foreach ($payments as $p): ?>
        <li style="grid-template-columns:1fr auto auto">
          <span><b><?= e(Status::PAYMENT_KIND[$p['kind']] ?? $p['kind']) ?></b> · <?= e(Status::PAYMENT_METHOD[$p['method']] ?? $p['method']) ?>
            <div class="sub"><?= e(thai_datetime($p['created_at'])) ?><?= $p['reviewer_first'] ? ' · ตรวจโดย ' . e($p['reviewer_nick'] ?: $p['reviewer_first']) : '' ?><?= $p['reject_reason'] ? ' · ' . e($p['reject_reason']) : '' ?></div></span>
          <span class="num"><?= e(money($p['amount'])) ?><?php if ($p['slip_path']): ?> · <a href="<?= e(url('/slips/' . $p['id'])) ?>" target="_blank">สลิป</a><?php endif; ?></span>
          <?= Status::badge(Status::PAYMENT, $p['status']) ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if ($receipts): ?>
      <p class="row" style="margin-top:12px">
        <?php foreach ($receipts as $r): ?>
          <a class="btn btn-sm btn-ghost" href="<?= e(url('/receipts/' . $r['id'])) ?>" target="_blank"><?= icon('printer', 'ic ic-sm') ?><?= e($r['receipt_no']) ?> · <?= e(Status::RECEIPT[$r['kind']] ?? $r['kind']) ?></a>
        <?php endforeach; ?>
      </p>
      <?php endif; ?>
    </div>

    <?php if ($inspection || $damage): ?>
    <div class="box" style="margin-top:14px">
      <div class="box-head"><h2>ผลตรวจสภาพตอนรับคืน</h2><?php if ($b['inspected_at']): ?><span class="small muted"><?= e(thai_datetime($b['inspected_at'])) ?></span><?php endif; ?></div>
      <?php if ($inspection): ?>
      <ul class="checks">
        <?php foreach (Inspection::CHECKS as $k => $label): $ok = in_array($k, $inspection['checks'] ?? [], true); ?>
        <li class="<?= $ok ? 'is-ok' : 'is-bad' ?>"><?= icon($ok ? 'check' : 'x', 'ic ic-sm') ?><span><?= e($label) ?></span><span class="sr-only"><?= $ok ? 'ผ่าน' : 'พบปัญหา' ?></span></li>
        <?php endforeach; ?>
      </ul>
      <p class="small" style="margin-top:10px">หลังตรวจ: <b><?= e(Inspection::OUTCOME[$inspection['outcome'] ?? 'ready'] ?? '') ?></b><?= !empty($inspection['parcel']) ? ' · พัสดุคืน: ' . e(Status::SHIPMENT[$inspection['parcel']][0] ?? '') : '' ?><?= !empty($inspection['note']) ? ' · ' . e($inspection['note']) : '' ?></p>
      <?php endif; ?>
      <?php if ($damage): ?>
      <div class="tablewrap" style="border:0;margin-top:10px"><table class="table">
        <thead><tr><th scope="col">รายการ</th><th scope="col" class="r">จำนวน</th><th scope="col" class="r">บาท</th></tr></thead>
        <tbody><?php foreach ($damage as $d): ?><tr><td><?= e($d['label']) ?><?= $d['note'] ? '<div class="sub small muted">' . e($d['note']) . '</div>' : '' ?></td><td class="r num"><?= (int) $d['qty'] ?></td><td class="r num"><?= e(money($d['amount'], false)) ?></td></tr><?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($b['dispute_at']): ?>
    <div class="box" id="dispute" style="margin-top:14px">
      <div class="box-head"><h2>ลูกค้าโต้แย้งค่าเสียหาย</h2><?= Status::badge(Status::DISPUTE, $b['dispute_resolved_at'] ? 'resolved' : 'open') ?></div>
      <p class="small muted"><?= e(thai_datetime($b['dispute_at'])) ?></p>
      <p style="margin-top:4px"><?= e($b['dispute_note']) ?></p>
      <?php if ($b['dispute_resolved_at']): ?>
      <p class="small" style="margin-top:10px"><b>ร้านตอบ</b> <?= e(thai_datetime($b['dispute_resolved_at'])) ?> · <?= e($b['dispute_reply']) ?><?= (float) $b['damage_waived'] > 0 ? ' · ลดให้ ' . e(money($b['damage_waived'])) : '' ?></p>
      <?php else: $maxWaive = (float) $b['damage_fee'] - (float) $b['damage_waived']; ?>
      <form method="post" action="<?= e(url($base . '/dispute')) ?>" style="margin-top:12px" data-confirm="ส่งคำตอบให้ลูกค้า? ถ้าลดค่าเสียหาย ระบบบันทึกการคืนเงินและออกใบปรับปรุงทันที">
        <?= csrf_field() ?>
        <p class="small muted">ดูรูปตอนส่งมอบ ตอนลูกค้าได้รับ และตอนตรวจคืนด้านล่างก่อนตัดสิน</p>
        <div class="field"><label class="label" for="d-reply">คำตอบถึงลูกค้า</label><textarea class="textarea" id="d-reply" name="reply" required maxlength="500" rows="3" placeholder="เช่น ตรวจรูปตอนส่งมอบแล้ว พบว่าจุดนี้มีอยู่ก่อน ร้านขอคืนค่าเสียหายส่วนนี้"></textarea></div>
        <div class="field"><label class="label" for="d-waive">ลดค่าเสียหาย (บาท)</label><input class="input num" id="d-waive" name="waive" inputmode="decimal" value="0"><span class="hint">0 = ยืนยันตามเดิม · ลดได้สูงสุด <?= e(money($maxWaive)) ?></span></div>
        <button class="btn" type="submit" style="margin-top:10px"><?= icon('scale') ?>ตอบและปิดข้อโต้แย้ง</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($shipments || $ships || array_filter($photos) || $b['arrival_reported_at']): ?>
    <div class="box" id="parcels" style="margin-top:14px">
      <div class="box-head"><h2>พัสดุและรูปสภาพชุด</h2><?php if ($ships || $b['return_method'] === 'post'): ?><a href="<?= e(url($base . '/label')) ?>" target="_blank"><?= icon('printer', 'ic ic-sm') ?> ใบปะหน้าและใบแพ็กของ</a><?php endif; ?></div>
      <?php foreach ($shipments as $s) { View::partial('parcel', ['s' => $s]); } ?>
      <?php if (!$shipments): ?><p class="small muted">ยังไม่มีพัสดุ</p><?php endif; ?>
      <?php if ($b['arrival_reported_at']): ?>
      <p class="small" style="margin-top:10px"><b>ลูกค้าแจ้งสภาพตอนได้รับ</b> <?= e(thai_datetime($b['arrival_reported_at'])) ?> · <?= $b['arrival_note'] ? '<span class="late">' . e($b['arrival_note']) . '</span>' : 'ไม่มีปัญหา' ?></p>
      <?php endif; ?>
      <?php View::partial('photo-strip', ['photos' => $photos]); ?>
      <details style="margin-top:12px">
        <summary class="small" style="cursor:pointer;font-weight:600">เพิ่มรูปสภาพชุด</summary>
        <form method="post" action="<?= e(url($base . '/photos')) ?>" enctype="multipart/form-data" style="margin-top:10px">
          <?= csrf_field() ?>
          <div class="field"><label class="label" for="ph-stage">ขั้นตอน</label>
            <select class="select" id="ph-stage" name="stage"><?php foreach (['handover', 'return'] as $k): ?><option value="<?= $k ?>" <?= ($k === 'return') === in_array($st, ['picked_up', 'returning', 'returned'], true) ? 'selected' : '' ?>><?= e(Status::PHOTO_STAGE[$k]) ?></option><?php endforeach; ?></select></div>
          <div style="margin-top:10px"><?php View::partial('photo-input', ['id' => 'staff-photos', 'label' => 'เลือกรูป', 'hint' => 'ถ่ายทั้งตัวชุดและจุดสำคัญ', 'required' => true]); ?></div>
          <button class="btn btn-ghost btn-sm" type="submit" style="margin-top:10px"><?= icon('upload', 'ic ic-sm') ?>บันทึกรูป</button>
        </form>
      </details>
    </div>
    <?php endif; ?>

    <div class="box" style="margin-top:14px">
      <div class="box-head"><h2>ประวัติ</h2></div>
      <ol class="timeline">
        <?php foreach ($events as $ev): ?>
        <li><?= e($ev['message']) ?><time datetime="<?= e($ev['created_at']) ?>"><?= e(thai_datetime($ev['created_at'])) ?><?= $ev['first_name'] ? ' · ' . e($ev['nickname'] ?: $ev['first_name']) . ($ev['role'] !== 'member' ? ' (' . e(Status::ROLE[$ev['role']]) . ')' : '') : ' · ระบบ' ?></time></li>
        <?php endforeach; ?>
      </ol>
    </div>
  </div>

  <aside class="actions" aria-label="การดำเนินการกับการจอง">
    <div class="box">
      <div class="who">
        <span class="small muted">ลูกค้า</span>
        <b><a href="<?= e(url('/admin/members/' . $b['user_id'])) ?>"><?= e(full_name($b)) ?></a><?= $b['nickname'] ? ' (' . e($b['nickname']) . ')' : '' ?></b>
        <span class="num"><a href="tel:<?= e(phone_fmt($b['phone'])) ?>"><?= e(phone_fmt($b['phone'])) ?></a><?= $b['line_id'] ? ' · LINE ' . e($b['line_id']) : '' ?></span>
        <span class="small muted"><?= e($b['email']) ?></span>
      </div>
    </div>

    <?php if (in_array($st, ['pending_payment', 'awaiting_verification'], true)): ?>
    <div class="action">
      <h3>รับชำระที่หน้าร้าน</h3>
      <p class="small muted">ลูกค้าจ่ายที่ร้าน หรือโอนแล้วแสดงสลิปให้ดู บันทึกแล้วระบบยืนยันการจองและออกใบเสร็จทันที</p>
      <form method="post" action="<?= e(url('/admin/bookings/' . $b['id'] . '/pay')) ?>" class="row" style="margin-top:10px" data-confirm="รับเงิน <?= e(money($b['total_amount'])) ?> และยืนยันการจองนี้?">
        <?= csrf_field() ?>
        <label class="sr-only" for="pay-method">ช่องทาง</label>
        <select class="select" id="pay-method" name="method" style="flex:1"><option value="cash">เงินสด</option><option value="transfer">โอนเข้าบัญชี</option><option value="promptpay">พร้อมเพย์</option></select>
        <button class="btn" type="submit">รับ <?= e(number_format((float) $b['total_amount'])) ?></button>
      </form>
      <?php if ($st === 'awaiting_verification'): ?><p class="small" style="margin-top:10px"><a href="<?= e(url('/admin/payments')) ?>">หรือไปตรวจสลิปที่ลูกค้าแนบมา</a></p><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($st === 'confirmed' && $ships): ?>
    <form class="action" method="post" action="<?= e(url($base . '/ship')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <h3>แพ็กและส่งพัสดุ</h3>
      <p class="small muted">ส่งถึง <?= e($b['ship_province']) ?> · ต้องส่งออก <?= e(relative_day((string) $b['ship_by_date'])) ?> · <a href="<?= e(url($base . '/label')) ?>" target="_blank">พิมพ์ใบปะหน้า</a></p>
      <?php if ($lastOut && in_array($lastOut['status'], ['failed', 'lost'], true)): ?><p class="small late" style="margin-top:6px">ครั้งก่อน: <?= e(Status::SHIPMENT[$lastOut['status']][0]) ?> · <?= e($lastOut['note'] ?? '') ?></p><?php endif; ?>
      <div class="field" style="margin-top:10px"><label class="label" for="s-carrier">ขนส่ง</label><?php $carrierSelect('s-carrier', $b['delivery_method'] === 'messenger' ? 'messenger' : 'thaipost', $b['delivery_method'] === 'messenger'); ?></div>
      <div class="field"><label class="label" for="s-no">เลขพัสดุ <span class="opt">(เมสเซนเจอร์เว้นได้)</span></label><input class="input num" id="s-no" name="tracking_no" maxlength="40" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="EF123456789TH" value="<?= e(old('tracking_no')) ?>" <?= invalid_attr('tracking_no') ?>><?= field_error('tracking_no') ?></div>
      <div class="field"><label class="label" for="s-note">หมายเหตุ <span class="opt">(ไม่บังคับ)</span></label><input class="input" id="s-note" name="note" maxlength="200" placeholder="เช่น คนขับ Lalamove ชื่อ…"></div>
      <div style="margin-top:10px"><?php View::partial('photo-input', ['id' => 'ship-photos', 'label' => 'รูปชุดก่อนแพ็ก', 'hint' => 'หลักฐานสภาพก่อนส่ง']); ?></div>
      <button class="btn btn-block" type="submit" style="margin-top:12px"><?= icon('truck') ?>บันทึกส่งออกและแจ้งลูกค้า</button>
    </form>
    <details class="action" style="border-color:var(--line)">
      <summary style="cursor:pointer;font-weight:600">ลูกค้าขอมารับที่ร้านแทน</summary>
      <form method="post" action="<?= e(url($base . '/to-pickup')) ?>" style="margin-top:10px" data-confirm="เปลี่ยนเป็นรับชุดที่ร้าน?<?= $lastOut ? '' : ' ระบบจะบันทึกคืนค่าจัดส่ง ' . e(money($b['delivery_fee'])) ?>">
        <?= csrf_field() ?>
        <p class="small muted"><?= $lastOut ? 'เคยส่งพัสดุแล้ว ไม่คืนค่าจัดส่ง' : 'ยังไม่ได้ส่ง ระบบคืนค่าจัดส่ง ' . e(money($b['delivery_fee'])) . ' ให้ลูกค้า' ?></p>
        <button class="btn btn-ghost btn-block" type="submit" style="margin-top:8px">เปลี่ยนเป็นรับที่ร้าน</button>
      </form>
    </details>
    <?php elseif ($st === 'confirmed'): ?>
    <div class="action">
      <h3>ลูกค้ามารับชุด</h3>
      <p class="small muted">ตรวจบัตรประชาชน ให้ลูกค้าลองและตรวจสภาพชุดด้วยกันก่อนส่งมอบ</p>
      <form method="post" action="<?= e(url('/admin/bookings/' . $b['id'] . '/pickup')) ?>" style="margin-top:10px" data-confirm="ยืนยันว่าลูกค้ารับชุด <?= e($b['costume_code']) ?> แล้ว?">
        <?= csrf_field() ?>
        <button class="btn btn-block" type="submit" <?= $b['pickup_date'] > today() ? 'title="ยังไม่ถึงวันรับ แต่บันทึกรับก่อนได้"' : '' ?>><?= icon('package') ?>บันทึกว่ารับชุดแล้ว</button>
      </form>
      <?php if ($b['pickup_date'] > today()): ?><p class="small muted" style="margin-top:8px">นัดรับ <?= e(relative_day($b['pickup_date'])) ?></p><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($st === 'shipped'): ?>
    <div class="action">
      <h3>พัสดุกำลังไปหาลูกค้า</h3>
      <?php if ($lastOut): ?><p class="small muted"><?= e(\App\Services\Fulfilment::describe($lastOut)) ?> · ควรถึง <?= e(thai_date($b['pickup_date'])) ?></p><?php endif; ?>
      <form method="post" action="<?= e(url($base . '/delivered')) ?>" style="margin-top:10px" data-confirm="ยืนยันว่าพัสดุถึงลูกค้าแล้ว (ดูจากระบบติดตามพัสดุ)?">
        <?= csrf_field() ?>
        <button class="btn btn-block" type="submit"><?= icon('package-check') ?>พัสดุถึงลูกค้าแล้ว</button>
      </form>
    </div>
    <details class="action" style="border-color:var(--line)">
      <summary style="cursor:pointer;font-weight:600;color:var(--bad)">ส่งไม่สำเร็จ / พัสดุหาย</summary>
      <form method="post" action="<?= e(url($base . '/failed')) ?>" style="margin-top:10px" data-confirm="บันทึกว่าส่งไม่สำเร็จ? การจองจะกลับเป็น “ยืนยันแล้ว” เพื่อส่งใหม่หรือเปลี่ยนเป็นรับที่ร้าน">
        <?= csrf_field() ?>
        <div class="field"><label class="label" for="f-reason">สาเหตุ</label><input class="input" id="f-reason" name="reason" required maxlength="200" placeholder="เช่น ไม่มีผู้รับ ขนส่งตีกลับมาที่ร้าน"></div>
        <label class="check" style="margin-top:8px"><input type="checkbox" name="lost" value="1"><span>พัสดุสูญหาย (เปิดเคลมกับขนส่ง)</span></label>
        <button class="btn btn-danger btn-block" type="submit" style="margin-top:10px">บันทึก</button>
      </form>
    </details>
    <?php endif; ?>

    <?php if ($st === 'returning' && $lastBack && $lastBack['status'] === 'in_transit'): ?>
    <form class="action" method="post" action="<?= e(url($base . '/receive')) ?>">
      <?= csrf_field() ?>
      <h3>พัสดุคืนกำลังมา</h3>
      <p class="small muted"><?= e(\App\Services\Fulfilment::describe($lastBack)) ?> · ฝากส่ง <?= e(thai_date($lastBack['shipped_on'])) ?></p>
      <div class="field" style="margin-top:10px"><label class="label" for="rb-date">วันที่ฝากส่งตามตราประทับ</label><input class="input" type="date" id="rb-date" name="posted_on" value="<?= e($lastBack['shipped_on']) ?>" max="<?= e(today()) ?>"><span class="hint">แก้ได้ถ้าวันที่ลูกค้าแจ้งไม่ตรงกับตราประทับ ใช้คิดค่าปรับคืนช้า</span></div>
      <button class="btn btn-block" type="submit" style="margin-top:10px"><?= icon('package-check') ?>ได้รับพัสดุคืนแล้ว</button>
    </form>
    <details class="action" style="border-color:var(--line)">
      <summary style="cursor:pointer;font-weight:600;color:var(--bad)">พัสดุคืนสูญหาย</summary>
      <form method="post" action="<?= e(url($base . '/return-lost')) ?>" style="margin-top:10px" data-confirm="บันทึกว่าพัสดุคืนสูญหาย? ลูกค้าจะไม่ถูกคิดค่าคืนช้า">
        <?= csrf_field() ?>
        <div class="field"><label class="label" for="rl-note">รายละเอียด</label><input class="input" id="rl-note" name="reason" required maxlength="200" placeholder="เช่น ขนส่งยืนยันว่าหาย เลขเคลม …"></div>
        <p class="hint">จากนั้นปิดการเช่าในหน้าตรวจสภาพ เลือก “ชุดสูญหาย” ถ้าขนส่งไม่ชดใช้</p>
        <button class="btn btn-danger btn-block" type="submit" style="margin-top:10px">บันทึก</button>
      </form>
    </details>
    <?php endif; ?>

    <?php if ($canInspect): ?>
    <div class="action" id="return">
      <h3><?= $st === 'returning' ? 'ตรวจสภาพชุดที่ส่งคืน' : 'รับคืนและตรวจสภาพ' ?></h3>
      <p class="small muted">เช็กลิสต์ทีละจุด เลือกค่าเสียหายจากตาราง ระบบคำนวณเงินมัดจำคืนให้</p>
      <a class="btn btn-block" href="<?= e(url($base . '/inspect')) ?>" style="margin-top:10px"><?= icon('clipboard-check') ?>เปิดหน้าตรวจสภาพ</a>
    </div>
    <?php endif; ?>

    <?php if ($st === 'picked_up'): ?>
    <details class="action" style="border-color:var(--line)">
      <summary style="cursor:pointer;font-weight:600">ลูกค้าส่งพัสดุคืน (ยังไม่ได้แจ้งในเว็บ)</summary>
      <form method="post" action="<?= e(url($base . '/post-back')) ?>" style="margin-top:10px">
        <?= csrf_field() ?>
        <p class="small muted">บันทึกเลขพัสดุแทนลูกค้า ระบบหยุดนับค่าปรับที่วันฝากส่ง</p>
        <div class="field"><label class="label" for="pb-carrier">ขนส่ง</label><?php $carrierSelect('pb-carrier'); ?></div>
        <div class="field"><label class="label" for="pb-no">เลขพัสดุ</label><input class="input num" id="pb-no" name="tracking_no" maxlength="40" autocomplete="off" autocapitalize="characters" spellcheck="false"></div>
        <div class="field"><label class="label" for="pb-date">วันที่ฝากส่ง</label><input class="input" type="date" id="pb-date" name="posted_on" value="<?= e(today()) ?>" max="<?= e(today()) ?>" required></div>
        <label class="check" style="margin-top:8px"><input type="checkbox" name="received" value="1"><span>พัสดุมาถึงร้านแล้ว</span></label>
        <button class="btn btn-ghost btn-block" type="submit" style="margin-top:10px">บันทึก</button>
      </form>
    </details>
    <?php endif; ?>


    <?php if (in_array($st, ['pending_payment', 'awaiting_verification', 'confirmed', 'shipped'], true)): ?>
    <?php if ($st !== 'shipped'): ?>
    <details class="action" style="border-width:1px;border-color:var(--line)">
      <summary style="cursor:pointer;font-weight:600">เลื่อนวัน / แก้ไขการจอง</summary>
      <form method="post" action="<?= e(url('/admin/bookings/' . $b['id'] . '/reschedule')) ?>" data-booking-form data-quote-api="<?= e(url('/api/dress/' . rawurlencode($b['costume_code']) . '/quote')) ?>" data-ignore="<?= (int) $b['id'] ?>" style="margin-top:12px">
        <?= csrf_field() ?>
        <div class="field"><label class="label" for="r-ev">วันงาน</label><input class="input" id="r-ev" type="date" name="event_date" value="<?= e($b['event_date']) ?>"></div>
        <div class="grid-2 keep" style="margin-top:12px">
          <div class="field"><label class="label" for="r-pu">รับ</label><input class="input" id="r-pu" type="date" name="pickup_date" value="<?= e($b['pickup_date']) ?>"></div>
          <div class="field"><label class="label" for="r-rt">คืน</label><input class="input" id="r-rt" type="date" name="return_date" value="<?= e($b['return_date']) ?>"></div>
          <div class="field"><label class="label" for="r-pt">เวลารับ</label><input class="input" id="r-pt" type="time" name="pickup_time" value="<?= e(substr((string) $b['pickup_time'], 0, 5)) ?>"></div>
          <div class="field"><label class="label" for="r-rtt">เวลาคืน</label><input class="input" id="r-rtt" type="time" name="return_time" value="<?= e(substr((string) $b['return_time'], 0, 5)) ?>"></div>
        </div>
        <div class="field" style="margin-top:12px"><label class="label" for="r-note">บันทึกของร้าน</label><input class="input" id="r-note" name="staff_note" value="<?= e($b['staff_note'] ?? '') ?>" maxlength="255"></div>
        <p class="quote-state" data-quote-state role="status"></p>
        <div data-quote-out hidden></div>
        <p class="hint">ยอดเงินคำนวณใหม่ตามจำนวนวัน ถ้ายอดเปลี่ยนหลังชำระแล้ว ให้เก็บหรือคืนส่วนต่างที่หน้าร้าน</p>
        <button class="btn btn-ghost btn-block" type="submit" style="margin-top:10px" data-booking-submit>บันทึกและแจ้งลูกค้า</button>
      </form>
    </details>
    <?php endif; ?>

    <details class="action" style="border-width:1px;border-color:var(--line)">
      <summary style="cursor:pointer;font-weight:600;color:var(--bad)">ยกเลิกการจอง</summary>
      <form method="post" action="<?= e(url('/admin/bookings/' . $b['id'] . '/cancel')) ?>" style="margin-top:12px" data-confirm="ยกเลิกการจอง <?= e($b['booking_no']) ?>? ชุดจะว่างให้คนอื่นจองทันที">
        <?= csrf_field() ?>
        <div class="field"><label class="label" for="c-reason">เหตุผล</label><input class="input" id="c-reason" name="reason" required maxlength="200" placeholder="เช่น ลูกค้าขอยกเลิกทางโทรศัพท์"></div>
        <?php if ($paidTotal > 0): ?>
        <div class="field"><label class="label" for="c-refund">คืนเงินลูกค้า (บาท)</label><input class="input num" id="c-refund" name="refund" inputmode="decimal" value="<?= e((float) $paidTotal) ?>"><span class="hint">ชำระมาแล้ว <?= e(money($paidTotal)) ?></span></div>
        <?php endif; ?>
        <button class="btn btn-danger btn-block" type="submit" style="margin-top:10px"><?= icon('ban') ?>ยกเลิกการจอง</button>
      </form>
    </details>
    <?php endif; ?>
  </aside>
</div>
