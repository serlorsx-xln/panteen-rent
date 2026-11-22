<?php
/** @var array $pickups @var array $parcels @var array $returns @var int $slips @var array $holds @var array $fittings @var array $week @var array $inventory @var array $month */
$who = fn(array $r) => ($r['nickname'] ? $r['nickname'] . ' · ' : '') . $r['first_name'] . ' ' . mb_substr((string) $r['last_name'], 0, 1) . '.';
$weekBy = array_column($week, null, 'd');
?>
<div class="page-head">
  <div>
    <h1><?= e(thai_date_full(today())) ?></h1>
    <p class="muted">สิ่งที่ต้องทำวันนี้ที่หน้าร้าน</p>
  </div>
</div>

<?php if ($slips): ?>
<a class="alertline" href="<?= e(url('/admin/payments')) ?>">
  <?= icon('wallet') ?><b><?= $slips ?></b><span>สลิปรอตรวจ ลูกค้ารอการยืนยันอยู่</span><span>ตรวจสลิป <?= icon('arrow-right', 'ic ic-sm') ?></span>
</a>
<?php endif; ?>

<div class="facts">
  <span><b><?= (int) $inventory['active'] ?></b>ชุดเปิดให้เช่า</span>
  <span><b><?= (int) $inventory['on_rent'] ?></b>อยู่กับลูกค้า</span>
  <span><b><?= (int) $inventory['maintenance'] ?></b>ซ่อม/ซักพิเศษ</span>
  <span><b><?= (int) $month['bookings'] ?></b>การจองยืนยันเดือนนี้</span>
  <span><b><?= e(number_format((float) $month['revenue'])) ?></b>ค่าเช่าเดือนนี้ (บาท)</span>
</div>

