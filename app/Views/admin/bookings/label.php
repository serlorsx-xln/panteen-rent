<?php
/** @var array $b @var list<string> $accessories @var ?array $out */
use App\Controllers\Admin\FulfilmentController as F;
use App\Services\Shipping;
?>
<div class="labels">
  <?php if ($b['delivery_method'] !== 'pickup'): ?>
  <section class="label-card">
    <header><b>ผู้รับ</b><span class="num"><?= e($b['booking_no']) ?></span></header>
    <p class="label-to"><?= nl2br(e(F::shipTo($b))) ?></p>
    <?php if ($b['ship_postcode']): ?><p class="label-zip num"><?= e($b['ship_postcode']) ?></p><?php endif; ?>
    <footer><b>ผู้ส่ง</b> <?= nl2br(e(F::shopAddress())) ?></footer>
    <p class="small">ส่งโดย <?= e(Shipping::DELIVERY[$b['delivery_method']]) ?><?= $out && $out['tracking_no'] ? ' · ' . e($out['tracking_no']) : '' ?> · ต้องถึงภายใน <?= e(thai_date($b['pickup_date'])) ?></p>
  </section>
  <?php endif; ?>
  <?php if ($b['return_method'] === 'post'): ?>
  <section class="label-card">
    <header><b>ใบปะหน้าส่งคืน · ผู้รับ</b><span class="num"><?= e($b['booking_no']) ?></span></header>
    <p class="label-to"><?= nl2br(e(F::shopAddress())) ?></p>
    <footer><b>ผู้ส่ง</b> <?= nl2br(e(F::shipTo($b))) ?></footer>
    <p class="small">ร้านจ่ายค่าส่งคืนแล้ว · ฝากส่งภายใน <b><?= e(thai_date($b['return_date'])) ?></b> แล้วแจ้งเลขพัสดุในหน้าการจอง</p>
  </section>
  <?php endif; ?>
</div>

<section class="packing">
  <div class="packing-head">
    <div>
      <h1>ใบแพ็กของ <span class="num"><?= e($b['booking_no']) ?></span></h1>
      <p><?= e($b['costume_code']) ?> · <?= e($b['costume_name']) ?> · <?= e($b['size_label'] === 'FREE' ? 'ฟรีไซส์' : 'ไซส์ ' . $b['size_label']) ?></p>
    </div>
    <div class="packing-qr" data-qr="<?= e($b['booking_no']) ?>" data-qr-label="QR เลขจอง <?= e($b['booking_no']) ?>"></div>
  </div>
  <table>
    <thead><tr><th>ในกล่อง</th><th>แพ็ก</th><th>ลูกค้าได้รับ</th><th>คืนครบ</th></tr></thead>
    <tbody>
      <tr><td><?= e($b['costume_name']) ?></td><td>☐</td><td>☐</td><td>☐</td></tr>
      <?php foreach ($accessories as $a): ?><tr><td><?= e($a) ?></td><td>☐</td><td>☐</td><td>☐</td></tr><?php endforeach; ?>
      <?php if ($b['return_method'] === 'post'): ?><tr><td>ใบปะหน้าส่งคืน</td><td>☐</td><td>☐</td><td>–</td></tr><?php endif; ?>
    </tbody>
  </table>
  <ol class="packing-steps">
    <li>เปิดกล่องแล้วตรวจชุดและของในรายการ ถ่ายรูปและกด “ได้รับชุดแล้ว” ในหน้าการจองภายใน <?= (int) setting('arrival_report_hours') ?> ชั่วโมง</li>
    <li>ไม่ต้องซักหรือรีดเอง ร้านดูแลให้ ห้ามใช้เตารีดกับเลื่อมและลูกไม้</li>
    <li><?= $b['return_method'] === 'post' ? 'ส่งคืนด้วยกล่องเดิมและใบปะหน้าส่งคืน ภายใน ' . e(thai_date($b['return_date'])) . ' แล้วกรอกเลขพัสดุในเว็บ' : 'นำชุดมาคืนที่ร้าน ' . e(thai_date($b['return_date'])) . ' ก่อน ' . e(substr((string) $b['return_time'], 0, 5)) . ' น.' ?></li>
  </ol>
  <p class="small">ติดต่อร้าน <?= e(setting('shop_phone')) ?> · LINE <?= e(setting('shop_line')) ?></p>
</section>
<script src="<?= e(asset('js/qrcode.js')) ?>"></script>
