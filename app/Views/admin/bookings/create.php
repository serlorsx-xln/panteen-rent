<?php
/** @var ?array $costume @var ?array $member @var array $costumes */
use App\Services\Pricing;

$errs = form_errors();
$set = ['base_days' => setting('base_days'), 'max_days' => setting('max_days'), 'cleaning_days' => setting('cleaning_days')];
$ev = old('event_date', '');
[$pu, $rt] = $ev ? Pricing::defaultWindow($ev) : ['', ''];
$memberLabel = $member ? full_name($member) . ($member['nickname'] ? ' (' . $member['nickname'] . ')' : '') : '';
?>
<nav class="crumbs"><a href="<?= e(url('/admin/bookings')) ?>"><?= icon('arrow-left', 'ic ic-sm') ?> การจองทั้งหมด</a></nav>
<div class="page-head">
  <div><h1>จองหน้าร้าน</h1><p class="muted">ลูกค้ามาที่ร้านหรือโทรมา เลือกชุด เลือกลูกค้า แล้วใส่วันงาน</p></div>
</div>

<div class="formgrid">
  <div>
    <form class="box" method="get" action="<?= e(url('/admin/bookings/new')) ?>">
      <div class="box-head"><h2>1 · ชุด</h2></div>
      <?php if ($member): ?><input type="hidden" name="member" value="<?= (int) $member['id'] ?>"><?php endif; ?>
      <div class="row" style="align-items:flex-end">
        <div class="field" style="flex:1;min-width:240px">
          <label class="label" for="dress">เลือกชุดที่เปิดให้เช่า</label>
          <select class="select" id="dress" name="dress" data-autosubmit>
            <option value="">— เลือกชุด —</option>
            <?php foreach ($costumes as $c): ?>
            <option value="<?= e($c['code']) ?>" <?= $costume && $costume['code'] === $c['code'] ? 'selected' : '' ?>><?= e($c['code'] . ' · ' . $c['name'] . ' · ' . ($c['size_label'] === 'FREE' ? 'ฟรีไซส์' : $c['size_label']) . ' · ' . number_format((float) $c['rent_price']) . ' บาท') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <noscript><button class="btn btn-ghost" type="submit">เลือก</button></noscript>
      </div>
      <?php if ($costume): ?>
      <div class="mini-post" style="margin-top:14px">
        <img src="<?= e(mini(\App\Services\Catalog::images((int) $costume['id'])[0]['path'] ?? null)) ?>" alt="">
        <div>
          <b><?= e($costume['code']) ?> · <?= e($costume['name']) ?></b>
          <span class="small muted"><?= e($costume['category_name']) ?> · ค่าเช่า <?= e(money($costume['rent_price'])) ?> / <?= (int) $set['base_days'] ?> วัน · มัดจำ <?= e(money($costume['deposit'])) ?></span>
          <a class="small" href="<?= e(url('/dress/' . rawurlencode($costume['code']))) ?>" target="_blank">ดูปฏิทินว่างของชุดนี้</a>
        </div>
      </div>
      <?php endif; ?>
    </form>

    <?php if ($costume): ?>
    <form class="box" style="margin-top:14px" method="post" action="<?= e(url('/admin/bookings')) ?>" data-booking-form data-quote-api="<?= e(url('/api/dress/' . rawurlencode($costume['code']) . '/quote')) ?>" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="costume_code" value="<?= e($costume['code']) ?>">
      <?php if (!empty($errs['form'])): ?><div class="form-error" role="alert"><?= icon('circle-alert') ?><span><?= e($errs['form']) ?></span></div><?php endif; ?>

      <div class="box-head"><h2>2 · ลูกค้า</h2><a href="<?= e(url('/admin/members/new', ['back' => 'booking'])) ?>">สมัครให้ลูกค้าใหม่</a></div>
      <div class="field" data-member-lookup="<?= e(url('/admin/api/members')) ?>" style="position:relative">
        <label class="label" for="mq">ค้นหาสมาชิก</label>
        <input class="input" id="mq" type="search" autocomplete="off" placeholder="ชื่อ ชื่อเล่น เบอร์โทร หรืออีเมล" value="<?= e($memberLabel) ?>" <?= invalid_attr('user_id') ?>>
        <input type="hidden" name="user_id" value="<?= e((string) old('user_id', $member['id'] ?? '')) ?>">
        <div class="lookup" data-member-results></div>
        <p class="hint" data-member-chosen <?= $member ? '' : 'hidden' ?>><?= $member ? 'เลือกแล้ว: ' . e($memberLabel) : '' ?></p>
        <?= field_error('user_id') ?>
      </div>

      <div class="box-head" style="margin-top:20px"><h2>3 · วันงานและวันรับ–คืน</h2></div>
      <div class="field">
        <label class="label" for="event_date">วันงาน</label>
        <input class="input" id="event_date" type="date" name="event_date" value="<?= e($ev) ?>" min="<?= e(today()) ?>" required <?= invalid_attr('event_date') ?>>
        <?= field_error('event_date') ?>
      </div>
      <div class="grid-2 keep" style="margin-top:12px">
        <div class="field"><label class="label" for="pickup_date">วันรับชุด</label><input class="input" id="pickup_date" type="date" name="pickup_date" value="<?= e(old('pickup_date', $pu)) ?>" min="<?= e(today()) ?>" <?= invalid_attr('pickup_date') ?>><?= field_error('pickup_date') ?></div>
        <div class="field"><label class="label" for="return_date">วันคืนชุด</label><input class="input" id="return_date" type="date" name="return_date" value="<?= e(old('return_date', $rt)) ?>" <?= invalid_attr('return_date') ?>><?= field_error('return_date') ?></div>
        <div class="field"><label class="label" for="pickup_time">เวลารับ</label><input class="input" id="pickup_time" type="time" name="pickup_time" value="<?= e(old('pickup_time', setting('default_pickup_time', '13:00'))) ?>"></div>
        <div class="field"><label class="label" for="return_time">เวลาคืน</label><input class="input" id="return_time" type="time" name="return_time" value="<?= e(old('return_time', setting('default_return_time', '13:00'))) ?>"></div>
      </div>
      <p class="quote-state" data-quote-state role="status" style="margin-top:14px"><?= $ev ? '' : 'ใส่วันงานเพื่อดูว่าชุดว่างไหมและคำนวณราคา' ?></p>
      <div data-quote-out hidden></div>
      <div class="field" style="margin-top:14px"><label class="label" for="note">บันทึก <span class="opt">(ถ้ามี)</span></label><textarea class="input" id="note" name="note" rows="2" maxlength="500" placeholder="เช่น ขอแก้ความยาวกระโปรง"><?= e(old('note')) ?></textarea></div>

      <div class="ship-box" style="margin-top:20px"><?php \App\Core\View::partial('delivery', ['addresses' => $addresses, 'staff' => true]); ?></div>

      <div class="box-head" style="margin-top:20px"><h2>4 · การชำระเงิน</h2></div>
      <fieldset class="field" style="border:0;padding:0;margin:0">
        <legend class="label">ลูกค้าชำระตอนนี้ไหม</legend>
        <?php $pn = old('pay_now', 'cash'); foreach (['cash' => 'จ่ายเงินสดที่ร้านแล้ว', 'transfer' => 'โอนแล้ว (ดูสลิปแล้ว)', 'promptpay' => 'สแกนพร้อมเพย์แล้ว', '' => 'ยังไม่จ่าย ให้ลูกค้าโอนภายหลัง (ล็อกชุดไว้ ' . (int) setting('hold_hours', 24) . ' ชม.)'] as $k => $l): ?>
        <label class="check"><input type="radio" name="pay_now" value="<?= e($k) ?>" <?= $pn === $k ? 'checked' : '' ?>><span><?= e($l) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
      <button class="btn btn-lg btn-block" type="submit" style="margin-top:16px" data-booking-submit>สร้างการจอง</button>
    </form>
    <?php endif; ?>
  </div>
  <aside class="box" aria-label="สรุปการจอง">
    <div class="box-head"><h2>เงื่อนไขที่ใช้</h2></div>
    <dl class="kv">
      <dt>ค่าเช่ารวม</dt><dd><?= (int) $set['base_days'] ?> วัน</dd>
      <dt>เช่าได้สูงสุด</dt><dd><?= (int) $set['max_days'] ?> วัน</dd>
      <?php if ($costume): ?><dt>วันเพิ่ม (ชุดนี้)</dt><dd><?= e(money($costume['extra_day_price'])) ?> / วัน</dd>
      <dt>ปรับคืนช้า</dt><dd><?= e(money($costume['late_fee_per_day'])) ?> / วัน</dd><?php endif; ?>
      <dt>เวลาซักหลังคืน</dt><dd><?= (int) $set['cleaning_days'] ?> วัน</dd>
    </dl>
    <p class="small muted" style="margin-top:12px">วันรับ–คืนตั้งต้นให้เป็นวันก่อนงาน 1 วันถึงวันหลังงาน 1 วัน ปรับได้ตามที่ลูกค้าขอ</p>
  </aside>
</div>
