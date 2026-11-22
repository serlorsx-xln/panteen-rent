<?php
/** @var array $s one shipments row */
use App\Services\Shipping;
use App\Services\Status;

$page = Shipping::trackingPage($s['carrier']);
?>
<div class="parcel">
  <div class="parcel-head">
    <span class="parcel-dir"><?= $s['direction'] === 'out' ? 'ร้าน → ลูกค้า' : 'ลูกค้า → ร้าน' ?></span>
    <?= Status::badge(Status::SHIPMENT, $s['status']) ?>
  </div>
  <p class="parcel-carrier"><?= e(Shipping::carrierName($s['carrier'])) ?></p>
  <?php if ($s['tracking_no']): ?>
  <p class="parcel-no">
    <span class="num"><?= e($s['tracking_no']) ?></span>
    <button class="btn btn-xs btn-ghost" type="button" data-copy="<?= e($s['tracking_no']) ?>"><?= icon('copy', 'ic ic-sm') ?>คัดลอกเลขพัสดุ</button>
    <?php if ($page): ?><a class="btn btn-xs btn-ghost" href="<?= e($page) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external-link', 'ic ic-sm') ?>เปิดหน้าติดตามพัสดุ</a><?php endif; ?>
  </p>
  <?php endif; ?>
  <p class="small muted"><?= $s['direction'] === 'out' ? 'ส่งออก' : 'ฝากส่ง' ?> <?= e(thai_date($s['shipped_on'])) ?>
    <?php if ($s['arrived_at']): ?> · <?= $s['direction'] === 'out' ? 'ถึงลูกค้า' : 'ถึงร้าน' ?> <?= e(thai_datetime($s['arrived_at'])) ?><?php endif; ?></p>
  <?php if ($s['note']): ?><p class="small" style="margin-top:4px"><?= e($s['note']) ?></p><?php endif; ?>
</div>
