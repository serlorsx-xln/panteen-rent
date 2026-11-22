<?php
/** @var array $c @var string $ym @var string|null $selected @var string $link  (dress|book) */
use App\Services\Availability;

[$y, $m] = array_map('intval', explode('-', $ym));
$days = Availability::month((int) $c['id'], $y, $m);
$first = sprintf('%04d-%02d-01', $y, $m);
$lead = (int) date('w', strtotime($first));
$prev = date('Y-m', strtotime($first . ' -1 month'));
$next = date('Y-m', strtotime($first . ' +1 month'));
$canPrev = $prev >= date('Y-m');
$canNext = $next <= date('Y-m', strtotime(add_days(today(), (int) setting('booking_horizon_days'))));
$today = today();
$code = rawurlencode($c['code']);
$api = url('/api/dress/' . $code . '/calendar');
$dayHref = fn(string $d) => $link === 'book' ? url('/book/' . $code, ['event' => $d]) : url('/dress/' . $code, ['date' => $d]) . '#verdict';
$labels = ['free' => 'ว่าง', 'busy' => 'ไม่ว่าง', 'past' => 'ผ่านไปแล้ว', 'off' => 'ยังไม่เปิดจอง'];
?>
<div class="cal" data-cal data-api="<?= e($api) ?>" data-link="<?= e($link) ?>" data-selected="<?= e($selected ?? '') ?>">
  <div class="cal-head">
    <h2 aria-live="polite"><?= e(thai_month_year($y, $m)) ?></h2>
    <div class="cal-nav">
      <a class="iconbtn<?= $canPrev ? '' : ' is-disabled' ?>" href="<?= e(url('/dress/' . $code, ['ym' => $prev])) ?>" data-cal-go="<?= e($prev) ?>" aria-label="เดือนก่อนหน้า" <?= $canPrev ? '' : 'aria-disabled="true" tabindex="-1"' ?>><?= icon('chevron-left') ?></a>
      <a class="iconbtn<?= $canNext ? '' : ' is-disabled' ?>" href="<?= e(url('/dress/' . $code, ['ym' => $next])) ?>" data-cal-go="<?= e($next) ?>" aria-label="เดือนถัดไป" <?= $canNext ? '' : 'aria-disabled="true" tabindex="-1"' ?>><?= icon('chevron-right') ?></a>
    </div>
  </div>
  <div class="cal-grid" role="group" aria-label="ปฏิทินว่างของชุด <?= e($c['code']) ?> เดือน<?= e(thai_month_year($y, $m)) ?>">
    <?php foreach (['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'] as $dow): ?><span class="cal-dow" aria-hidden="true"><?= $dow ?></span><?php endforeach; ?>
    <?php for ($i = 0; $i < $lead; $i++): ?><span aria-hidden="true"></span><?php endfor; ?>
    <?php foreach ($days as $d => $state):
        $cls = 'cal-day cal-' . $state . ($d === $today ? ' cal-today' : '') . ($d === $selected ? ' cal-sel' : '');
        $label = thai_date($d, true) . ' ' . $labels[$state] . ($d === $selected ? ' (วันงานที่เลือก)' : '');
        $n = (int) substr($d, 8);
        if ($state === 'free'): ?>
      <a class="<?= $cls ?>" href="<?= e($dayHref($d)) ?>" data-date="<?= $d ?>" aria-label="<?= e($label) ?>"><?= $n ?></a>
    <?php else: ?>
      <span class="<?= $cls ?>" data-date="<?= $d ?>" aria-label="<?= e($label) ?>"><?= $n ?></span>
    <?php endif; endforeach; ?>
  </div>
  <div class="cal-legend" aria-hidden="true">
    <span><i></i>ว่าง แตะเพื่อเลือกเป็นวันงาน</span>
    <span><i class="lb"></i>ไม่ว่าง (ชนช่วงรับ–คืน–ซักของคนอื่น)</span>
    <?php if ($selected): ?><span><i class="ls"></i>วันงานของคุณ</span><?php endif; ?>
    <?php if ($link === 'book'): ?><span><i class="lr"></i>ช่วงรับ–คืนชุด</span><?php endif; ?>
  </div>
</div>
