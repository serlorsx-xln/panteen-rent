<?php
/** @var string $start @var int $span @var array $costumes @var array $byCostume @var array $categories @var int $cat */
$days = [];
for ($i = 0; $i < $span; $i++) { $days[] = add_days($start, $i); }
$end = end($days);
$idx = array_flip($days);
$who = fn($b) => $b['nickname'] ?: $b['first_name'];
$nav = fn($d) => url('/admin/schedule', array_filter(['start' => $d, 'category' => $cat ?: null]));
$label = \App\Services\Status::BOOKING;
?>
<div class="page-head">
  <div><h1>ตารางชุด</h1><p class="muted"><?= e(thai_date($start, false)) ?> – <?= e(thai_date($end)) ?></p></div>
  <div class="row">
    <a class="btn btn-ghost btn-sm" href="<?= e($nav(add_days($start, -14))) ?>" aria-label="ย้อนไป 2 สัปดาห์"><?= icon('chevron-left', 'ic ic-sm') ?></a>
    <a class="btn btn-ghost btn-sm" href="<?= e($nav(add_days(today(), -2))) ?>">วันนี้</a>
    <a class="btn btn-ghost btn-sm" href="<?= e($nav(add_days($start, 14))) ?>" aria-label="ถัดไป 2 สัปดาห์"><?= icon('chevron-right', 'ic ic-sm') ?></a>
  </div>
</div>
<form class="filterbar" method="get" action="<?= e(url('/admin/schedule')) ?>">
  <input type="hidden" name="start" value="<?= e($start) ?>">
  <div class="field"><label class="label" for="sc-cat">หมวด</label>
    <select class="select" id="sc-cat" name="category" data-autosubmit>
      <option value="">ทุกหมวด</option>
      <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field"><label class="label" for="sc-start">เริ่มวันที่</label><input class="input" id="sc-start" type="date" name="start" value="<?= e($start) ?>" data-autosubmit></div>
</form>
<div class="legend" aria-hidden="true">
  <span><i style="background:var(--wait-bg)"></i>รอชำระ/รอตรวจ</span>
  <span><i style="background:var(--ok-bg)"></i>ยืนยันแล้ว</span>
  <span><i style="background:var(--ink)"></i>อยู่กับลูกค้า</span>
  <span><i style="background:var(--bad)"></i>เลยกำหนดคืน</span>
  <span><i style="background:var(--sunk);box-shadow:inset 0 0 0 1px var(--line-2)"></i>คืนแล้ว</span>
  <span><i class="bar-picked_up legend-clean"></i>ส่วนจาง = วันซักชุดหลังคืน</span>
</div>
<div class="board">
  <table>
    <caption class="sr-only">ตารางการจองของแต่ละชุด <?= e(thai_date($start)) ?> ถึง <?= e(thai_date($end)) ?></caption>
    <thead><tr>
      <th class="rowhead" scope="col">ชุด (<?= count($costumes) ?>)</th>
      <?php foreach ($days as $d): $w = (int) date('w', strtotime($d)); ?>
      <th scope="col" class="<?= $d === today() ? 'is-today' : '' ?><?= $w === 0 || $w === 6 ? ' is-weekend' : '' ?>"><?= e(THAI_DAYS_SHORT[$w]) ?><br><?= (int) substr($d, 8) ?></th>
      <?php endforeach; ?>
    </tr></thead>
    <tbody>
      <?php foreach ($costumes as $c): $list = $byCostume[$c['id']] ?? []; ?>
      <tr>
        <th class="rowhead" scope="row"><i style="--c:<?= e($c['category_color']) ?>"></i><a href="<?= e(url('/admin/costumes/' . $c['id'])) ?>"><?= e($c['code']) ?></a> <small><?= e(str_limit($c['name'], 18)) ?></small><?= $c['status'] === 'maintenance' ? ' <small>· ซ่อม</small>' : '' ?></th>
        <?php foreach ($days as $i => $d):
            $bar = '';
            foreach ($list as $b) {
                $from = max($b['pickup_date'], $start);
                if ($from !== $d) continue;
                $overdue = $b['status'] === 'picked_up' && $b['return_date'] < today();
                $to = min($overdue ? max($b['busy_until'], today()) : $b['busy_until'], $end);
                $retTo = min($overdue ? today() : $b['return_date'], $end);
                $cells = $idx[$to] - $idx[$from] + 1;
                $useCells = $b['return_date'] < $start ? 0 : $idx[$retTo] - $idx[$from] + 1;
                $cleanPct = $cells ? round(100 * ($cells - $useCells) / $cells, 2) : 0;
                $title = $b['booking_no'] . ' · ' . $who($b) . ' · ' . $label[$b['status']][0] . ' · รับ ' . thai_date($b['pickup_date'], false, false) . ' คืน ' . thai_date($b['return_date'], false, false) . ($overdue ? ' · เลยกำหนดคืน' : '');
                $bar .= '<a class="bar bar-' . e($b['status']) . ($overdue ? ' bar-overdue' : '') . '" style="width:calc(' . ($cells * 100) . '% + ' . ($cells - 1) . 'px - 2px)" href="' . e(url('/admin/bookings/' . $b['id'])) . '" title="' . e($title) . '">' . e($who($b)) . ($cleanPct > 0 ? '<span class="clean" style="width:' . $cleanPct . '%"></span>' : '') . '</a>';
            }
        ?>
        <td class="<?= $d === today() ? 'is-today' : '' ?>"><?= $bar ?></td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
