<?php
/** @var string $date @var array $items @var array $week @var array $slots @var ?array $member */
use App\Services\Status;
$errs = form_errors();
$memberLabel = $member ? full_name($member) . ($member['nickname'] ? ' (' . $member['nickname'] . ')' : '') : '';
?>
<div class="page-head">
  <div><h1>นัดหมาย</h1><p class="muted"><?= e(thai_date_full($date)) ?> · <?= count($items) ?> นัด</p></div>
  <form class="row datenav" method="get" action="<?= e(url('/admin/appointments')) ?>">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/appointments', ['date' => add_days($date, -1)])) ?>" aria-label="วันก่อนหน้า"><?= icon('chevron-left', 'ic ic-sm') ?></a>
    <label class="sr-only" for="ap-date">วันที่</label>
    <input class="input" id="ap-date" type="date" name="date" value="<?= e($date) ?>" data-autosubmit style="min-height:40px">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/appointments', ['date' => add_days($date, 1)])) ?>" aria-label="วันถัดไป"><?= icon('chevron-right', 'ic ic-sm') ?></a>
    <?php if ($date !== today()): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/appointments')) ?>">วันนี้</a><?php endif; ?>
  </form>
</div>
<div class="week" style="margin-bottom:16px">
  <?php for ($i = -3; $i <= 10; $i++): $d = add_days($date, $i); if ($i < -1 || $i > 5) continue; $n = (int) ($week[$d] ?? 0); ?>
  <a class="week-day<?= $d === $date ? ' is-busy' : '' ?>" href="<?= e(url('/admin/appointments', ['date' => $d])) ?>" style="text-decoration:none" <?= $d === $date ? 'aria-current="date"' : '' ?>>
    <b><?= e(THAI_DAYS_SHORT[(int) date('w', strtotime($d))] . ' ' . (int) substr($d, 8) . ' ' . THAI_MONTHS_SHORT[(int) substr($d, 5, 2)]) ?></b><?= $n ? $n . ' นัด' : 'ว่าง' ?>
  </a>
  <?php endfor; ?>
</div>
<div class="formgrid">
  <section class="box" aria-labelledby="h-list">
    <div class="box-head"><h2 id="h-list">นัดของวันนี้</h2></div>
    <?php if (!$items): ?><p class="small muted">ไม่มีนัดในวันนี้</p><?php endif; ?>
    <ul class="rows">
      <?php foreach ($items as $a): ?>
      <li class="appt">
        <b class="num"><?= e(substr($a['starts_at'], 11, 5)) ?></b>
        <span>
          <b><?= e(Status::APPOINTMENT_TYPE[$a['type']]) ?> · <a href="<?= e(url('/admin/members/' . $a['user_id'])) ?>"><?= e(full_name($a)) ?></a></b>
          <div class="sub"><span class="num"><?= e(phone_fmt($a['phone'])) ?></span><?= $a['booking_no'] ? ' · <a href="' . e(url('/admin/bookings/' . $a['booking_id'])) . '">' . e($a['booking_no']) . '</a> ' . e($a['costume_code']) : '' ?><?= $a['note'] ? ' · ' . e($a['note']) : '' ?></div>
        </span>
        <span class="row appt-act">
          <?= Status::badge(Status::APPOINTMENT, $a['status']) ?>
          <?php if ($a['status'] === 'scheduled' && $a['type'] === 'fitting'): ?>
          <form method="post" action="<?= e(url('/admin/appointments/' . $a['id'] . '/status')) ?>" class="row" style="gap:4px">
            <?= csrf_field() ?>
            <button class="btn btn-xs" name="status" value="done" type="submit">มาแล้ว</button>
            <button class="btn btn-xs btn-ghost" name="status" value="no_show" type="submit">ไม่มา</button>
            <button class="btn btn-xs btn-ghost" name="status" value="cancelled" type="submit">ยกเลิก</button>
          </form>
          <?php endif; ?>
        </span>
      </li>
      <?php endforeach; ?>
    </ul>
    <p class="small muted" style="margin-top:12px">นัดรับและนัดคืนชุดสร้างจากการจองโดยอัตโนมัติ และปิดเองเมื่อบันทึกรับหรือคืนชุด</p>
  </section>

  <form class="box" method="post" action="<?= e(url('/admin/appointments')) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="date" value="<?= e($date) ?>">
    <div class="box-head"><h2>เพิ่มนัดลองชุด</h2></div>
    <div class="field" data-member-lookup="<?= e(url('/admin/api/members')) ?>">
      <label class="label" for="ap-mq">ลูกค้า</label>
      <input class="input" id="ap-mq" type="search" autocomplete="off" placeholder="ชื่อ เบอร์โทร หรืออีเมล" value="<?= e($memberLabel) ?>" <?= invalid_attr('user_id') ?>>
      <input type="hidden" name="user_id" value="<?= e((string) ($member['id'] ?? '')) ?>">
      <div class="lookup" data-member-results></div>
      <p class="hint" data-member-chosen hidden></p>
      <?= field_error('user_id') ?>
    </div>
    <fieldset class="field" style="border:0;padding:0;margin:14px 0 0">
      <legend class="label">เวลา (<?= e(thai_date($date, false)) ?>)</legend>
      <div class="slots">
        <?php $any = false; foreach ($slots as $s): $any = $any || $s['left'] > 0; ?>
        <label><input type="radio" name="time" value="<?= e($s['time']) ?>" <?= $s['left'] ? '' : 'disabled' ?> <?= old('time') === $s['time'] ? 'checked' : '' ?>><span><?= e($s['time']) ?><small><?= $s['left'] ? 'ว่าง ' . $s['left'] : 'เต็ม' ?></small></span></label>
        <?php endforeach; ?>
      </div>
      <?php if (!$any): ?><p class="hint">วันนี้ไม่มีช่วงเวลาว่างแล้ว เลือกวันอื่นด้านบน</p><?php endif; ?>
      <?= field_error('time') ?>
    </fieldset>
    <div class="field" style="margin-top:14px"><label class="label" for="ap-note">หมายเหตุ <span class="opt">(ถ้ามี)</span></label><input class="input" id="ap-note" name="note" maxlength="255" placeholder="เช่น อยากลองชุดไทยสีเขียว 2 ชุด" value="<?= e(old('note')) ?>"></div>
    <button class="btn btn-block" type="submit" style="margin-top:14px" <?= $any ? '' : 'disabled' ?>>บันทึกนัดและแจ้งลูกค้า</button>
  </form>
</div>
