<?php
/** @var array $d @var string $from @var string $to @var bool $print */
use App\Services\Status;
\App\Core\View::partial('report-head', get_defined_vars());
$mMax = max(1, ...array_values($d['methods']));
?>
<div<?= $print ? ' class="report-sheet"' : '' ?>>
<dl class="stats">
  <div><dt>เงินเข้า (บาท)</dt><dd><?= e(number_format($d['received'])) ?></dd></div>
  <div><dt>คืนเงินออก (บาท)</dt><dd><?= e(number_format($d['refunded'])) ?></dd></div>
  <div><dt>คงเหลือสุทธิ (บาท)</dt><dd><?= e(number_format($d['net'])) ?></dd></div>
  <div><dt>รายได้ของร้าน (บาท)</dt><dd><?= e(number_format($d['income'])) ?></dd></div>
  <div><dt>มัดจำที่ถืออยู่ตอนนี้</dt><dd><?= e(number_format($d['deposit_held_now'])) ?></dd></div>
</dl>
<div class="two">
  <section class="box" aria-labelledby="h-break">
    <div class="box-head"><h2 id="h-break">ที่มาของรายได้</h2></div>
    <table class="table">
      <tbody>
        <tr><td>ค่าเช่า + วันเพิ่ม (จากการจองที่รับเงินในช่วงนี้)</td><td class="r num"><?= e(money($d['rent'])) ?></td></tr>
        <?php if ($d['shipping'] > 0): ?><tr><td>ค่าจัดส่งและค่าส่งคืนที่เก็บจากลูกค้า</td><td class="r num"><?= e(money($d['shipping'])) ?></td></tr><?php endif; ?>
        <tr><td>ค่าปรับคืนช้า (คืนชุด <?= (int) $d['settled_count'] ?> ครั้ง)</td><td class="r num"><?= e(money($d['fines_late'])) ?></td></tr>
        <tr><td>ค่าเสียหาย (หลังหักยอดที่ลดให้จากการโต้แย้ง)</td><td class="r num"><?= e(money($d['fines_damage'])) ?></td></tr>
      </tbody>
      <tfoot><tr><td>รายได้ของร้าน</td><td class="r num"><?= e(money($d['income'])) ?></td></tr></tfoot>
    </table>
    <p class="small muted" style="margin-top:10px">เงินมัดจำรับเข้า <?= e(money($d['deposit_in'])) ?> ไม่นับเป็นรายได้ เพราะคืนให้ลูกค้าเมื่อคืนชุด · คืนเงินเพราะยกเลิก <?= e(money($d['cancel_refunds'])) ?></p>
  </section>
  <section class="box" aria-labelledby="h-method">
    <div class="box-head"><h2 id="h-method">เงินเข้าตามช่องทาง</h2></div>
    <?php foreach ($d['methods'] as $k => $amt): ?>
    <div class="barrow"><span><?= e(Status::PAYMENT_METHOD[$k]) ?></span><span class="track"><span class="fill" style="display:block;width:<?= round(100 * $amt / $mMax) ?>%"></span></span><span class="num"><?= e(number_format($amt)) ?></span></div>
    <?php endforeach; ?>
  </section>
</div>
<div class="tablewrap">
  <table class="table">
    <caption class="sr-only">รายการรับและจ่ายเงิน</caption>
    <thead><tr><th>วันเวลา</th><th>การจอง</th><th>ลูกค้า</th><th>รายการ</th><th>ช่องทาง</th><th>ผู้ตรวจ</th><th class="r">รับเข้า</th><th class="r">จ่ายออก</th></tr></thead>
    <tbody>
      <?php foreach ($d['tx'] as $t): $out = $t['kind'] === 'refund'; ?>
      <tr>
        <td class="num nowrap"><?= e(thai_datetime($t['reviewed_at'])) ?></td>
        <td class="num"><?= $print ? e($t['booking_no']) : '<a href="' . e(url('/admin/bookings/' . $t['booking_id'])) . '">' . e($t['booking_no']) . '</a>' ?></td>
        <td><?= e(full_name($t)) ?></td>
        <td><?= e(Status::PAYMENT_KIND[$t['kind']]) ?></td>
        <td><?= e(Status::PAYMENT_METHOD[$t['method']]) ?></td>
        <td><?= e($t['reviewer_nick'] ?: ($t['reviewer_first'] ?? '–')) ?></td>
        <td class="r num"><?= $out ? '' : e(number_format((float) $t['amount'], 2)) ?></td>
        <td class="r num"><?= $out ? e(number_format((float) $t['amount'], 2)) : '' ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$d['tx']): ?><tr><td colspan="8" class="muted">ไม่มีรายการเงินในช่วงนี้</td></tr><?php endif; ?>
    </tbody>
    <?php if ($d['tx']): ?><tfoot><tr><td colspan="6">รวม</td><td class="r num"><?= e(number_format($d['received'], 2)) ?></td><td class="r num"><?= e(number_format($d['refunded'], 2)) ?></td></tr></tfoot><?php endif; ?>
  </table>
</div>
</div>
