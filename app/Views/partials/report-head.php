<?php /** @var string $type @var string $from @var string $to @var array $types @var bool $print */
$q = ['from' => $from, 'to' => $to];
$m0 = date('Y-m-01'); $m1 = date('Y-m-t');
$p0 = date('Y-m-01', strtotime('first day of last month')); $p1 = date('Y-m-t', strtotime('last day of last month'));
$y0 = date('Y-01-01'); $y1 = date('Y-12-31');
?>
<?php if (!$print): ?>
<div class="page-head">
  <div><h1>รายงาน</h1><p class="muted"><?= e(thai_date($from)) ?> – <?= e(thai_date($to)) ?></p></div>
  <div class="row">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/reports/' . $type, $q + ['format' => 'print'])) ?>" target="_blank"><?= icon('printer', 'ic ic-sm') ?>พิมพ์ / PDF</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/reports/' . $type, $q + ['format' => 'csv'])) ?>"><?= icon('download', 'ic ic-sm') ?>Excel (CSV)</a>
  </div>
</div>
<nav class="tabs" aria-label="ประเภทรายงาน">
  <?php foreach ($types as $k => $l): ?><a href="<?= e(url('/admin/reports/' . $k, $q)) ?>" <?= $k === $type ? 'aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?>
</nav>
<form class="filterbar" method="get" action="<?= e(url('/admin/reports/' . $type)) ?>">
  <div class="field"><label class="label" for="r-from">ตั้งแต่</label><input class="input" id="r-from" type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="field"><label class="label" for="r-to">ถึง</label><input class="input" id="r-to" type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="btn btn-ghost" type="submit">ดูรายงาน</button>
  <span class="row" style="gap:4px;padding-bottom:2px">
    <?php foreach ([['เดือนนี้', $m0, $m1], ['เดือนที่แล้ว', $p0, $p1], ['ปีนี้', $y0, $y1]] as [$l, $a, $b]): ?>
    <a class="chip<?= $from === $a && $to === $b ? ' is-on' : '' ?>" href="<?= e(url('/admin/reports/' . $type, ['from' => $a, 'to' => $b])) ?>"><?= e($l) ?></a>
    <?php endforeach; ?>
  </span>
</form>
<?php else: ?>
<div class="report-head">
  <div><h1><?= e($types[$type]) ?></h1><p class="muted"><?= e(setting('shop_name')) ?> · <?= e(thai_date($from)) ?> – <?= e(thai_date($to)) ?></p></div>
  <p class="small muted">พิมพ์เมื่อ <?= e(thai_datetime(date('Y-m-d H:i:s'))) ?></p>
</div>
<?php endif;
