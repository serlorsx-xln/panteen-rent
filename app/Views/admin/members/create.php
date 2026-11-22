<?php /** @var string $back */ ?>
<nav class="crumbs"><a href="<?= e(url($back === 'booking' ? '/admin/bookings/new' : '/admin/members')) ?>"><?= icon('arrow-left', 'ic ic-sm') ?> <?= $back === 'booking' ? 'กลับไปหน้าจอง' : 'สมาชิกทั้งหมด' ?></a></nav>
<div class="page-head"><div><h1>สมัครให้ลูกค้าหน้าร้าน</h1><p class="muted">ระบบส่งลิงก์ตั้งรหัสผ่านไปที่อีเมลลูกค้า ลูกค้าเข้าดูการจองของตัวเองได้ภายหลัง</p></div></div>
<form class="box" method="post" action="<?= e(url('/admin/members')) ?>" style="max-width:640px" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="back" value="<?= e($back) ?>">
  <div class="grid-2">
    <div class="field"><label class="label" for="first_name">ชื่อ</label><input class="input" id="first_name" name="first_name" value="<?= e(old('first_name')) ?>" required autocomplete="off" <?= invalid_attr('first_name') ?>><?= field_error('first_name') ?></div>
    <div class="field"><label class="label" for="last_name">นามสกุล</label><input class="input" id="last_name" name="last_name" value="<?= e(old('last_name')) ?>" required autocomplete="off" <?= invalid_attr('last_name') ?>><?= field_error('last_name') ?></div>
    <div class="field"><label class="label" for="nickname">ชื่อเล่น <span class="opt">(ถ้ามี)</span></label><input class="input" id="nickname" name="nickname" value="<?= e(old('nickname')) ?>" autocomplete="off"></div>
    <div class="field"><label class="label" for="phone">เบอร์โทร</label><input class="input num" id="phone" name="phone" type="tel" inputmode="tel" value="<?= e(old('phone')) ?>" required autocomplete="off" <?= invalid_attr('phone') ?>><?= field_error('phone') ?></div>
    <div class="field"><label class="label" for="email">อีเมล</label><input class="input" id="email" name="email" type="email" value="<?= e(old('email')) ?>" required autocomplete="off" <?= invalid_attr('email') ?>><?= field_error('email') ?></div>
    <div class="field"><label class="label" for="line_id">LINE ID <span class="opt">(ถ้ามี)</span></label><input class="input" id="line_id" name="line_id" value="<?= e(old('line_id')) ?>" autocomplete="off"></div>
  </div>
  <button class="btn btn-lg" type="submit" style="margin-top:18px"><?= $back === 'booking' ? 'สมัครแล้วไปหน้าจอง' : 'สมัครสมาชิก' ?></button>
</form>
