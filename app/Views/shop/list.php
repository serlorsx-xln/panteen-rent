<?php
/**
 * Every dress, or one category of them: the event date across the top, the filters down the side.
 * @var ?array $category @var ?array $floor @var array $floors @var string $base
 * @var array $filters @var array $result @var ?array $window @var array $colors
 */
use App\Core\View;
use App\Services\Catalog;

$f = $filters;
$data = ['filters' => $f, 'result' => $result, 'window' => $window, 'base' => $base];
$allCount = array_sum(array_column($floors, 'costume_count'));
$prices = [0 => 'ไม่จำกัด', 600 => 'ไม่เกิน 600 บาท', 900 => 'ไม่เกิน 900 บาท', 1500 => 'ไม่เกิน 1,500 บาท', 2000 => 'ไม่เกิน 2,000 บาท'];
if ($f['max'] > 0 && !isset($prices[$f['max']])) {
    $prices[$f['max']] = 'ไม่เกิน ' . money($f['max']);
}
// an empty date in the address tells the shop to forget the one it was holding
$clearDate = Catalog::listUrl($base, $f, ['date' => '', 'free' => null]);
$more = $f['page'] < $result['pages'];
$left = max(0, (int) $result['total'] - $f['page'] * Catalog::PER_PAGE);
?>
<div class="wrap">
  <nav class="crumbs" aria-label="ตำแหน่งในเว็บไซต์">
    <a href="<?= e(url('/')) ?>">หน้าแรก</a><?= icon('chevron-right', 'ic ic-sm') ?>
    <?php if ($category): ?>
      <a href="<?= e(url('/dresses')) ?>">ชุดทั้งหมด</a><?= icon('chevron-right', 'ic ic-sm') ?>
      <span aria-current="page"><?= e($category['name']) ?></span>
    <?php else: ?>
      <span aria-current="page">ชุดทั้งหมด</span>
    <?php endif; ?>
  </nav>

  <header class="list-head">
    <h1><?= e($category ? $category['name'] : 'ชุดทั้งหมด') ?></h1>
    <?php if ($category && !empty($category['description'])): ?>
      <p><?= e($category['description']) ?></p>
    <?php endif; ?>
    <?php if ($floor && $floor['min_price'] !== null): ?>
    <span class="list-meta">ค่าเช่าเริ่ม <?= e(money($floor['min_price'])) ?> / <?= (int) setting('base_days') ?> วัน</span>
    <?php endif; ?>
  </header>

  <section class="deskbar" data-desk aria-label="วันงานของคุณ">
    <label class="desk-cell desk-date">
      <span class="desk-label">วันงาน</span>
      <span class="desk-value<?= $f['date'] ? '' : ' is-empty' ?>" data-desk-text aria-hidden="true"><?= $f['date'] ? e(thai_date_dow($f['date'])) : 'เลือกวันงาน' ?></span>
      <input class="desk-native" type="date" name="date" form="filters" value="<?= e($f['date'] ?? '') ?>" min="<?= e(today()) ?>" max="<?= e(add_days(today(), (int) setting('booking_horizon_days'))) ?>" data-desk-input>
      <?= icon('calendar') ?>
    </label>
    <p class="deskbar-count" data-desk-count><?php View::partial('desk-count', $data); ?></p>
    <div class="deskbar-tools">
      <label class="switch"><input type="checkbox" name="free" value="1" form="filters" data-desk-free<?= $f['free'] ? ' checked' : '' ?><?= $f['date'] ? '' : ' disabled' ?>> เฉพาะชุดที่ว่าง</label>
      <a class="desk-clear" href="<?= e($clearDate) ?>" data-desk-clear<?= $f['date'] ? '' : ' hidden' ?>>ล้างวันงาน</a>
      <button class="btn btn-sm nojs-only" type="submit" form="filters">ดูชุด</button>
    </div>
  </section>

  <div class="listing">
    <form class="filters" id="filters" method="get" action="<?= e(url($base)) ?>" data-filters aria-label="ตัวกรองชุด">
      <div class="filters-head">
        <h2>ตัวกรอง</h2>
        <button class="iconbtn" type="button" data-filters-close aria-label="ปิดตัวกรอง"><?= icon('x') ?></button>
      </div>

      <div class="fgroup">
        <label class="label" for="f-q">ค้นหา</label>
        <div class="search-field">
          <input class="input" id="f-q" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="ชื่อชุด รหัส หรือสี" autocomplete="off" enterkeyhint="search">
          <button type="submit" aria-label="ค้นหา"><?= icon('search') ?></button>
        </div>
      </div>

      <nav class="fgroup" aria-label="กรองตามหมวด">
        <span class="label">หมวด</span>
        <ul class="flist">
          <li><a href="<?= e(Catalog::listUrl('/dresses', $f)) ?>"<?= $category ? '' : ' aria-current="page"' ?>><span>ชุดทั้งหมด</span><small><?= (int) $allCount ?></small></a></li>
          <?php foreach ($floors as $fl): ?>
          <li><a href="<?= e(Catalog::listUrl('/c/' . $fl['slug'], $f)) ?>"<?= $category && $category['slug'] === $fl['slug'] ? ' aria-current="page"' : '' ?>><span><?= e($fl['name']) ?></span><small><?= (int) $fl['costume_count'] ?></small></a></li>
          <?php endforeach; ?>
        </ul>
      </nav>

      <fieldset class="fgroup">
        <legend>ไซส์</legend>
        <div class="opt-row">
          <label><input type="radio" name="size" value=""<?= $f['size'] === '' ? ' checked' : '' ?>><span>ทุกไซส์</span></label>
          <?php foreach (Catalog::SIZES as $s): ?>
          <label><input type="radio" name="size" value="<?= e($s) ?>"<?= $f['size'] === $s ? ' checked' : '' ?>><span><?= $s === 'FREE' ? 'ฟรีไซส์' : e($s) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <details class="fgroup"<?= $f['color'] !== '' ? ' open' : '' ?>>
        <summary>สี</summary>
        <fieldset>
          <legend class="sr-only">สี</legend>
          <ul class="flist">
            <li><label><input type="radio" name="color" value=""<?= $f['color'] === '' ? ' checked' : '' ?>><span class="box" aria-hidden="true"></span><span>ทุกสี</span></label></li>
            <?php foreach ($colors as $col): ?>
            <li><label><input type="radio" name="color" value="<?= e($col) ?>"<?= $f['color'] === $col ? ' checked' : '' ?>><span class="sw" aria-hidden="true" style="--c:<?= e(Catalog::SWATCHES[$col] ?? 'transparent') ?>"></span><span><?= e($col) ?></span></label></li>
            <?php endforeach; ?>
          </ul>
        </fieldset>
      </details>

      <details class="fgroup"<?= $f['max'] > 0 ? ' open' : '' ?>>
        <summary>ค่าเช่า</summary>
        <fieldset>
          <legend class="sr-only">ค่าเช่า</legend>
          <ul class="flist">
            <?php foreach ($prices as $v => $label): ?>
            <li><label><input type="radio" name="max" value="<?= $v ?: '' ?>"<?= (int) $f['max'] === (int) $v ? ' checked' : '' ?>><span class="box" aria-hidden="true"></span><span><?= e($label) ?></span></label></li>
            <?php endforeach; ?>
          </ul>
        </fieldset>
      </details>

      <div class="fgroup">
        <label class="check"><input type="checkbox" name="new" value="1"<?= $f['new'] ? ' checked' : '' ?>><span>เฉพาะชุดมาใหม่ใน 45 วัน</span></label>
      </div>

      <div class="filters-actions">
        <button class="btn apply" type="submit"><span class="nojs-only">ดูชุดที่ตรงกับตัวกรอง</span><span class="js-only">ดู <span data-apply-total><?= (int) $result['total'] ?></span> ชุด</span></button>
        <a class="btn btn-ghost" href="<?= e(Catalog::listUrl($base, ['date' => $f['date'], 'free' => $f['free']])) ?>" data-chip>ล้างตัวกรอง</a>
      </div>
    </form>
    <div class="filters-scrim" data-filters-close></div>

    <div>
      <div class="grid-head">
        <p class="grid-count" data-grid-count role="status"><?php View::partial('grid-count', $data); ?></p>
        <div class="chips" data-chips role="group" aria-label="ตัวกรองที่ใช้อยู่"><?php View::partial('chips', $data); ?></div>
        <button class="btn btn-ghost btn-sm filters-open" type="button" data-filters-open aria-controls="filters" aria-expanded="false"><?= icon('sliders-horizontal', 'ic ic-sm') ?>ตัวกรอง</button>
        <label class="sortbox"><span>เรียงตาม</span>
          <select class="select" name="sort" form="filters" aria-label="เรียงตาม">
            <?php foreach (Catalog::SORTS as $k => $label): ?><option value="<?= e($k) ?>"<?= $f['sort'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
        </label>
      </div>

      <div data-feed data-base="<?= e(url($base)) ?>">
        <div class="vits vits-3" data-grid><?php View::partial('grid', ['items' => $result['items'], 'filters' => $f]); ?></div>
        <div data-empty><?php View::partial('list-empty', $data); ?></div>
        <div class="feed-more">
          <?php if ($f['page'] > 1): ?><a class="btn btn-ghost nojs-only" href="<?= e(Catalog::listUrl($base, $f, ['page' => $f['page'] > 2 ? $f['page'] - 1 : null])) ?>">หน้าก่อน</a><?php endif; ?>
          <a class="btn btn-ghost" href="<?= e(Catalog::listUrl($base, $f, ['page' => $f['page'] + 1])) ?>" data-more data-page="<?= $f['page'] + 1 ?>"<?= $more ? '' : ' hidden' ?>>ดูชุดเพิ่มอีก <span data-left><?= (int) $left ?></span> ชุด</a>
        </div>
      </div>
    </div>
  </div>
</div>
