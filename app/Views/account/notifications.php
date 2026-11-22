<?php
$iconFor = fn(string $t) => match (true) {
    str_contains($t, 'ship') || str_contains($t, 'deliver') || str_contains($t, 'parcel') || $t === 'return_slow' => 'truck',
    str_contains($t, 'dispute') || str_contains($t, 'inspect') || str_contains($t, 'damage') => 'scale',
    str_contains($t, 'remind') => 'clock',
    str_contains($t, 'slip') || str_contains($t, 'payment') => 'wallet',
    str_contains($t, 'confirm') => 'circle-check',
    str_contains($t, 'cancel') || str_contains($t, 'expire') || str_contains($t, 'reject') => 'calendar-x',
    str_contains($t, 'appoint') || str_contains($t, 'fitting') => 'calendar-clock',
    str_contains($t, 'return') || str_contains($t, 'overdue') => 'rotate-ccw',
    default => 'bell',
};
$isAdmin = is_path('/admin');
?>
<div class="<?= $isAdmin ? '' : 'col' ?>">
  <div class="page-head"><h1>การแจ้งเตือน</h1></div>
  <?php if (!$isAdmin) \App\Core\View::partial('acct-nav'); ?>
  <?php if (!$items): ?>
    <div class="empty"><?= icon('bell', 'ic ic-lg') ?><h2>ยังไม่มีการแจ้งเตือน</h2><p>ร้านจะแจ้งที่นี่และทางอีเมล เมื่อการจองเปลี่ยนสถานะ หรือใกล้วันรับและคืนชุด</p></div>
  <?php else: ?>
  <div style="margin-top:8px">
    <?php foreach ($items as $n): ?>
    <a class="notif<?= $n['read_at'] ? '' : ' is-unread' ?>" href="<?= e($n['link'] ? url($n['link']) : '#') ?>">
      <span class="ico"><?= icon($iconFor($n['type']), 'ic ic-sm') ?></span>
      <span><b><?= e($n['title']) ?></b><?php if ($n['body']): ?><span class="small muted"><?= e($n['body']) ?></span><br><?php endif; ?><small><?= e(time_ago($n['created_at'])) ?></small></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
