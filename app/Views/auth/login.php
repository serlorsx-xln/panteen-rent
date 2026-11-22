<div class="col">
  <div class="auth">
    <div class="auth-card">
      <h1>เข้าสู่ระบบ</h1>
      <p class="lead">ดูการจอง ชำระเงิน และเก็บชุดที่ถูกใจ</p>
      <form method="post" action="<?= e(url('/login')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label class="label" for="email">อีเมล</label>
          <input class="input" id="email" name="email" type="email" autocomplete="email" value="<?= e(old('email')) ?>" required autofocus <?= invalid_attr('email') ?>>
          <?= field_error('email') ?>
        </div>
        <div class="field">
          <div class="spread"><label class="label" for="password">รหัสผ่าน</label><a class="small" href="<?= e(url('/forgot')) ?>">ลืมรหัสผ่าน?</a></div>
          <div class="input-affix">
            <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
            <button class="iconbtn reveal" type="button" data-reveal="password" aria-label="แสดงรหัสผ่าน" aria-pressed="false"><?= icon('eye') ?></button>
          </div>
        </div>
        <button class="btn btn-lg btn-block" type="submit" style="margin-top:20px">เข้าสู่ระบบ</button>
      </form>
      <p class="auth-alt">ยังไม่มีบัญชี? <a href="<?= e(url('/register')) ?>">สมัครสมาชิก</a></p>
      <?php if (setting('is_demo') === '1'): ?>
      <div class="demo-accounts">
        <p>บัญชีสาธิต แตะเพื่อกรอกให้</p>
        <?php foreach ([['สมาชิก', 'member@panteen.test', 'Member1234'], ['พนักงานหน้าร้าน', 'staff@panteen.test', 'Staff1234'], ['ผู้ดูแลระบบ', 'admin@panteen.test', 'Admin1234']] as [$role, $em, $pw]): ?>
        <button type="button" data-fill-login="<?= e($em) ?>" data-pw="<?= e($pw) ?>"><b><?= e($role) ?></b><span class="num"><?= e($em) ?> / <?= e($pw) ?></span></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
