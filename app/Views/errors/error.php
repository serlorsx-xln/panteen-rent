<div class="col">
  <div class="error-page">
    <span class="code"><?= (int) ($code ?? 500) ?></span>
    <h1><?= e($title ?? 'เกิดข้อผิดพลาด') ?></h1>
    <p><?= e($message ?? 'ลองใหม่อีกครั้ง ถ้ายังไม่ได้ ติดต่อร้าน') ?></p>
    <div class="row" style="justify-content:center">
      <a class="btn" href="<?= e(url('/')) ?>">กลับหน้าร้าน</a>
      <button class="btn btn-ghost" type="button" onclick="history.back()">ย้อนกลับ</button>
    </div>
  </div>
</div>
