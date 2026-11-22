<?php
/** What the floor says when nothing matches. @var array $filters @var array $result @var string $base */
use App\Services\Catalog;

if ((int) $result['total'] > 0) {
    return;
}
$onlyFree = $filters['date'] && $filters['free'] && (int) $result['match_total'] > 0;
?>
<div class="empty">
  <?= icon($onlyFree ? 'calendar-x' : 'search', 'ic ic-lg') ?>
  <?php if ($onlyFree): ?>
    <h2>ไม่มีชุดที่ว่างวันที่ <?= e(thai_date($filters['date'], true, false)) ?></h2>
    <p>ชุดที่ตรงกับตัวกรองทั้ง <?= (int) $result['match_total'] ?> ชุดถูกจองวันนั้นแล้ว ลองเลื่อนวันงาน หรือดูชุดทั้งหมดเพื่อเทียบวันที่ว่าง</p>
    <a class="btn btn-ghost" href="<?= e(Catalog::listUrl($base, $filters, ['free' => null])) ?>" data-chip>ดูทั้ง <?= (int) $result['match_total'] ?> ชุด</a>
  <?php else: ?>
    <h2>ไม่พบชุดที่ตรงกับตัวกรอง</h2>
    <p>ลองเอาตัวกรองออกทีละอย่าง หรือค้นด้วยคำที่สั้นลง</p>
    <a class="btn btn-ghost" href="<?= e(Catalog::listUrl($base, ['date' => $filters['date'], 'free' => $filters['free']])) ?>" data-chip>ล้างตัวกรอง</a>
  <?php endif; ?>
</div>
