<?php
/**
 * One dress: the photograph fills the left half of the window; on the right the event date,
 * whether the dress is free that day, and every line of the price. The month calendar and
 * the measurements follow underneath.
 * @var array $c @var array $images @var ?string $date @var ?array $quote @var ?bool $free @var array $fit
 * @var string $ym @var array $busy @var bool $isFav @var array $related @var int $timesRented @var ?array $floor
 */
use App\Core\Auth;
use App\Core\View;

$code = rawurlencode($c['code']);
$main = $images[0] ?? null;
// the photograph in two sizes: the catalogue file, and a larger copy for a half-window on a dense screen
$mainPath = $main['path'] ?? null;
$mainSize = media_size($mainPath);
$bigSize = $mainPath ? media_size(dirname($mainPath) . '/large/' . basename($mainPath)) : null;
$size = $c['size_label'] === 'FREE' ? 'ฟรีไซส์' : 'ไซส์ ' . $c['size_label'];
$active = $c['status'] === 'active';
$bookUrl = url('/book/' . $code, array_filter(['event' => $date]));
$baseDays = (int) setting('base_days');
$inch = fn($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
$range = fn($a, $b) => $a !== null && $b !== null ? ($inch($a) === $inch($b) ? $inch($a) : $inch($a) . '–' . $inch($b)) . ' นิ้ว' : null;
$specs = array_filter([
    'รหัสชุด' => $c['code'],
    'หมวด' => $c['category_name'],
    'ไซส์' => $c['size_label'] === 'FREE' ? 'ฟรีไซส์' : $c['size_label'],
    'สี' => $c['color_name'] ?: null,
    'รอบอก' => $range($c['bust_min'], $c['bust_max']),
    'รอบเอว' => $range($c['waist_min'], $c['waist_max']),
    'สะโพกไม่เกิน' => $c['hip_max'] !== null ? $inch($c['hip_max']) . ' นิ้ว' : null,
    'ความยาวชุด' => $c['length_in'] ? $inch($c['length_in']) . ' นิ้ว' : null,
]);
?>
<article class="pdp">
  <div data-gallery>
    <div class="vitrine">
      <img class="main" src="<?= e(media($mainPath)) ?>"<?php if ($mainSize && $bigSize): ?> srcset="<?= e(media($mainPath)) ?> <?= $mainSize[0] ?>w, <?= e(large($mainPath)) ?> <?= $bigSize[0] ?>w" sizes="(max-width: 899px) 100vw, 50vw"<?php endif; ?> alt="<?= e($c['name'] . ($c['color_name'] && !str_contains($c['name'], $c['color_name']) ? ' สี' . $c['color_name'] : '')) ?>" width="<?= $mainSize[0] ?? 933 ?>" height="<?= $mainSize[1] ?? 1400 ?>" data-gallery-main fetchpriority="high">
    </div>
    <?php if (count($images) > 1): ?>
    <div class="vitrine-thumbs">
      <?php foreach ($images as $i => $img): ?>
      <button type="button" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>" data-gallery-src="<?= e(large($img['path'])) ?>" data-credit="<?= e($img['credit'] ?? '') ?>" aria-label="ดูรูปที่ <?= $i + 1 ?>">
        <img src="<?= e(thumb($img['path'])) ?>" alt="" loading="lazy">
      </button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($main['credit'])):
        $parts = explode(' · ', $main['credit']); ?>
      <a class="vitrine-credit" href="<?= e(url('/credits')) ?>" data-gallery-credit><?= e($parts[0]) ?></a>
    <?php endif; ?>
  </div>

  <div class="pdp-info">
    <div class="pdp-box">
      <nav class="crumbs" aria-label="ตำแหน่งในเว็บไซต์">
        <a href="<?= e(url('/')) ?>">หน้าแรก</a><?= icon('chevron-right', 'ic ic-sm') ?>
        <a href="<?= e(url('/c/' . $c['category_slug'])) ?>"><?= e($c['category_name']) ?></a><?= icon('chevron-right', 'ic ic-sm') ?>
        <span aria-current="page"><?= e($c['code']) ?></span>
      </nav>

      <header class="pdp-head">
        <h1 class="pdp-title"><?= e($c['name']) ?></h1>
        <div class="pdp-tools">
          <?php if (Auth::check() && !Auth::isStaff()): ?>
          <form method="post" action="<?= e(url('/favorites/' . $code)) ?>" data-fav>
            <?= csrf_field() ?>
            <button class="iconbtn" type="submit" aria-pressed="<?= $isFav ? 'true' : 'false' ?>" aria-label="<?= $isFav ? 'นำออกจากชุดที่ถูกใจ' : 'เก็บไว้ในชุดที่ถูกใจ' ?>" title="ชุดที่ถูกใจ">
              <?= icon('heart', 'ic' . ($isFav ? ' is-filled' : '')) ?>
            </button>
          </form>
          <?php elseif (!Auth::check()): ?>
          <a class="iconbtn" href="<?= e(url('/login')) ?>" aria-label="เข้าสู่ระบบเพื่อเก็บชุดที่ถูกใจ" title="ชุดที่ถูกใจ"><?= icon('heart') ?></a>
          <?php endif; ?>
          <button class="iconbtn" type="button" data-share data-title="<?= e($c['name']) ?>" aria-label="แชร์ลิงก์ชุดนี้" title="แชร์"><?= icon('share-2') ?></button>
          <?php if (Auth::isStaff()): ?>
          <a class="iconbtn" href="<?= e(url('/admin/costumes/' . $c['id'])) ?>" aria-label="แก้ไขในระบบหลังร้าน" title="แก้ไข"><?= icon('pencil') ?></a>
          <?php endif; ?>
        </div>
      </header>
      <p class="pdp-spec"><?= e($c['code']) ?> · <?= e($size) ?><?= $c['color_name'] ? ' · สี' . e($c['color_name']) : '' ?><?= $timesRented ? ' · เช่าไปแล้ว ' . (int) $timesRented . ' ครั้ง' : '' ?></p>
      <p class="pdp-price"><?= e(money($c['rent_price'], false)) ?><small>บาท / <?= $baseDays ?> วัน</small></p>

      <?php if ($active): ?>
      <form class="pdp-date" method="get" action="<?= e(url('/dress/' . $code)) ?>" data-desk data-desk-go aria-label="วันงานของคุณ">
        <label class="desk-cell desk-date">
          <span class="desk-label">วันงาน</span>
          <span class="desk-value<?= $date ? '' : ' is-empty' ?>" data-desk-text aria-hidden="true"><?= $date ? e(thai_date_dow($date)) : 'เลือกวันงาน' ?></span>
          <input class="desk-native" type="date" name="date" value="<?= e($date ?? '') ?>" min="<?= e(today()) ?>" max="<?= e(add_days(today(), (int) setting('booking_horizon_days'))) ?>" data-desk-input>
          <?= icon('calendar') ?>
        </label>
        <button class="btn btn-ghost nojs-only" type="submit">ดูวันนี้</button>
      </form>
      <?php endif; ?>

      <div id="verdict">
      <?php if (!$active): ?>
        <p class="verdict is-none"><span class="d"></span>ชุดนี้งดให้เช่าชั่วคราว</p>
        <p class="verdict-sub">กำลังซ่อมหรือปรับแก้ ดูชุดอื่นในหมวด<?= e($c['category_name']) ?>ด้านล่างได้</p>
      <?php elseif ($date && $free): ?>
        <p class="verdict is-free"><span class="d"></span>ว่าง <?= e(thai_date_full($date)) ?></p>
        <p class="verdict-sub">รับชุด <?= e(thai_date($quote['pickup'], false, false)) ?> · ใส่วันงาน · คืนชุด <?= e(thai_date($quote['return'], false, false)) ?> ก่อน <?= e(setting('default_return_time')) ?> น.</p>
      <?php elseif ($date): ?>
        <p class="verdict is-busy"><span class="d"></span>ไม่ว่าง <?= e(thai_date_full($date)) ?></p>
        <p class="verdict-sub">มีคนจองช่วงนี้แล้ว <a href="#availability">ดูวันที่ยังว่างในปฏิทิน</a> หรือดูชุดอื่นที่ว่างวันนั้น</p>
      <?php else: ?>
        <p class="verdict is-none"><span class="d"></span>เลือกวันงานเพื่อดูว่าชุดนี้ว่างไหม</p>
        <p class="verdict-sub">ร้านจะคำนวณวันรับและวันคืนชุดให้ หรือ<a href="#availability">ดูวันว่างทั้งเดือนในปฏิทิน</a></p>
      <?php endif; ?>
      </div>

      <?php if (!Auth::isStaff() && ($fit['state'] !== 'unknown' || Auth::check())): ?>
      <p class="fit fit-<?= e($fit['state']) ?>">
        <?= icon($fit['state'] === 'fit' ? 'circle-check' : ($fit['state'] === 'unknown' ? 'ruler' : 'circle-alert')) ?>
        <span><?= e($fit['text']) ?><?php if ($fit['state'] === 'unknown'): ?> · <a href="<?= e(url('/account#size')) ?>">ใส่สัดส่วน</a><?php endif; ?></span>
      </p>
      <?php endif; ?>

      <?php if ($active): ?>
      <div class="pdp-cta">
        <?php if (Auth::isStaff()): ?>
          <a class="btn btn-lg" href="<?= e(url('/admin/bookings/new', array_filter(['dress' => $c['code'], 'event' => $date]))) ?>"><?= icon('calendar-plus') ?>จองหน้าร้านให้ลูกค้า</a>
        <?php elseif ($date && $free): ?>
          <a class="btn btn-lg" href="<?= e($bookUrl) ?>">จองชุดนี้ วันงาน <?= e(thai_date($date, false, false)) ?><?= icon('arrow-right') ?></a>
        <?php elseif ($date): ?>
          <a class="btn btn-lg" href="<?= e(url('/dresses', ['date' => $date, 'free' => 1])) ?>">ดูชุดอื่นที่ว่างวันนั้น<?= icon('arrow-right') ?></a>
        <?php else: ?>
          <a class="btn btn-lg" href="<?= e($bookUrl) ?>">เลือกวันและจอง<?= icon('arrow-right') ?></a>
        <?php endif; ?>
        <a class="btn btn-lg btn-ghost" href="<?= e(url('/account/appointments', array_filter(['dress' => $c['code'], 'date' => $date ? max(today(), add_days($date, -3)) : null]))) ?>">นัดลองชุดที่ร้าน</a>
      </div>
      <?php endif; ?>

      <?php if ($quote && $active && $free): ?>
        <?php View::partial('quote', ['q' => $quote, 'c' => $c, 'pickup' => $quote['pickup'], 'return' => $quote['return']]); ?>
      <?php else: $taken = $date && $active; /* the dress cannot be had that day: its prices stay in view, quieter, for the days it can */ ?>
      <div class="strip<?= $taken ? ' is-muted' : '' ?>">
        <?php if ($taken): ?><p class="strip-for">ราคานี้ใช้กับวันอื่นที่ชุดยังว่าง</p><?php endif; ?>
        <div class="strip-row"><span>ค่าเช่า <?= $baseDays ?> วัน<small>วันรับชุด วันงาน และวันคืนชุด</small></span><span><?= e(money($c['rent_price'])) ?></span></div>
        <div class="strip-row"><span>เงินมัดจำ<small>ได้คืนเมื่อคืนชุดตรงเวลาและสภาพสมบูรณ์</small></span><span><?= e(money($c['deposit'])) ?></span></div>
        <div class="strip-row strip-total"><span>ชำระตอนจอง</span><span class="num"><?= e(money((float) $c['rent_price'] + (float) $c['deposit'], false)) ?> <small>บาท</small></span></div>
        <p class="strip-notes">เช่าได้สูงสุด <?= (int) setting('max_days') ?> วัน วันเพิ่มวันละ <?= e(money($c['extra_day_price'])) ?> · คืนช้าวันละ <?= e(money($c['late_fee_per_day'])) ?> หักจากเงินมัดจำ</p>
      </div>
      <?php endif; ?>


    </div>
  </div>
