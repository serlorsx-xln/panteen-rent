<div class="col">
  <div class="page-head">
    <h1>กล่องจดหมายจำลอง</h1>
    <p>ทุกอีเมลที่ระบบส่งถูกเก็บไว้ที่นี่ ใช้ทดสอบโดยไม่ต้องตั้งค่า SMTP · ตอนนี้ตั้งค่าการส่งเป็น <b><?= $driver === 'smtp' ? 'SMTP (ส่งจริงและเก็บสำเนา)' : 'โหมดทดสอบ (ไม่ส่งออกจริง)' ?></b></p>
  </div>
  <form class="row" method="get" action="<?= e(url('/dev/mailbox')) ?>" style="margin-bottom:12px">
    <label class="sr-only" for="to">กรองตามผู้รับ</label>
    <input class="input" id="to" name="to" type="email" value="<?= e($to) ?>" placeholder="กรองตามอีเมลผู้รับ" style="max-width:320px">
    <button class="btn btn-ghost" type="submit">กรอง</button>
    <?php if ($to): ?><a class="small" href="<?= e(url('/dev/mailbox')) ?>">ดูทั้งหมด</a><?php endif; ?>
  </form>
  <?php if (!$mails): ?>
    <div class="empty"><?= icon('inbox', 'ic ic-lg') ?><h2>ยังไม่มีอีเมล</h2><p>สมัครสมาชิกหรือจองชุด แล้วอีเมลจะมาอยู่ที่นี่</p></div>
  <?php else: ?>
  <ul class="mail-list">
    <?php foreach ($mails as $m): ?>
    <li><a href="<?= e(url('/dev/mailbox/' . $m['id'])) ?>">
      <span><b><?= e($m['subject']) ?></b><br><span class="small muted">ถึง <?= e($m['to_email']) ?></span></span>
      <span class="small faint" style="text-align:right"><?= e(time_ago($m['created_at'])) ?><br><?= $m['status'] === 'sent' ? 'ส่งแล้ว' : ($m['status'] === 'failed' ? 'ส่งไม่สำเร็จ' : 'เก็บไว้') ?></span>
    </a></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
