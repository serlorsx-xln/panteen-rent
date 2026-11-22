<?php
/** @var array $items @var string $tab @var array $counts */
use App\Services\Status;
?>
<div class="col">
  <div class="page-head"><h1>การจองของฉัน</h1></div>
  <?php \App\Core\View::partial('acct-nav'); ?>
  <nav class="subtabs" aria-label="ประเภทการจอง">
    <a href="<?= e(url('/bookings')) ?>" <?= $tab === 'active' ? 'aria-current="page"' : '' ?>>กำลังดำเนินการ<span class="n num"><?= (int) ($counts['active'] ?? 0) ?></span></a>
    <a href="<?= e(url('/bookings', ['tab' => 'past'])) ?>" <?= $tab === 'past' ? 'aria-current="page"' : '' ?>>ที่ผ่านมา<span class="n num"><?= (int) ($counts['past'] ?? 0) ?></span></a>
  </nav>
  <?php if (!$items): ?>
    <div class="empty">
      <?= icon('shopping-bag', 'ic ic-lg') ?>
      <h2><?= $tab === 'active' ? 'ยังไม่มีการจองที่กำลังดำเนินการ' : 'ยังไม่มีประวัติการเช่า' ?></h2>
      <p>เลือกวันงานที่หน้าร้าน แล้วดูว่าชุดไหนว่างวันนั้น</p>
      <a class="btn" href="<?= e(url('/dresses')) ?>">ดูชุดทั้งหมด</a>
    </div>
  <?php else: ?>
  <div class="blist">
    <?php foreach ($items as $b):
        $hint = match ($b['status']) {
            'pending_payment' => 'ชำระภายใน ' . thai_datetime($b['hold_expires_at']),
            'awaiting_verification' => 'ร้านกำลังตรวจสลิป',
            'confirmed' => $b['delivery_method'] === 'pickup'
                ? 'รับชุด' . relative_day($b['pickup_date']) . ' ' . substr((string) $b['pickup_time'], 0, 5) . ' น.'
                : 'ร้านส่งพัสดุ' . relative_day((string) $b['ship_by_date']) . ' ถึงภายใน ' . thai_date($b['pickup_date'], false, false),
            'shipped' => 'ชุดกำลังเดินทาง กดได้รับแล้วเมื่อพัสดุถึง',
            'picked_up' => $b['return_date'] < today() ? 'เลยกำหนดคืน ' . days_between($b['return_date'], today()) . ' วัน'
                : ($b['return_method'] === 'post' ? 'ฝากส่งคืน' : 'คืนชุด') . relative_day($b['return_date']),
            'returning' => 'ร้านรอรับพัสดุคืนและตรวจสภาพ',
            'returned' => ($b['dispute_at'] ?? null) && !($b['dispute_resolved_at'] ?? null) ? 'ร้านกำลังทบทวนข้อโต้แย้ง' : '',
            default => '',
        }; ?>
    <a class="bcard" href="<?= e(url('/bookings/' . $b['id'])) ?>">
      <img src="<?= e(mini($b['image'])) ?>" alt="">
      <div class="stack" style="--stack:4px">
        <div class="spread"><b><?= e($b['costume_name']) ?></b><?= Status::badge(Status::BOOKING, $b['status']) ?></div>
        <p class="when">งาน <?= e(thai_date($b['event_date'])) ?> · รับ <?= e(thai_date($b['pickup_date'], false, false)) ?> คืน <?= e(thai_date($b['return_date'], false, false)) ?></p>
        <p class="small faint num"><?= e($b['booking_no']) ?> · <?= e(money($b['total_amount'])) ?><?= $hint ? ' · <b style="color:var(--ink)">' . e($hint) . '</b>' : '' ?></p>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
