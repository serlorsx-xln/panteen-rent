<?php
/**
 * Who took the photographs: the two pictures the site itself uses, then one line per dress.
 * @var array $rows @var array $site
 */
?>
<div class="col">
  <div class="page-head">
    <h1>ที่มาของภาพ</h1>
    <p>ภาพทั้งหมดในเว็บไซต์สาธิตนี้มาจาก Pexels ใช้ตามสัญญาอนุญาต Pexels License ซึ่งใช้ได้ฟรีโดยไม่ต้องขออนุญาต ขอบคุณช่างภาพทุกท่าน ชื่อและราคาชุดเป็นข้อมูลตัวอย่าง</p>
  </div>

  <section aria-labelledby="h-site">
    <h2 class="credit-title" id="h-site">ภาพประกอบเว็บไซต์</h2>
    <ul class="mail-list">
      <?php foreach ($site as $s): ?>
      <li>
        <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener">
          <span><b><?= e($s['where']) ?></b><br><span class="small muted">ภาพ: <?= e($s['by']) ?><?= !empty($s['note']) ? ' · ' . e($s['note']) : '' ?></span></span>
          <span class="small faint">Pexels <?= icon('external-link', 'ic ic-sm') ?></span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section aria-labelledby="h-dress">
    <h2 class="credit-title" id="h-dress">ภาพชุด</h2>
    <ul class="mail-list">
      <?php foreach ($rows as $r):
          $parts = explode(' · ', $r['credit']);
          $link = $parts[2] ?? null; ?>
      <li>
        <a href="<?= e($link ?: url('/dress/' . rawurlencode($r['code']))) ?>" <?= $link ? 'target="_blank" rel="noopener"' : '' ?>>
          <span><b><?= e($r['code']) ?></b> <?= e($r['name']) ?><br><span class="small muted"><?= e($parts[0]) ?></span></span>
          <span class="small faint"><?= $link ? 'Pexels ' . icon('external-link', 'ic ic-sm') : '' ?></span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
