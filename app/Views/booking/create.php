<?php
/** @var array $c @var ?array $image @var ?string $event @var ?string $pickup @var ?string $return @var ?array $state @var array $fit @var list<array> $addresses */
use App\Core\Auth;

$code = rawurlencode($c['code']);
$errors = form_errors();
$u = Auth::user();
$verified = $u && $u['email_verified_at'];
$min = add_days(today(), (int) setting('min_lead_days'));
$max = add_days(today(), (int) setting('booking_horizon_days'));
$free = $state && ($state['ok'] ?? false);
/* a date field in the same hand as the search under the opening photograph: the Thai reading on top, the native picker under it */
$day = static function (string $name, string $label, ?string $value) use ($min, $max): string {
    $v = (string) old($name, $value ?? '');
    $v = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    return '<label class="desk-cell desk-date book-day" data-desk>'
        . '<span class="desk-label">' . e($label) . '</span>'
        . '<span class="desk-value' . ($v ? '' : ' is-empty') . '" data-desk-text data-empty="เลือกวัน" aria-hidden="true">' . ($v ? e(thai_date_dow($v)) : 'เลือกวัน') . '</span>'
        . '<input class="desk-native" type="date" id="' . e($name) . '" name="' . e($name) . '" value="' . e($v) . '" min="' . e($min) . '" max="' . e($max) . '" required data-desk-input ' . invalid_attr($name) . '>'
        . icon('calendar') . '</label>' . field_error($name);
};
?>
<div class="wrap">
  <nav class="crumbs" aria-label="ตำแหน่งหน้า">
    <a href="<?= e(url('/dress/' . $code)) ?>"><?= icon('arrow-left', 'ic ic-sm') ?> กลับไปที่ชุด <?= e($c['code']) ?></a>
  </nav>
  <div class="page-head">
    <h1>จองชุด</h1>
    <p>ตรวจวันรับ-คืนและยอดเงินให้ครบ ร้านล็อกชุดไว้ให้ <?= (int) setting('hold_hours') ?> ชั่วโมงหลังกดยืนยันเพื่อรอชำระ</p>
  </div>

  <?php if (!$u): ?>
  <div class="panel">
    <div class="mini-post">
      <img src="<?= e(mini($image['path'] ?? null)) ?>" alt="">
      <div><b><?= e($c['name']) ?></b><span class="small muted"><?= e($c['code']) ?> · <?= e(price_tag($c['rent_price'])) ?></span></div>
    </div>
    <p style="margin-top:14px">เข้าสู่ระบบหรือสมัครสมาชิกก่อนจอง ร้านต้องใช้ชื่อและเบอร์โทรเพื่อยืนยันตัวตนตอนรับชุด</p>
    <div class="row" style="margin-top:14px">
      <a class="btn" href="<?= e(url('/login')) ?>">เข้าสู่ระบบ</a>
      <a class="btn btn-ghost" href="<?= e(url('/register')) ?>">สมัครสมาชิก</a>
    </div>
  </div>
  <?php elseif (!$verified): ?>
  <div class="panel">
    <h2>ยืนยันอีเมลก่อนจอง</h2>
    <p class="muted" style="margin-top:6px">เราส่งลิงก์ยืนยันไปที่ <?= e($u['email']) ?> แล้ว กดลิงก์ในอีเมล แล้วกลับมาจองชุดนี้ได้เลย</p>
    <form method="post" action="<?= e(url('/account/verify')) ?>" style="margin-top:14px"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">ส่งลิงก์ยืนยันอีกครั้ง</button></form>
  </div>
  <?php else: ?>
  <form class="booking-grid" method="post" action="<?= e(url('/book/' . $code)) ?>" data-booking-form data-quote-api="<?= e(url('/api/dress/' . $code . '/quote')) ?>" novalidate>
    <?= csrf_field() ?>
    <div>
      <section class="panel" aria-labelledby="h-when">
        <div class="panel-title"><?= icon('calendar-days') ?><h2 id="h-when">วันงานและช่วงเช่า</h2></div>
        <div class="window">
          <div class="field full"><?= $day('event_date', 'วันงาน', $event) ?></div>
          <div class="field"><?= $day('pickup_date', 'วันรับชุด', $pickup) ?></div>
          <div class="field"><?= $day('return_date', 'วันคืนชุด', $return) ?></div>
          <div class="field" data-when="pickup">
            <label class="label" for="pickup_time">เวลารับชุด</label>
            <input class="input" type="time" id="pickup_time" name="pickup_time" value="<?= e(old('pickup_time', setting('default_pickup_time'))) ?>" min="<?= e(setting('open_time')) ?>" max="<?= e(setting('close_time')) ?>" step="1800">
          </div>
          <div class="field" data-when="store">
            <label class="label" for="return_time">เวลาคืนชุด</label>
            <input class="input" type="time" id="return_time" name="return_time" value="<?= e(old('return_time', setting('default_return_time'))) ?>" min="<?= e(setting('open_time')) ?>" max="<?= e(setting('close_time')) ?>" step="1800">
          </div>
        </div>
        <p class="hint" style="margin-top:10px">ค่าเช่ารวม <?= (int) setting('base_days') ?> วันนับรวมวันรับและวันคืน เช่าได้สูงสุด <?= (int) setting('max_days') ?> วัน · ร้านเปิด <?= e(setting('open_time')) ?>–<?= e(setting('close_time')) ?> น.
          <span data-when="ship"> · ส่งพัสดุ: วันรับชุดคือวันที่ชุดถึงมือคุณ วันคืนชุดคือวันสุดท้ายที่ฝากส่งคืนได้</span></p>
        <p class="quote-state <?= $state ? ($free ? 'is-free' : 'is-busy') : '' ?>" data-quote-state role="status" aria-live="polite">
          <?php if ($state && !$state['ok'] && isset($state['message'])): ?><?= icon('circle-alert') ?><?= e($state['message']) ?>
          <?php elseif ($state && $free): ?><?= icon('circle-check') ?>ว่างตลอดช่วงที่เลือก
          <?php elseif ($state): ?><?= icon('calendar-x') ?>ชุดนี้มีคนจองในช่วงที่เลือกแล้ว ลองเปลี่ยนวัน
          <?php endif; ?>
        </p>
        <?php if (!empty($errors['form'])): ?><p class="form-error"><?= icon('circle-alert') ?><?= e($errors['form']) ?></p><?php endif; ?>
      </section>

      <?php \App\Core\View::partial('delivery', ['addresses' => $addresses]); ?>

      <section class="panel" aria-labelledby="h-note">
        <div class="panel-title"><?= icon('message-circle') ?><h2 id="h-note">ฝากถึงร้าน <span class="small faint">(ไม่บังคับ)</span></h2></div>
        <label class="sr-only" for="note">ข้อความถึงร้าน</label>
        <textarea class="textarea" id="note" name="note" maxlength="500" placeholder="เช่น ขอเข็มกลัดเพิ่ม ขอให้ช่วยแต่งตัว มารับช่วงเย็น"><?= e(old('note')) ?></textarea>
      </section>

      <section class="panel" aria-labelledby="h-terms">
        <div class="panel-title"><?= icon('file-text') ?><h2 id="h-terms">เงื่อนไขการเช่า</h2></div>
        <div class="terms" tabindex="0" role="region" aria-label="ข้อความเงื่อนไขการเช่า (เลื่อนอ่านได้)"><?= e(setting('rental_terms')) ?></div>
        <label class="check" style="margin-top:14px">
          <input type="checkbox" name="agree" value="1" <?= old('agree') ? 'checked' : '' ?> <?= invalid_attr('agree') ?>>
          <span>อ่านแล้วและยอมรับเงื่อนไขการเช่า การคืนมัดจำ และค่าปรับคืนช้า</span>
        </label>
        <?= field_error('agree') ?>
      </section>
    </div>

    <aside class="booking-aside">
      <div class="panel">
        <div class="mini-post">
          <img src="<?= e(mini($image['path'] ?? null)) ?>" alt="">
          <div>
            <b><?= e($c['name']) ?></b>
            <span class="small muted"><?= e($c['code']) ?> · <?= e($c['size_label'] === 'FREE' ? 'ฟรีไซส์' : 'ไซส์ ' . $c['size_label']) ?></span>
          </div>
        </div>
        <?php if ($fit['state'] !== 'unknown'): ?>
          <p class="fit fit-<?= e($fit['state']) ?>"><?= icon($fit['state'] === 'fit' ? 'circle-check' : 'circle-alert') ?><span><?= e($fit['text']) ?></span></p>
        <?php endif; ?>
        <div data-quote-out>
          <?php if ($state && isset($state['quote'])): ?>
            <?php \App\Core\View::partial('quote', ['q' => $state['quote'], 'c' => $c, 'pickup' => $pickup, 'return' => $return]); ?>
          <?php else: ?>
            <p class="muted small" style="margin-top:14px">เลือกวันงาน วันรับ และวันคืน แล้วยอดเงินจะแสดงตรงนี้</p>
          <?php endif; ?>
        </div>
        <div class="noreturn">
          <?= icon('lock') ?>
          <span>กด <b>ยืนยันการจอง</b> แล้ว ร้านจะล็อกชุดไว้ให้และรอชำระ <?= (int) setting('hold_hours') ?> ชั่วโมง ยกเลิกได้ฟรีก่อนวันรับชุด <?= (int) setting('cancel_before_days') ?> วัน</span>
        </div>
        <button class="btn btn-lg btn-block" type="submit" style="margin-top:14px" data-booking-submit <?= $state && !$free ? 'disabled' : '' ?>><?= icon('calendar-check') ?>ยืนยันการจอง</button>
      </div>
    </aside>
  </form>

  <div class="panel" data-cal-wrap>
    <h2>วันที่ชุดนี้ว่าง</h2>
    <?php \App\Core\View::partial('calendar', ['c' => $c, 'ym' => $event ? substr($event, 0, 7) : date('Y-m'), 'selected' => $event, 'link' => 'book']); ?>
  </div>
  <?php endif; ?>
</div>
