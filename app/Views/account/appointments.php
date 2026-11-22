<?php
/** @var array $items @var ?array $costume @var string $date @var array $slots */
use App\Services\Status;
$upcoming = array_filter($items, fn($a) => $a['status'] === 'scheduled' && $a['starts_at'] >= date('Y-m-d H:i:s'));
$past = array_filter($items, fn($a) => !($a['status'] === 'scheduled' && $a['starts_at'] >= date('Y-m-d H:i:s')));
?>
<div class="col">
  <div class="page-head">
    <h1>นัดหมาย</h1>
    <p>นัดลองชุดที่ร้านก่อนจอง หรือดูนัดรับและคืนชุดของคุณ</p>
  </div>
  <?php \App\Core\View::partial('acct-nav'); ?>
  <div class="booking-grid">
    <div>
      <section class="panel" aria-labelledby="h-up">
        <div class="panel-title"><?= icon('calendar-clock') ?><h2 id="h-up">นัดที่กำลังจะถึง</h2></div>
        <?php if (!$upcoming): ?><p class="muted">ยังไม่มีนัด เลือกวันและเวลาได้จากแบบฟอร์มนัดลองชุด</p><?php else: ?>
        <ul class="rowlist">
          <?php foreach ($upcoming as $a): ?>
          <li>
            <span>
              <b><?= e(Status::label(Status::APPOINTMENT_TYPE, $a['type'])) ?></b> · <?= e(thai_date_full(substr($a['starts_at'], 0, 10))) ?> <?= e(substr($a['starts_at'], 11, 5)) ?> น.
              <br><span class="small muted"><?= e($a['costume_name'] ? $a['costume_name'] . ' · ' . $a['booking_no'] : ($a['note'] ?: 'ลองชุดที่ร้าน')) ?></span>
            </span>
            <?php if ($a['type'] === 'fitting'): ?>
            <form method="post" action="<?= e(url('/account/appointments/' . $a['id'] . '/cancel')) ?>" data-confirm="ยกเลิกนัดลองชุดนี้?"><?= csrf_field() ?><button class="btn btn-xs btn-ghost" type="submit">ยกเลิกนัด</button></form>
            <?php else: ?>
            <a class="btn btn-xs btn-ghost" href="<?= e(url('/bookings/' . $a['booking_id'])) ?>">ดูการจอง</a>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </section>
      <?php if ($past): ?>
      <section class="panel" aria-labelledby="h-past">
        <div class="panel-title"><?= icon('list-checks') ?><h2 id="h-past">นัดที่ผ่านมา</h2></div>
        <ul class="rowlist">
          <?php foreach ($past as $a): ?>
          <li>
            <span class="small"><?= e(Status::label(Status::APPOINTMENT_TYPE, $a['type'])) ?> · <?= e(thai_date(substr($a['starts_at'], 0, 10))) ?> <?= e(substr($a['starts_at'], 11, 5)) ?></span>
            <?= Status::badge(Status::APPOINTMENT, $a['status']) ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>
    </div>
    <aside class="booking-aside">
      <form class="panel" method="post" action="<?= e(url('/account/appointments')) ?>" data-slots="<?= e(url('/api/slots')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="panel-title"><?= icon('hanger') ?><h2>นัดลองชุด</h2></div>
        <?php if ($costume): ?>
          <input type="hidden" name="dress" value="<?= e($costume['code']) ?>">
          <p class="small" style="margin-bottom:12px">ชุดที่อยากลอง: <b><?= e($costume['code']) ?> <?= e($costume['name']) ?></b></p>
        <?php endif; ?>
        <div class="field">
          <label class="label" for="ap-date">วันที่</label>
          <input class="input" id="ap-date" type="date" name="date" value="<?= e(old('date', $date)) ?>" min="<?= e(today()) ?>" max="<?= e(add_days(today(), 60)) ?>" required>
        </div>
        <fieldset class="field">
          <legend class="label" style="margin-bottom:6px">เวลา <span class="opt">(ช่องละ 30 นาที รับได้ช่วงละ 2 คน)</span></legend>
          <div data-slot-box aria-live="polite">
            <?php if (!$slots): ?><p class="muted">วันนี้ไม่มีช่วงเวลาว่าง ลองเลือกวันอื่น</p><?php endif; ?>
            <div class="slots">
              <?php foreach ($slots as $s): ?>
              <label><input type="radio" name="time" value="<?= e($s['time']) ?>" <?= $s['left'] > 0 ? '' : 'disabled' ?> <?= old('time') === $s['time'] ? 'checked' : '' ?> required><span><?= e($s['time']) ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
          <?= field_error('time') ?>
        </fieldset>
        <div class="field">
          <label class="label" for="ap-note">อยากลองชุดแบบไหน <span class="opt">(ไม่บังคับ)</span></label>
          <textarea class="textarea" id="ap-note" name="note" maxlength="300" placeholder="เช่น ชุดไทยสีชมพู 2-3 ชุด ไซส์ M"><?= e(old('note')) ?></textarea>
        </div>
        <?php if (!\App\Core\Auth::user()['email_verified_at']): ?>
          <p class="small" style="margin-top:12px;color:var(--wait)">ยืนยันอีเมลก่อนจึงจะนัดได้ · <a href="<?= e(url('/account/verify')) ?>">ยืนยันอีเมล</a></p>
        <?php endif; ?>
        <button class="btn btn-block" type="submit" style="margin-top:14px"><?= icon('calendar-plus') ?>ยืนยันนัดลองชุด</button>
      </form>
    </aside>
  </div>
</div>
