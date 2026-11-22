<?php
/** @var array $d @var string $from @var string $to @var bool $print */
use App\Services\Status;
\App\Core\View::partial('report-head', get_defined_vars());
?>
<div<?= $print ? ' class="report-sheet"' : '' ?>>
<dl class="stats">
  <div><dt>นัดทั้งหมด</dt><dd><?= (int) $d['total'] ?></dd></div>
  <?php foreach (Status::APPOINTMENT_TYPE as $k => $l): ?><div><dt><?= e($l) ?></dt><dd><?= array_sum($d['matrix'][$k]) ?></dd></div><?php endforeach; ?>
  <div><dt>ลองชุดแล้วไม่มา</dt><dd><?= $d['no_show_rate'] === null ? '–' : e(round($d['no_show_rate'] * 100)) . '%' ?></dd></div>
</dl>
<div class="tablewrap" style="margin-bottom:16px">
  <table class="table">
    <caption class="sr-only">จำนวนนัดตามประเภทและสถานะ</caption>
    <thead><tr><th>ประเภทนัด</th><?php foreach (Status::APPOINTMENT as [$l]): ?><th class="r"><?= e($l) ?></th><?php endforeach; ?><th class="r">รวม</th></tr></thead>
    <tbody>
      <?php foreach ($d['matrix'] as $t => $row): ?>
      <tr><td><?= e(Status::APPOINTMENT_TYPE[$t]) ?></td><?php foreach ($row as $n): ?><td class="r num"><?= $n ?: '<span class="faint">0</span>' ?></td><?php endforeach; ?><td class="r num"><b><?= array_sum($row) ?></b></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<div class="tablewrap">
  <table class="table">
    <caption class="sr-only">รายการนัดหมายทั้งหมดในช่วงนี้</caption>
    <thead><tr><th>วันเวลา</th><th>ประเภท</th><th>ลูกค้า</th><th>เบอร์โทร</th><th>การจอง</th><th>สถานะ</th><th>หมายเหตุ</th></tr></thead>
    <tbody>
      <?php foreach ($d['rows'] as $a): ?>
      <tr>
        <td class="num nowrap"><?= e(thai_date(substr($a['starts_at'], 0, 10), false, false)) ?> <?= e(substr($a['starts_at'], 11, 5)) ?></td>
        <td><?= e(Status::APPOINTMENT_TYPE[$a['type']]) ?></td>
        <td><?= e(full_name($a)) ?></td>
        <td class="num"><?= e(phone_fmt($a['phone'])) ?></td>
        <td class="num"><?= $a['booking_no'] ? e($a['booking_no'] . ' · ' . $a['costume_code']) : '–' ?></td>
        <td><?= e(Status::label(Status::APPOINTMENT, $a['status'])) ?></td>
        <td class="small"><?= e($a['note'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$d['rows']): ?><tr><td colspan="7" class="muted">ไม่มีนัดในช่วงนี้</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
</div>
