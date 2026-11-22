<div class="col">
  <div class="auth">
    <div class="auth-card" style="max-width:520px">
      <h1>สมัครสมาชิก</h1>
      <p class="lead">ใช้ชื่อจริงและเบอร์ที่ติดต่อได้ ร้านใช้ยืนยันตัวตนตอนรับชุด</p>
      <form method="post" action="<?= e(url('/register')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="grid-2">
          <div class="field">
            <label class="label" for="first_name">ชื่อ</label>
            <input class="input" id="first_name" name="first_name" autocomplete="given-name" value="<?= e(old('first_name')) ?>" required <?= invalid_attr('first_name') ?>>
            <?= field_error('first_name') ?>
          </div>
          <div class="field">
            <label class="label" for="last_name">นามสกุล</label>
            <input class="input" id="last_name" name="last_name" autocomplete="family-name" value="<?= e(old('last_name')) ?>" required <?= invalid_attr('last_name') ?>>
            <?= field_error('last_name') ?>
          </div>
        </div>
        <div class="grid-2" style="margin-top:14px">
          <div class="field">
            <label class="label" for="nickname">ชื่อเล่น <span class="opt">(ไม่บังคับ)</span></label>
            <input class="input" id="nickname" name="nickname" value="<?= e(old('nickname')) ?>" <?= invalid_attr('nickname') ?>>
            <?= field_error('nickname') ?>
          </div>
          <div class="field">
            <label class="label" for="phone">เบอร์โทร</label>
            <input class="input" id="phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="08X-XXX-XXXX" value="<?= e(old('phone')) ?>" required <?= invalid_attr('phone') ?>>
            <?= field_error('phone') ?>
          </div>
        </div>
        <div class="field" style="margin-top:14px">
          <label class="label" for="email">อีเมล</label>
          <input class="input" id="email" name="email" type="email" autocomplete="email" value="<?= e(old('email')) ?>" required <?= invalid_attr('email') ?> aria-describedby="email-hint">
          <span class="hint" id="email-hint">ร้านส่งลิงก์ยืนยัน ใบเสร็จ และการแจ้งเตือนไปที่อีเมลนี้</span>
          <?= field_error('email') ?>
        </div>
        <div class="field">
          <label class="label" for="line_id">LINE ID <span class="opt">(ไม่บังคับ)</span></label>
          <input class="input" id="line_id" name="line_id" value="<?= e(old('line_id')) ?>" <?= invalid_attr('line_id') ?>>
          <?= field_error('line_id') ?>
        </div>
        <div class="field">
          <label class="label" for="password">รหัสผ่าน</label>
          <div class="input-affix">
            <input class="input" id="password" name="password" type="password" autocomplete="new-password" required <?= invalid_attr('password') ?> aria-describedby="password-hint">
            <button class="iconbtn reveal" type="button" data-reveal="password" aria-label="แสดงรหัสผ่าน" aria-pressed="false"><?= icon('eye') ?></button>
          </div>
          <div class="pw-meter" data-pw-meter="password" aria-hidden="true"><i></i></div>
          <span class="hint" id="password-hint">อย่างน้อย 8 ตัว มีทั้งตัวอักษรและตัวเลข</span>
          <?= field_error('password') ?>
        </div>
        <div class="field">
          <label class="label" for="password_confirmation">ยืนยันรหัสผ่าน</label>
          <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required <?= invalid_attr('password_confirmation') ?>>
          <?= field_error('password_confirmation') ?>
        </div>
        <label class="check" style="margin-top:18px">
          <input type="checkbox" name="accept" value="1" <?= old('accept') ? 'checked' : '' ?> <?= invalid_attr('accept') ?>>
          <span>ยอมรับ<a href="<?= e(url('/how-to-rent')) ?>" target="_blank">เงื่อนไขการเช่าชุด</a> และยินยอมให้ร้านเก็บข้อมูลติดต่อเพื่อการเช่า</span>
        </label>
        <?= field_error('accept') ?>
        <button class="btn btn-lg btn-block" type="submit" style="margin-top:20px">สมัครสมาชิก</button>
      </form>
      <p class="auth-alt">มีบัญชีแล้ว? <a href="<?= e(url('/login')) ?>">เข้าสู่ระบบ</a></p>
    </div>
  </div>
</div>