</article>

<div class="pdp-more<?= $active ? '' : ' is-single' ?>">
  <?php if ($active): ?>
  <section id="availability" aria-labelledby="h-cal">
    <h2 id="h-cal">วันที่ชุดนี้ว่าง</h2>
    <div data-cal-wrap>
      <?php View::partial('calendar', ['c' => $c, 'ym' => $ym, 'selected' => $date, 'link' => 'dress']); ?>
    </div>
    <?php if ($busy): ?>
    <p class="busy-list">ช่วงที่มีคนจองแล้ว: <?= e(implode(', ', array_map(fn($r) => thai_date($r['s'], false, false) . '–' . thai_date($r['e'], false, false), $busy))) ?></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section id="details" aria-labelledby="h-detail">
    <h2 id="h-detail">รายละเอียดชุด</h2>
    <?php if (trim((string) $c['description']) !== ''): ?>
    <p class="pdp-desc"><?= nl2br(e($c['description'])) ?></p>
    <?php endif; ?>
    <table class="spec-table">
      <tbody>
        <?php foreach ($specs as $k => $v): ?>
        <tr><th scope="row"><?= e($k) ?></th><td><?= e($v) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>

<?php if ($related): ?>
<div class="wrap">
  <section class="pdp-related" aria-labelledby="h-related">
    <div class="block-head">
      <h2 id="h-related">ชุดอื่นในหมวด<?= e($c['category_name']) ?></h2>
      <a class="block-link" href="<?= e(url('/c/' . $c['category_slug'])) ?>">ดูทั้งหมวด <?= icon('arrow-right') ?></a>
    </div>
    <div class="vits" data-grid>
      <?php View::partial('grid', ['items' => $related, 'filters' => ['date' => $date]]); ?>
    </div>
  </section>
</div>
<?php endif; ?>
