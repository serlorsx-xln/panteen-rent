<?php
/** @var array $items @var array $pg @var string $status @var string $q @var string $from @var string $to @var array $counts */
use App\Services\Status;
$tabs = ['' => 'ทั้งหมด', 'active' => 'กำลังดำเนินการ', 'awaiting_verification' => 'รอตรวจสลิป', 'pending_payment' => 'รอชำระ', 'confirmed' => 'รอรับ/รอส่ง', 'shipped' => 'ระหว่างส่ง', 'picked_up' => 'กำลังเช่า', 'returning' => 'ส่งคืนแล้ว', 'overdue' => 'เลยกำหนดคืน', 'returned' => 'คืนแล้ว', 'cancelled' => 'ยกเลิก', 'expired' => 'หมดเวลา'];
$n = fn($k) => match ($k) {
    '' => array_sum(array_diff_key($counts, ['overdue' => 1])),
    'active' => array_sum(array_intersect_key($counts, array_flip(['pending_payment', 'awaiting_verification', 'confirmed', 'shipped', 'picked_up', 'returning']))),
    default => $counts[$k] ?? 0,
};
$qs = fn(array $o = []) => url('/admin/bookings', array_filter(array_merge(['status' => $status, 'q' => $q, 'from' => $from, 'to' => $to], $o), fn($v) => $v !== '' && $v !== null));
?>
<div class="page-head">
  <div><h1>การจอง</h1><p class="muted">พบ <?= (int) $pg['total'] ?> รายการ</p></div>
  <a class="btn" href="<?= e(url('/admin/bookings/new')) ?>"><?= icon('calendar-plus') ?>จองหน้าร้าน</a>
</div>
<nav class="tabs" aria-label="สถานะการจอง">
  <?php foreach ($tabs as $k => $l): ?>
  <a href="<?= e($qs(['status' => $k, 'page' => null])) ?>" <?= $status === $k ? 'aria-current="page"' : '' ?>><?= e($l) ?><span class="n num"><?= (int) $n($k) ?></span></a>
  <?php endforeach; ?>
</nav>
<form class="filterbar" method="get" action="<?= e(url('/admin/bookings')) ?>">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <div class="field"><label class="label" for="bq">ค้นหา</label><input class="input" id="bq" name="q" type="search" value="<?= e($q) ?>" placeholder="เลขจอง ชื่อ เบอร์ รหัสชุด" style="width:260px"></div>
  <div class="field"><label class="label" for="bf">วันรับชุดตั้งแต่</label><input class="input" id="bf" type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="field"><label class="label" for="bt">ถึง</label><input class="input" id="bt" type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="btn btn-ghost" type="submit"><?= icon('filter', 'ic ic-sm') ?>กรอง</button>
  <?php if ($q || $from || $to): ?><a class="small" href="<?= e(url('/admin/bookings', array_filter(['status' => $status]))) ?>" style="padding:10px 4px">ล้าง</a><?php endif; ?>
</form>
<?php if (!$items): ?>
  <div class="empty"><?= icon('search', 'ic ic-lg') ?><h2>ไม่พบการจอง</h2><p>ลองเปลี่ยนสถานะหรือคำค้นหา</p></div>
<?php else: ?>
<div class="tablewrap">
  <table class="table">
    <thead><tr><th>เลขที่</th><th>ลูกค้า</th><th>ชุด</th><th>วันงาน</th><th>รับ – คืน</th><th class="r">ยอด</th><th>สถานะ</th></tr></thead>
    <tbody>
      <?php foreach ($items as $b): $overdue = $b['status'] === 'picked_up' && $b['return_date'] < today(); ?>
      <tr>
        <td class="num"><a href="<?= e(url('/admin/bookings/' . $b['id'])) ?>"><b><?= e($b['booking_no']) ?></b></a><br><small class="faint"><?= $b['channel'] === 'counter' ? 'หน้าร้าน' : 'ออนไลน์' ?></small></td>
        <td><?= e(full_name($b)) ?><?= $b['nickname'] ? ' <span class="faint">(' . e($b['nickname']) . ')</span>' : '' ?><br><small class="faint num"><?= e(phone_fmt($b['phone'])) ?></small></td>
        <td><div class="cell-main"><img class="thumb" src="<?= e(mini($b['image'])) ?>" alt=""><span><b><?= e($b['costume_code']) ?></b><small><?= e(str_limit($b['costume_name'], 26)) ?></small></span></div></td>
        <td class="num nowrap"><?= e(thai_date($b['event_date'], false, false)) ?></td>
        <td class="num nowrap"><?= e(thai_date($b['pickup_date'], false, false)) ?> – <?= e(thai_date($b['return_date'], false, false)) ?><?= $overdue ? '<br><span class="late small">เลยกำหนด ' . days_between($b['return_date'], today()) . ' วัน</span>' : '' ?></td>
        <td class="r num"><?= e(number_format((float) $b['total_amount'])) ?></td>
        <td><?= Status::badge(Status::BOOKING, $b['status']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php \App\Core\View::partial('pager', ['pg' => $pg, 'make' => fn($p) => $qs(['page' => $p])]); ?>
<?php endif; ?>
