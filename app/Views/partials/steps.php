<?php
/** @var array $b */
// The steps a rental goes through, with the parcel legs only when this booking uses them.
$ships = ($b['delivery_method'] ?? 'pickup') !== 'pickup';
$posts = ($b['return_method'] ?? 'store') === 'post';
$steps = ['pending_payment' => 'จองแล้ว รอชำระ', 'awaiting_verification' => 'ร้านตรวจสลิป', 'confirmed' => 'ยืนยันแล้ว'];
if ($ships) {
    $steps['shipped'] = 'ร้านส่งพัสดุ';
}
$steps['picked_up'] = $ships ? 'ได้รับชุดแล้ว' : 'รับชุดแล้ว';
if ($posts) {
    $steps['returning'] = 'ส่งชุดคืนแล้ว';
}
$steps['returned'] = $posts ? 'ตรวจสภาพ คืนมัดจำ' : 'คืนชุดแล้ว';
$keys = array_keys($steps);
$now = array_search($b['status'], $keys, true);
$closed = $now === false;
$last = count($keys) - 1;
?>
<ol class="steps<?= $closed ? ' is-closed' : '' ?><?= count($keys) > 5 ? ' is-long' : '' ?>" aria-label="ขั้นตอนการเช่า">
  <?php foreach (array_values($steps) as $i => $l): ?>
  <li class="<?= !$closed && ($i < $now || ($i === $now && $i === $last)) ? 'is-done' : (!$closed && $i === $now ? 'is-now' : '') ?>" <?= !$closed && $i === $now ? 'aria-current="step"' : '' ?>><?= e($l) ?></li>
  <?php endforeach; ?>
</ol>
