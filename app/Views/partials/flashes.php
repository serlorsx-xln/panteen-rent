<?php
$icons = ['success' => 'circle-check', 'error' => 'circle-alert', 'warning' => 'circle-alert', 'info' => 'info'];
$flashes = take_flashes();
if ($flashes): ?>
<div class="flashes" role="status" aria-live="polite">
  <?php foreach ($flashes as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>" data-flash>
    <?= icon($icons[$f['type']] ?? 'info') ?>
    <span><?= e($f['message']) ?></span>
    <button type="button" data-dismiss aria-label="ปิดข้อความ"><?= icon('x', 'ic ic-sm') ?></button>
  </div>
  <?php endforeach; ?>
</div>
<?php endif;
