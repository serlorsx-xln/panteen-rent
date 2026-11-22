<div class="col col-narrow">
  <div class="auth">
    <div class="auth-card">
      <?= icon('mail', 'ic ic-lg') ?>
      <h1 style="margin-top:12px">ยืนยันอีเมลของคุณ</h1>
      <p class="lead">เราส่งลิงก์ยืนยันไปที่ <b><?= e($user['email']) ?></b> กดลิงก์ในอีเมลแล้วเริ่มจองชุดได้เลย ลิงก์ใช้ได้ 48 ชั่วโมง</p>
      <form method="post" action="<?= e(url('/account/verify')) ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">ส่งลิงก์อีกครั้ง</button></form>
      <p class="small muted" style="margin-top:18px">ไม่เห็นอีเมล? ดูในโฟลเดอร์จดหมายขยะ หรือ<a href="<?= e(url('/account')) ?>">แก้อีเมลในโปรไฟล์</a><?php if (mailbox_open()): ?> · โหมดทดสอบ: เปิด<a href="<?= e(url('/dev/mailbox', ['to' => $user['email']])) ?>">กล่องจดหมายจำลอง</a><?php endif; ?></p>
    </div>
  </div>
</div>
