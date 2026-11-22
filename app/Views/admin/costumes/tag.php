<?php
/** @var array $c @var list<string> $accessories */
?>
<h1 class="sr-only">ป้าย QR ชุด <?= e($c['code']) ?></h1>
<div class="tags">
  <?php for ($i = 0; $i < 2; $i++): ?>
  <section class="tag-card">
    <div class="tag-qr" data-qr="<?= e($c['code']) ?>" data-qr-label="QR รหัสชุด <?= e($c['code']) ?>"></div>
    <div>
      <p class="tag-code num"><?= e($c['code']) ?></p>
      <p><?= e($c['name']) ?></p>
      <p class="small"><?= e($c['category_name']) ?> · <?= e($c['size_label'] === 'FREE' ? 'ฟรีไซส์' : 'ไซส์ ' . $c['size_label']) ?></p>
      <?php if ($accessories): ?><p class="small">มากับชุด: <?= e(implode(', ', $accessories)) ?></p><?php endif; ?>
    </div>
  </section>
  <?php endfor; ?>
</div>
<p class="small no-print" style="text-align:center;margin:12px">พิมพ์สองใบ: ติดที่ป้ายในชุด และที่ถุงคลุมชุด สแกนที่หน้า “สแกนชุด” ในหลังร้าน</p>
<script src="<?= e(asset('js/qrcode.js')) ?>"></script>