<div class="today">
  <section class="box" aria-labelledby="h-pick">
    <div class="box-head"><span class="count"><?= count($pickups) ?></span><h2 id="h-pick">ลูกค้ามารับชุด</h2><a href="<?= e(url('/admin/bookings', ['status' => 'confirmed'])) ?>">ทั้งหมด</a></div>
    <?php if (!$pickups): ?><p class="muted small">วันนี้ไม่มีนัดรับชุด</p><?php endif; ?>
    <ul class="rows">
      <?php foreach ($pickups as $b): $late = $b['pickup_date'] < today(); ?>
      <li>
        <img src="<?= e(mini($b['image'])) ?>" alt="">
        <a href="<?= e(url('/admin/bookings/' . $b['id'])) ?>"><b><?= e($who($b)) ?></b><div class="sub"><?= e($b['costume_code']) ?> · <?= $late ? '<span class="late">ควรรับตั้งแต่ ' . e(thai_date($b['pickup_date'], false, false)) . '</span>' : e(substr((string) $b['pickup_time'], 0, 5)) . ' น.' ?> · <?= e(phone_fmt($b['phone'])) ?></div></a>
        <form method="post" action="<?= e(url('/admin/bookings/' . $b['id'] . '/pickup')) ?>" data-confirm="ยืนยันว่าลูกค้า <?= e($who($b)) ?> รับชุด <?= e($b['costume_code']) ?> แล้ว?"><?= csrf_field() ?><button class="btn btn-xs" type="submit">รับชุดแล้ว</button></form>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="box" aria-labelledby="h-ret">
    <div class="box-head"><span class="count"><?= count($returns) ?></span><h2 id="h-ret">ต้องคืนชุด</h2><a href="<?= e(url('/admin/bookings', ['status' => 'overdue'])) ?>">เลยกำหนด</a></div>
    <?php if (!$returns): ?><p class="muted small">วันนี้ไม่มีชุดที่ครบกำหนดคืน</p><?php endif; ?>
    <ul class="rows">
      <?php foreach ($returns as $b): $late = days_between($b['return_date'], today()); ?>
      <li>
        <img src="<?= e(mini($b['image'])) ?>" alt="">
        <a href="<?= e(url('/admin/bookings/' . $b['id'])) ?>"><b><?= e($who($b)) ?></b><div class="sub"><?= e($b['costume_code']) ?> · <?= $late > 0 ? '<span class="late">เลยกำหนด ' . $late . ' วัน · ปรับ ' . e(money($late * (float) $b['late_fee_per_day'])) . '</span>' : 'ภายใน ' . e(substr((string) $b['return_time'], 0, 5)) . ' น.' ?> · <?= e(phone_fmt($b['phone'])) ?></div></a>
        <a class="btn btn-xs btn-ghost" href="<?= e(url('/admin/bookings/' . $b['id'] . ($b['return_method'] === 'post' ? '' : '/inspect'))) ?>"><?= $b['return_method'] === 'post' ? 'รอพัสดุคืน' : 'รับคืน' ?></a>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <?php if ($parcels): ?>
  <section class="box" aria-labelledby="h-parcel">
    <div class="box-head"><span class="count"><?= count($parcels) ?></span><h2 id="h-parcel">พัสดุวันนี้</h2><a href="<?= e(url('/admin/shipping')) ?>">จัดส่งและรับคืน</a></div>
    <ul class="rows">
      <?php foreach ($parcels as $b): $back = $b['status'] === 'returning'; $late = !$back && $b['ship_by_date'] < today(); ?>
      <li>
        <img src="<?= e(mini($b['image'])) ?>" alt="">
        <a href="<?= e(url('/admin/bookings/' . $b['id'])) ?>"><b><?= e($who($b)) ?></b><div class="sub"><?= e($b['costume_code']) ?> · <?= $back ? 'พัสดุคืนถึงร้านแล้ว' : ($late ? '<span class="late">ควรส่งตั้งแต่ ' . e(thai_date($b['ship_by_date'], false, false)) . '</span>' : 'ส่งออกวันนี้ · ' . e($b['ship_province'] ?? '')) ?></div></a>
        <a class="btn btn-xs<?= $back ? '' : ' btn-ghost' ?>" href="<?= e(url('/admin/bookings/' . $b['id'] . ($back ? '/inspect' : ''))) ?>"><?= $back ? 'ตรวจสภาพ' : 'ส่งพัสดุ' ?></a>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <section class="box" aria-labelledby="h-fit">
    <div class="box-head"><span class="count"><?= count($fittings) ?></span><h2 id="h-fit">นัดลองชุดวันนี้</h2><a href="<?= e(url('/admin/appointments')) ?>">ตารางนัด</a></div>
    <?php if (!$fittings): ?><p class="muted small">วันนี้ไม่มีนัดลองชุด</p><?php endif; ?>
    <ul class="rows">
      <?php foreach ($fittings as $a): ?>
      <li style="grid-template-columns:52px 1fr auto">
        <b class="num"><?= e(substr($a['starts_at'], 11, 5)) ?></b>
        <span><b><?= e($who($a)) ?></b><div class="sub"><?= e($a['note'] ?: 'ลองชุดที่ร้าน') ?> · <?= e(phone_fmt($a['phone'])) ?></div></span>
        <?= \App\Services\Status::badge(\App\Services\Status::APPOINTMENT, $a['status']) ?>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="box" aria-labelledby="h-hold">
    <div class="box-head"><span class="count"><?= count($holds) ?></span><h2 id="h-hold">จองไว้ รอโอนเงิน</h2><a href="<?= e(url('/admin/bookings', ['status' => 'pending_payment'])) ?>">ทั้งหมด</a></div>
    <?php if (!$holds): ?><p class="muted small">ไม่มีการจองที่รอชำระ</p><?php endif; ?>
    <ul class="rows">
      <?php foreach ($holds as $b): $left = max(0, strtotime($b['hold_expires_at']) - time()); ?>
      <li>
        <img src="<?= e(mini($b['image'])) ?>" alt="">
        <a href="<?= e(url('/admin/bookings/' . $b['id'])) ?>"><b><?= e($who($b)) ?></b><div class="sub"><?= e($b['costume_code']) ?> · <?= e(money($b['total_amount'])) ?></div></a>
        <span class="small <?= $left < 3 * 3600 ? 'late' : 'muted' ?> num">เหลือ <?= $left >= 3600 ? floor($left / 3600) . ' ชม.' : floor($left / 60) . ' นาที' ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="box box-wide" aria-labelledby="h-week">
    <div class="box-head"><h2 id="h-week">7 วันข้างหน้า</h2><a href="<?= e(url('/admin/schedule')) ?>">ตารางชุด</a></div>
    <div class="week">
      <?php for ($i = 1; $i <= 7; $i++): $d = add_days(today(), $i); $w = $weekBy[$d] ?? null; ?>
      <div class="week-day<?= $w ? ' is-busy' : '' ?>">
        <b><?= e(THAI_DAYS_SHORT[(int) date('w', strtotime($d))] . ' ' . (int) substr($d, 8)) ?></b>
        รับ <?= (int) ($w['pickups'] ?? 0) ?> · คืน <?= (int) ($w['returns'] ?? 0) ?>
      </div>
      <?php endfor; ?>
    </div>
  </section>
</div>
