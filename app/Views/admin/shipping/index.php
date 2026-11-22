<?php
/** @var string $tab @var array $tabs @var array $counts @var array $items */
use App\Services\Shipping;
use App\Services\Status;

$today = today();
$empty = [
    'out' => 'ไม่มีพัสดุที่ต้องส่ง', 'road' => 'ไม่มีพัสดุระหว่างทางไปหาลูกค้า', 'due' => 'ไม่มีชุดที่รอลูกค้าส่งคืน',
    'back' => 'ไม่มีพัสดุคืนระหว่างทาง', 'check' => 'ไม่มีชุดรอตรวจ', 'dispute' => 'ไม่มีข้อโต้แย้งค้าง',
];
?>
<div class="page-head">
  <div><h1>จัดส่งและรับคืน</h1><p class="muted">ทุกการเช่าที่เดินทางด้วยพัสดุหรือเมสเซนเจอร์ ตามลำดับที่ต้องทำ</p></div>
  <a class="btn btn-ghost" href="<?= e(url('/admin/scan')) ?>"><?= icon('scan-line') ?>สแกนชุด</a>
</div>
<nav class="tabs" aria-label="ขั้นตอนจัดส่ง">
  <?php foreach ($tabs as $k => [, $label]): ?>
  <a href="<?= e(url('/admin/shipping', ['tab' => $k])) ?>" <?= $tab === $k ? 'aria-current="page"' : '' ?>><?= e($label) ?><span class="n num"><?= (int) $counts[$k] ?></span></a>
  <?php endforeach; ?>
</nav>
<?php if (!$items): ?>
  <div class="empty"><?= icon('package', 'ic ic-lg') ?><h2><?= e($empty[$tab]) ?></h2></div>
<?php else: ?>
<div class="tablewrap">
  <table class="table">
    <thead><tr>
      <th scope="col">เลขที่</th><th scope="col">ชุด</th><th scope="col">ลูกค้า / ปลายทาง</th>
      <th scope="col"><?= ['out' => 'ต้องส่งออก', 'road' => 'ควรถึง', 'due' => 'ต้องฝากส่งภายใน', 'back' => 'ฝากส่งเมื่อ', 'check' => 'ได้รับเมื่อ', 'dispute' => 'แจ้งเมื่อ'][$tab] ?></th>
      <th scope="col">พัสดุ</th><th scope="col"><span class="sr-only">ทำต่อ</span></th>
    </tr></thead>
    <tbody>
      <?php foreach ($items as $b):
          $path = '/admin/bookings/' . $b['id'];
          [$when, $late] = match ($tab) {
              'out' => [$b['ship_by_date'], $b['ship_by_date'] < $today],
              'road' => [$b['pickup_date'], $b['pickup_date'] < $today],
              'due' => [$b['return_date'], $b['return_date'] < $today],
              'back' => [$b['shipped_on'], (string) $b['shipped_on'] < add_days($today, -Shipping::transitDays('post', $b['ship_zone']) - 2)],
              'check' => [substr((string) $b['arrived_at'], 0, 10), false],
              default => [substr((string) $b['dispute_at'], 0, 10), false],
          };
          [$act, $href] = match ($tab) {
              'out' => ['ส่งพัสดุ', $path],
              'road' => ['ยืนยันถึงแล้ว', $path],
              'due' => ['ดูการจอง', $path],
              'back' => ['รับพัสดุคืน', $path],
              'check' => ['ตรวจสภาพ', $path . '/inspect'],
              default => ['ตอบ', $path . '#dispute'],
          }; ?>
      <tr>
        <td class="num"><a href="<?= e(url($path)) ?>"><b><?= e($b['booking_no']) ?></b></a><br><small class="faint"><?= e(Shipping::DELIVERY[$b['delivery_method']]) ?></small></td>
        <td><div class="cell-main"><img class="thumb" src="<?= e(mini($b['image'])) ?>" alt=""><span><b><?= e($b['costume_code']) ?></b><small><?= e(str_limit($b['costume_name'], 24)) ?></small></span></div></td>
        <td><?= e(full_name($b)) ?><br><small class="faint"><?= e(($b['ship_province'] ?? '') . ($b['ship_zone'] ? ' · ' . Shipping::ZONE[$b['ship_zone']] : '')) ?></small></td>
        <td class="num nowrap"><?= e(thai_date((string) $when, false, false)) ?><?= $late ? '<br><span class="late small">' . ($tab === 'back' ? 'นานผิดปกติ ติดตามกับขนส่ง' : 'เลยกำหนด') . '</span>' : ($when === $today ? '<br><small class="faint">วันนี้</small>' : '') ?></td>
        <td class="small"><?php if ($b['carrier']): ?><?= e(Shipping::carrierName($b['carrier'])) ?><?= $b['tracking_no'] ? '<br><span class="num">' . e($b['tracking_no']) . '</span>' : '' ?><?php if ($b['parcel_status'] && in_array($b['parcel_status'], ['failed', 'lost'], true)): ?><br><?= Status::badge(Status::SHIPMENT, $b['parcel_status']) ?><?php endif; ?><?php else: ?><span class="faint">–</span><?php endif; ?></td>
        <td class="r"><a class="btn btn-sm<?= $tab === 'due' ? ' btn-ghost' : '' ?>" href="<?= e(url($href)) ?>"><?= e($act) ?></a><?php if ($tab === 'out'): ?> <a class="btn btn-sm btn-ghost" href="<?= e(url($path . '/label')) ?>" target="_blank" aria-label="พิมพ์ใบปะหน้า <?= e($b['booking_no']) ?>"><?= icon('printer', 'ic ic-sm') ?></a><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
