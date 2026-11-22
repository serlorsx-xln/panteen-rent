<?php
/** @var array $d @var string $from @var string $to @var bool $print */
use App\Services\Status;
\App\Core\View::partial('report-head', get_defined_vars());
$series = [];
for ($x = $from; $x <= $to; $x = add_days($x, 1)) { $series[$x] = $d['series'][$x] ?? 0; }
$peak = max(1, ...array_values($series) ?: [1]);
$catMax = max(1, ...array_column($d['by_category'], 'count') ?: [1]);
$keys = array_keys($series);
?>
<?php if (!$print): ?><div><?php else: ?><div class="report-sheet"><?php endif; ?>
<dl class="stats">
  <div><dt>เช่าสำเร็จ (ครั้ง)</dt><dd><?= (int) $d['rented'] ?></dd></div>
  <div><dt>ค่าเช่ารวม (บาท)</dt><dd><?= e(number_format($d['revenue'])) ?></dd></div>
  <div><dt>เฉลี่ยต่อครั้ง (บาท)</dt><dd><?= e(number_format($d['avg'])) ?></dd></div>
  <div><dt>วันเช่ารวม</dt><dd><?= (int) $d['days'] ?></dd></div>
  <div><dt>คืนช้า (ครั้ง)</dt><dd><?= (int) $d['late'] ?></dd></div>
</dl>

<section class="box" style="margin-bottom:16px" aria-labelledby="h-daily">
  <div class="box-head"><h2 id="h-daily">จำนวนชุดที่ออกเช่าตามวันรับชุด</h2><span class="small muted">สูงสุด <?= $peak ?> ชุด/วัน</span></div>
  <?php if (!$d['rented']): ?><p class="small muted">ช่วงนี้ยังไม่มีการเช่าที่ยืนยันแล้ว</p><?php else: ?>
  <div class="daybar-wrap">
    <div class="yaxis" aria-hidden="true"><span><?= $peak ?></span><span>0</span></div>
    <div>
      <div class="daybars" role="img" aria-label="กราฟจำนวนชุดที่ออกเช่ารายวัน ดูตัวเลขแต่ละวันได้ในตารางด้านล่าง">
        <?php foreach ($series as $day => $n): ?>
        <div style="height:<?= $n ? max(3, round(100 * $n / $peak)) : 0 ?>%" title="<?= e(thai_date($day, false, false) . ' · ' . $n . ' ชุด') ?>"></div>
        <?php endforeach; ?>
      </div>
      <div class="daybars-axis" aria-hidden="true"><span><?= e(thai_date($keys[0], false, false)) ?></span><span><?= e(thai_date($keys[intdiv(count($keys) - 1, 2)], false, false)) ?></span><span><?= e(thai_date(end($keys), false, false)) ?></span></div>
    </div>
  </div>
  <?php endif; ?>
</section>

<div class="two">
  <section class="box" aria-labelledby="h-cat">
    <div class="box-head"><h2 id="h-cat">ตามหมวดชุด</h2></div>
    <?php if (!$d['by_category']): ?><p class="small muted">ไม่มีข้อมูล</p><?php endif; ?>
    <?php foreach ($d['by_category'] as $c): ?>
    <div class="barrow">
      <span><span class="swatch" style="background:<?= e($c['color']) ?>;width:10px;height:10px;margin-right:6px"></span><?= e($c['name']) ?></span>
      <span class="track"><span class="fill" style="display:block;width:<?= round(100 * $c['count'] / $catMax) ?>%"></span></span>
      <span><?= (int) $c['count'] ?> · <?= e(number_format($c['revenue'])) ?></span>
    </div>
    <?php endforeach; ?>
    <p class="small muted" style="margin-top:10px">ช่องทาง: ออนไลน์ <?= (int) $d['by_channel']['online'] ?> · หน้าร้าน <?= (int) $d['by_channel']['counter'] ?></p>
  </section>
  <section class="box" aria-labelledby="h-top">
    <div class="box-head"><h2 id="h-top">ชุดที่ถูกเช่าบ่อย</h2></div>
    <?php if (!$d['by_costume']): ?><p class="small muted">ไม่มีข้อมูล</p><?php endif; ?>
    <ol class="rows">
      <?php foreach ($d['by_costume'] as $i => $c): ?>
      <li style="grid-template-columns:22px 1fr auto"><b class="num muted"><?= $i + 1 ?></b><span><b><?= e($c['code']) ?></b> <span class="sub"><?= e(str_limit($c['name'], 30)) ?></span></span><span class="num small"><?= (int) $c['count'] ?> ครั้ง · <?= e(number_format($c['revenue'])) ?></span></li>
      <?php endforeach; ?>
    </ol>
  </section>
</div>

<section class="box" style="margin-bottom:16px" aria-labelledby="h-status">
  <div class="box-head"><h2 id="h-status">ทุกการจองในช่วงนี้ตามสถานะ</h2></div>
  <p class="row" style="gap:8px 18px">
    <?php foreach ($d['by_status'] as $k => $n): ?><span class="small"><?= Status::badge(Status::BOOKING, $k) ?> <b class="num"><?= (int) $n ?></b></span><?php endforeach; ?>
  </p>
</section>

<div class="tablewrap">
  <table class="table">
    <caption class="sr-only">รายการจองที่มีวันรับชุดในช่วงนี้</caption>
    <thead><tr><th>เลขที่</th><th>รับชุด</th><th>ชุด</th><th>ลูกค้า</th><th>ช่องทาง</th><th>สถานะ</th><th class="r">วัน</th><th class="r">ค่าเช่า</th><th class="r">ค่าปรับ</th></tr></thead>
    <tbody>
      <?php foreach ($d['rows'] as $r): ?>
      <tr>
        <td class="num"><?php if ($print): ?><?= e($r['booking_no']) ?><?php else: ?><a href="<?= e(url('/admin/bookings/' . $r['id'])) ?>"><?= e($r['booking_no']) ?></a><?php endif; ?></td>
        <td class="num nowrap"><?= e(thai_date($r['pickup_date'], false, false)) ?></td>
        <td><?= e($r['costume_code']) ?></td>
        <td><?= e(full_name($r)) ?></td>
        <td><?= $r['channel'] === 'online' ? 'ออนไลน์' : 'หน้าร้าน' ?></td>
        <td><?= e(Status::label(Status::BOOKING, $r['status'])) ?></td>
        <td class="r num"><?= (int) $r['rental_days'] ?></td>
        <td class="r num"><?= e(number_format((float) $r['rent_amount'] + (float) $r['extra_amount'])) ?></td>
        <td class="r num"><?= (float) $r['late_fee'] + (float) $r['damage_fee'] > 0 ? e(number_format((float) $r['late_fee'] + (float) $r['damage_fee'])) : '–' ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$d['rows']): ?><tr><td colspan="9" class="muted">ไม่มีการจองในช่วงนี้</td></tr><?php endif; ?>
    </tbody>
    <?php if ($d['rows']): ?><tfoot><tr><td colspan="6">รวม <?= count($d['rows']) ?> รายการ (นับเงินเฉพาะที่ยืนยันแล้ว)</td><td class="r num"><?= (int) $d['days'] ?></td><td class="r num"><?= e(number_format($d['revenue'])) ?></td><td></td></tr></tfoot><?php endif; ?>
  </table>
</div>
</div>
