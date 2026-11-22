<?php
/** @var array $r @var array $b @var array $data @var ?array $issuer */
$isSettle = $r['kind'] === 'settlement';
$isAdjust = $r['kind'] === 'adjustment';
?>
<article class="receipt">
  <header class="receipt-head">
    <div>
      <div class="wordmark"><?= e(setting('shop_name')) ?></div>
      <p class="small muted" style="margin-top:8px"><?= e(setting('shop_address')) ?><br>โทร <?= e(setting('shop_phone')) ?></p>
    </div>
    <div class="receipt-meta">
      <h1><?= $isSettle ? 'ใบสรุปการคืนชุด' : ($isAdjust ? 'ใบปรับปรุงค่าเสียหาย' : 'ใบเสร็จรับเงิน') ?></h1>
      <p class="small num" style="margin-top:6px">เลขที่ <b><?= e($r['receipt_no']) ?></b><br>วันที่ <?= e(thai_datetime($r['issued_at'])) ?><br>อ้างอิงการจอง <?= e($b['booking_no']) ?></p>
    </div>
  </header>
  <dl class="kv" style="margin-top:16px">
    <dt>ลูกค้า</dt><dd><?= e(full_name($b)) ?><?= $b['nickname'] ? ' (' . e($b['nickname']) . ')' : '' ?> · <?= e(phone_fmt($b['phone'])) ?></dd>
    <dt>ชุด</dt><dd><?= e($b['costume_code'] . ' ' . $b['costume_name']) ?></dd>
    <dt>ช่วงเช่า</dt><dd>รับ <?= e(thai_date($b['pickup_date'])) ?> · คืน <?= e(thai_date($b['return_date'])) ?> (<?= (int) $b['rental_days'] ?> วัน)</dd>
    <?php if ($b['delivery_method'] !== 'pickup' || $b['return_method'] === 'post'): ?>
      <dt>การส่ง</dt><dd><?= e(\App\Services\Shipping::DELIVERY[$b['delivery_method']] . ' · ' . \App\Services\Shipping::RETURN[$b['return_method']]) ?></dd>
    <?php endif; ?>
    <?php if ($isSettle): ?><dt><?= $b['return_method'] === 'post' ? 'ร้านได้รับคืน' : 'คืนจริง' ?></dt><dd><?= e(thai_datetime($b['returned_at'])) ?></dd><?php endif; ?>
  </dl>
  <table>
    <thead><tr><th>รายการ</th><th>จำนวนเงิน (บาท)</th></tr></thead>
    <tbody>
      <?php foreach ($data['items'] ?? [] as $it): ?>
      <tr><td><?= e($it['label']) ?></td><td class="num"><?= e(($it['amount'] < 0 ? '−' : '') . number_format(abs((float) $it['amount']), 2)) ?></td></tr>
      <?php endforeach; ?>
      <?php if ($isSettle && ($data['extra_charge'] ?? 0) > 0): ?>
      <tr><td>ลูกค้าชำระส่วนที่เกินเงินมัดจำ</td><td class="num"><?= e(number_format((float) $data['extra_charge'], 2)) ?></td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot><tr><td><?= e($data['total_label'] ?? 'รวม') ?></td><td class="num"><?= e(number_format(abs((float) ($isSettle ? (($data['deposit_refund'] ?? 0) ?: ($data['extra_charge'] ?? 0)) : $r['amount'])), 2)) ?></td></tr></tfoot>
  </table>
  <?php if ($isAdjust && !empty($data['reply'])): ?>
    <p class="small" style="margin-top:10px">ผลการทบทวน: <?= e($data['reply']) ?></p>
  <?php endif; ?>
  <?php if (!$isSettle && !$isAdjust && !empty($data['method'])): ?>
    <p class="small" style="margin-top:10px">ชำระโดย <?= e(\App\Services\Status::PAYMENT_METHOD[$data['method']] ?? $data['method']) ?></p>
  <?php endif; ?>
  <div class="receipt-foot">
    <p><?= $isAdjust ? 'ร้านคืนเงินส่วนนี้ให้ลูกค้าภายใน 3 วันทำการ' : ($isSettle ? 'ร้านได้รับชุดคืนและตรวจสภาพเรียบร้อยแล้ว' : 'เงินมัดจำคืนเมื่อคืนชุดตรงเวลาและสภาพสมบูรณ์ คืนช้าปรับวันละ ' . e(money($b['late_fee_per_day']))) ?></p>
    <div class="sign">ผู้รับเงิน<?= $issuer ? '<br>' . e($issuer['nickname'] ?: $issuer['first_name']) : '' ?></div>
  </div>
  <?php if (setting('is_demo') === '1'): ?><p class="tiny faint" style="margin-top:18px">เอกสารจากระบบสาธิต ไม่ใช่ใบเสร็จจริง</p><?php endif; ?>
</article>
