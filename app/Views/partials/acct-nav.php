<?php $here = request_path(); ?>
<nav class="acct-nav chips" aria-label="เมนูบัญชี">
  <?php foreach (['/account' => 'โปรไฟล์และสัดส่วน', '/bookings' => 'การจอง', '/account/appointments' => 'นัดลองชุด', '/account/addresses' => 'ที่อยู่จัดส่ง', '/account/favorites' => 'ชุดที่ถูกใจ', '/account/notifications' => 'การแจ้งเตือน'] as $href => $label): ?>
  <a class="chip<?= $here === $href ? ' is-on' : '' ?>" href="<?= e(url($href)) ?>" <?= $here === $href ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>
