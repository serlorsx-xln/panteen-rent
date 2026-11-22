<?php
/**
 * The one count on a listing, beside the sort. Without a date: how many dresses. With a date: how many are free
 * that day out of how many match. data-total always holds the number of cards the grid is showing.
 * @var array $filters @var array $result
 */
$shown = (int) $result['total'];
if ($filters['date']):
    $free = (int) $result['free_total'];
    $all = (int) $result['match_total'];
    if ($filters['free']): ?>
ว่าง <b data-total><?= $shown ?></b>จาก <?= $all ?> ชุด
<?php else: ?>
ว่าง <b><?= $free ?></b>จาก <span data-total><?= $shown ?></span> ชุด
<?php endif;
else: ?>
<b data-total><?= $shown ?></b>ชุด
<?php endif;
