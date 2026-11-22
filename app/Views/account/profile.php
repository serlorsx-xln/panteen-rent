<?php /** @var array $u @var array $stats */ ?>
<div class="col">
  <div class="page-head">
    <h1>สวัสดี คุณ<?= e($u['nickname'] ?: $u['first_name']) ?></h1>
    <p>เช่าไปแล้ว <?= (int) $stats['done'] ?> ครั้ง · กำลังดำเนินการ <?= (int) $stats['active'] ?> รายการ · สมาชิกตั้งแต่ <?= e(thai_date($u['created_at'])) ?></p>
  </div>
  <?php \App\Core\View::partial('acct-nav'); ?>
  <?php if (!$u['email_verified_at']): ?>
  <div class="flash flash-warning" style="margin-top:14px"><?= icon('circle-alert') ?><span>ยังไม่ได้ยืนยันอีเมล ต้องยืนยันก่อนจองชุด <a href="<?= e(url('/account/verify')) ?>">ยืนยันอีเมล</a></span></div>
  <?php endif; ?>

  <div class="booking-grid">
    <div>
      <section class="panel" id="size" aria-labelledby="h-size">
        <div class="panel-title"><?= icon('ruler') ?><h2 id="h-size">สัดส่วนของฉัน</h2></div>
        <p class="muted small">หน่วยเป็นนิ้ว ใช้เทียบกับชุดแต่ละตัว หน้าชุดจะบอกว่าพอดี คับ หรือหลวม เฉพาะคุณและร้านเท่านั้นที่เห็นข้อมูลนี้</p>
        <form method="post" action="<?= e(url('/account/measurements')) ?>" style="margin-top:14px" novalidate>
          <?= csrf_field() ?>
          <div class="grid-4 keep">
            <?php foreach (['bust_in' => ['รอบอก', 'นิ้ว'], 'waist_in' => ['รอบเอว', 'นิ้ว'], 'hip_in' => ['รอบสะโพก', 'นิ้ว'], 'height_cm' => ['ส่วนสูง', 'ซม.']] as $k => [$l, $unit]): ?>
            <div class="field">
              <label class="label" for="<?= $k ?>"><?= $l ?></label>
              <div class="input-affix"><input class="input num" id="<?= $k ?>" name="<?= $k ?>" inputmode="decimal" value="<?= e(old($k, $u[$k] !== null ? rtrim(rtrim((string) $u[$k], '0'), '.') : '')) ?>" <?= invalid_attr($k) ?>><span><?= $unit ?></span></div>
              <?= field_error($k) ?>
            </div>
            <?php endforeach; ?>
          </div>
          <button class="btn" type="submit" style="margin-top:14px">บันทึกสัดส่วน</button>
        </form>
      </section>

      <section class="panel" aria-labelledby="h-info">
        <div class="panel-title"><?= icon('user') ?><h2 id="h-info">ข้อมูลติดต่อ</h2></div>
        <form method="post" action="<?= e(url('/account/profile')) ?>" novalidate>
          <?= csrf_field() ?>
          <div class="grid-2">
            <?php foreach (['first_name' => 'ชื่อ', 'last_name' => 'นามสกุล', 'nickname' => 'ชื่อเล่น', 'phone' => 'เบอร์โทร', 'email' => 'อีเมล', 'line_id' => 'LINE ID'] as $k => $l): ?>
            <div class="field">
              <label class="label" for="<?= $k ?>"><?= $l ?><?= in_array($k, ['nickname', 'line_id'], true) ? ' <span class="opt">(ไม่บังคับ)</span>' : '' ?></label>
              <input class="input" id="<?= $k ?>" name="<?= $k ?>" <?= $k === 'email' ? 'type="email"' : ($k === 'phone' ? 'type="tel" inputmode="numeric"' : '') ?> value="<?= e(old($k, $u[$k] ?? '')) ?>" <?= invalid_attr($k) ?>>
              <?= field_error($k) ?>
            </div>
            <?php endforeach; ?>
          </div>
          <p class="hint" style="margin-top:10px">เปลี่ยนอีเมลแล้วต้องยืนยันอีเมลใหม่ก่อนจองครั้งต่อไป</p>
          <button class="btn" type="submit" style="margin-top:14px">บันทึกข้อมูล</button>
        </form>
      </section>
    </div>

    <aside class="booking-aside">
      <section class="panel" id="password" aria-labelledby="h-pw">
        <div class="panel-title"><?= icon('key-round') ?><h2 id="h-pw">เปลี่ยนรหัสผ่าน</h2></div>
        <form method="post" action="<?= e(url('/account/password')) ?>" novalidate>
          <?= csrf_field() ?>
          <div class="field">
            <label class="label" for="current_password">รหัสผ่านปัจจุบัน</label>
            <input class="input" id="current_password" name="current_password" type="password" autocomplete="current-password" <?= invalid_attr('current_password') ?>>
            <?= field_error('current_password') ?>
          </div>
          <div class="field">
            <label class="label" for="new_password">รหัสผ่านใหม่</label>
            <input class="input" id="new_password" name="password" type="password" autocomplete="new-password" <?= invalid_attr('password') ?>>
            <div class="pw-meter" data-pw-meter="new_password" aria-hidden="true"><i></i></div>
            <?= field_error('password') ?>
          </div>
          <div class="field">
            <label class="label" for="password_confirmation">ยืนยันรหัสผ่านใหม่</label>
            <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" <?= invalid_attr('password_confirmation') ?>>
            <?= field_error('password_confirmation') ?>
          </div>
          <button class="btn btn-ghost btn-block" type="submit" style="margin-top:14px">เปลี่ยนรหัสผ่าน</button>
        </form>
      </section>
    </aside>
  </div>
</div>
