<div class="col">
  <nav class="crumbs"><a href="<?= e(url('/dev/mailbox')) ?>"><?= icon('arrow-left', 'ic ic-sm') ?> กล่องจดหมายจำลอง</a></nav>
  <div class="page-head">
    <h1><?= e($m['subject']) ?></h1>
    <p>ถึง <?= e($m['to_email']) ?> · <?= e(thai_datetime($m['created_at'])) ?><?= $m['error'] ? ' · ส่งไม่สำเร็จ: ' . e($m['error']) : '' ?></p>
  </div>
  <iframe class="mail-frame" src="<?= e(url('/dev/mailbox/' . $m['id'], ['raw' => 1])) ?>" title="เนื้อหาอีเมล" sandbox></iframe>
</div>
