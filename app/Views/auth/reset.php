<div class="col">
  <div class="auth">
    <div class="auth-card">
      <h1>ตั้งรหัสผ่านใหม่</h1>
      <p class="lead">ใช้อย่างน้อย 8 ตัว มีทั้งตัวอักษรและตัวเลข</p>
      <form method="post" action="<?= e(url('/reset/' . $token)) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label class="label" for="password">รหัสผ่านใหม่</label>
          <div class="input-affix">
            <input class="input" id="password" name="password" type="password" autocomplete="new-password" required autofocus <?= invalid_attr('password') ?>>
            <button class="iconbtn reveal" type="button" data-reveal="password" aria-label="แสดงรหัสผ่าน" aria-pressed="false"><?= icon('eye') ?></button>
          </div>
          <div class="pw-meter" data-pw-meter="password" aria-hidden="true"><i></i></div>
          <?= field_error('password') ?>
        </div>
        <div class="field">
          <label class="label" for="password_confirmation">ยืนยันรหัสผ่านใหม่</label>
          <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required <?= invalid_attr('password_confirmation') ?>>
          <?= field_error('password_confirmation') ?>
        </div>
        <button class="btn btn-lg btn-block" type="submit" style="margin-top:18px">บันทึกรหัสผ่านใหม่</button>
      </form>
    </div>
  </div>
</div>
