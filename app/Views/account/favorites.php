<?php
/** The dresses a member has kept, in the same cards as the shop. @var array $items @var array $filters */
use App\Core\View;
?>
<div class="col">
  <div class="page-head">
    <h1>ชุดที่ถูกใจ</h1>
    <p><?= $filters['date'] ? 'สถานะว่างตามวันงาน ' . e(thai_date_full($filters['date'])) : 'เลือกวันงานที่หน้ารายการชุด แล้วรายการนี้จะบอกว่าชุดไหนว่างวันนั้น' ?></p>
  </div>
  <?php View::partial('acct-nav'); ?>
  <?php if ($items): ?>
  <div class="vits vits-acct" data-grid><?php View::partial('grid', ['items' => $items, 'filters' => $filters]); ?></div>
  <?php else: ?>
  <div class="empty">
    <?= icon('heart', 'ic ic-lg') ?>
    <h2>ยังไม่มีชุดที่ถูกใจ</h2>
    <p>แตะรูปหัวใจที่ชุด เพื่อเก็บไว้เทียบหรือจองทีหลัง</p>
    <a class="btn" href="<?= e(url('/dresses')) ?>">ดูชุดทั้งหมด</a>
  </div>
  <?php endif; ?>
</div>
