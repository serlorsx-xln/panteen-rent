<div class="col">
  <div class="auth">
    <div class="auth-card">
      <h1>ลืมรหัสผ่าน</h1>
      <p class="lead">ใส่อีเมลที่ใช้สมัคร เราจะส่งลิงก์ตั้งรหัสผ่านใหม่ให้</p>
      <form method="post" action="<?= e(url('/forgot')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label class="label" for="email">อีเมล</label>
          <input class="input" id="email" name="email" type="email" autocomplete="email" value="<?= e(old('email')) ?>" required autofocus <?= invalid_attr('email') ?>>
          <?= field_error('email') ?>
        </div>
        <button class="btn btn-lg btn-block" type="submit" style="margin-top:18px">ส่งลิงก์ตั้งรหัสผ่านใหม่</button>
      </form>
      <p class="auth-alt"><a href="<?= e(url('/login')) ?>">กลับไปหน้าเข้าสู่ระบบ</a></p>
    </div>
  </div>
</div>
