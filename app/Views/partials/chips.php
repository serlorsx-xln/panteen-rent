<?php
/** The filters in force, each one a link that lifts it. @var array $filters @var string $base */
use App\Services\Catalog;

$chips = [];
if ($filters['q'] !== '') {
    $chips[] = ['q', '“' . $filters['q'] . '”'];
}
if ($filters['size'] !== '') {
    $chips[] = ['size', $filters['size'] === 'FREE' ? 'ฟรีไซส์' : 'ไซส์ ' . $filters['size']];
}
if ($filters['color'] !== '') {
    $chips[] = ['color', 'สี' . $filters['color']];
}
if ($filters['max'] > 0) {
    $chips[] = ['max', 'ไม่เกิน ' . money($filters['max'])];
}
if ($filters['new']) {
    $chips[] = ['new', 'มาใหม่'];
}
foreach ($chips as [$key, $label]): ?>
<a class="chip" href="<?= e(Catalog::listUrl($base, $filters, [$key => null])) ?>" data-chip aria-label="เอาตัวกรอง <?= e($label) ?> ออก"><?= e($label) ?><?= icon('x', 'ic ic-sm x') ?></a>
<?php endforeach;
if (count($chips) > 1): ?>
<a class="chip" href="<?= e(Catalog::listUrl($base, ['date' => $filters['date'], 'free' => $filters['free'], 'sort' => $filters['sort']])) ?>" data-chip>ล้างตัวกรองทั้งหมด</a>
<?php endif;
