<?php
/**
 * The front of the shop.
 * @var ?string $date @var array $filters @var array $floors @var ?array $freeBy
 * @var array $arrivals @var int $total @var ?array $show @var ?array $showQuote @var ?array $look
 */
use App\Core\View;
use App\Services\Catalog;

$steps = include APP_PATH . '/Views/partials/howto-steps.php';
$baseDays = (int) setting('base_days');
?>
<section class="hero" aria-labelledby="hero-title">
  <picture class="hero-media">
    <source media="(max-width: 899px)" srcset="<?= e(media('site/hero-m.jpg')) ?>" width="1200" height="1680">
    <img src="<?= e(media('site/hero-2160.jpg')) ?>" srcset="<?= e(media('site/hero-2160.jpg')) ?> 2160w, <?= e(media('site/hero-4320.jpg')) ?> 4320w" sizes="max(100vw, calc(300vh - 500px))"
         alt="ผู้หญิงสวมชุดยาวสีขาวเปิดไหล่ ยืนหันข้างในห้องผนังสีน้ำตาลอ่อนที่แสงแดดส่องถึง" width="4320" height="1440" fetchpriority="high" decoding="async">
  </picture>
  <div class="hero-text">
    <div class="wrap">
      <h1 id="hero-title"><span>เช่าชุดออกงาน</span> <span>ที่ว่างในวันงานของคุณ</span></h1>
      <p class="hero-lede">บอกวันงาน แล้วเลือกจากชุดที่ว่างจริงในวันนั้น เห็นค่าเช่าและเงินมัดจำครบก่อนกดจอง</p>
      <?php if ($look): ?>
      <a class="hero-look" href="<?= e(url('/dress/' . rawurlencode($look['code']))) ?>">ชุดในภาพ <?= e($look['name']) ?> · <?= e(money($look['rent_price'])) ?> / <?= $baseDays ?> วัน</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<form class="desk" method="get" action="<?= e(url('/dresses')) ?>" data-desk data-desk-home="<?= e(url('/')) ?>" aria-label="ค้นหาชุดที่ว่างตามวันงาน">
  <label class="desk-cell desk-date">
    <span class="desk-label">วันงาน</span>
    <span class="desk-value<?= $date ? '' : ' is-empty' ?>" data-desk-text aria-hidden="true"><?= $date ? e(thai_date_dow($date)) : 'เลือกวันงาน' ?></span>
    <input class="desk-native" type="date" name="date" value="<?= e($date ?? '') ?>" min="<?= e(today()) ?>" max="<?= e(add_days(today(), (int) setting('booking_horizon_days'))) ?>" data-desk-input>
    <?= icon('calendar') ?>
  </label>
  <label class="desk-cell">
    <span class="desk-label">หมวด</span>
    <select class="desk-select" name="category">
      <option value="">ทุกหมวด</option>
      <?php foreach ($floors as $fl): ?><option value="<?= e($fl['slug']) ?>"><?= e($fl['name']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label class="desk-cell">
    <span class="desk-label">ไซส์</span>
    <select class="desk-select" name="size">
      <option value="">ทุกไซส์</option>
      <?php foreach (Catalog::SIZES as $s): ?><option value="<?= e($s) ?>"><?= $s === 'FREE' ? 'ฟรีไซส์' : e($s) ?></option><?php endforeach; ?>
    </select>
  </label>
  <input type="hidden" name="free" value="1">
  <button class="desk-go" type="submit">ค้นหาชุดที่ว่าง <?= icon('arrow-right') ?></button>
</form>
<p class="sr-only" role="status" data-desk-status></p>

<section class="wrap block" aria-labelledby="cats-title">
  <div class="block-head">
    <h2 id="cats-title">เลือกชุดตามงาน</h2>
    <a class="block-link" href="<?= e(url('/dresses')) ?>">ดูชุดทั้งหมด <?= (int) $total ?> ชุด <?= icon('arrow-right') ?></a>
  </div>
  <div class="cats">
    <?php foreach ($floors as $fl): $cover = $fl['cover_image'] ?: null; ?>
    <a class="cat" href="<?= e(url('/c/' . $fl['slug'])) ?>">
      <span class="cat-photo"><img src="<?= e(thumb($cover)) ?>"<?php if ($set = photo_srcset($cover)): ?> srcset="<?= e($set) ?>" sizes="(max-width: 899px) 58vw, 19vw"<?php endif; ?> alt="" loading="lazy" decoding="async" width="575" height="862"></span>
      <span class="cat-name"><?= e($fl['name']) ?></span>
      <small><span data-floor-count="<?= e($fl['slug']) ?>"><?php View::partial('floor-count', ['floor' => $fl, 'freeBy' => $freeBy]); ?></span><?php if ($fl['min_price'] !== null): ?> · เริ่ม <?= e(money($fl['min_price'])) ?><?php endif; ?></small>
    </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="wrap block" aria-labelledby="new-title">
  <div class="block-head">
    <h2 id="new-title">มาใหม่ในร้าน</h2>
    <a class="block-link" href="<?= e(url('/dresses', ['new' => 1])) ?>">ดูชุดมาใหม่ทั้งหมด <?= icon('arrow-right') ?></a>
  </div>
  <div class="vits" data-grid data-home-grid>
    <?php View::partial('grid', ['items' => $arrivals, 'filters' => $filters, 'markNew' => false]); ?>
  </div>
</section>

<?php if ($show && $showQuote): ?>
<section class="band" aria-labelledby="price-title">
  <div class="wrap band-in">
    <div>
      <h2 id="price-title">เห็นราคาครบทุกบรรทัด<br>ก่อนกดจอง</h2>
      <p>ค่าเช่า เงินมัดจำ และค่าปรับถ้าคืนช้า บอกไว้ตั้งแต่หน้าชุด ยอดที่ชำระตอนจองคือยอดเดียวกับที่เห็นตรงนี้ เงินมัดจำคืนเต็มจำนวนเมื่อคืนชุดตรงเวลาและสภาพสมบูรณ์</p>
      <a class="bill-dress" href="<?= e(url('/dress/' . rawurlencode($show['code']))) ?>">
        <img src="<?= e(mini($show['image'])) ?>" alt="" loading="lazy" decoding="async" width="56" height="84">
        <span>ตัวเลขนี้เป็นของชุด<b><?= e($show['code']) ?> <?= e($show['name']) ?></b></span>
      </a>
    </div>
    <div>
      <dl class="bill-lines">
        <div><dt>ค่าเช่า <?= $baseDays ?> วัน<small>วันรับชุด วันงาน และวันคืนชุด</small></dt><dd><?= e(money($showQuote['rent'], false)) ?><small>บาท</small></dd></div>
        <div><dt>เงินมัดจำ<small>คืนให้เมื่อคืนชุด</small></dt><dd><?= e(money($showQuote['deposit'], false)) ?><small>บาท</small></dd></div>
        <div class="bill-total"><dt>ชำระตอนจอง</dt><dd><?= e(money($showQuote['total'], false)) ?><small>บาท</small></dd></div>
      </dl>
      <p class="bill-notes">เช่าเพิ่มหลังวันที่ <?= $baseDays ?> วันละ <?= e(money($show['extra_day_price'])) ?> · คืนช้าวันละ <?= e(money($show['late_fee_per_day'])) ?></p>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="wrap block" aria-labelledby="how-title">
  <div class="how">
    <div class="how-head">
      <h2 id="how-title">เช่าชุดในห้าขั้น</h2>
      <a class="block-link" href="<?= e(url('/how-to-rent')) ?>">เงื่อนไขการเช่าทั้งหมด <?= icon('arrow-right') ?></a>
    </div>
    <ol class="how-rows">
      <?php foreach ($steps as [$head, $body]): ?>
      <li><b><?= e($head) ?></b><p><?= e($body) ?></p></li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section class="split" aria-labelledby="visit-title">
  <div class="split-photo">
    <img src="<?= e(media('site/fitting.jpg')) ?>" alt="" loading="lazy" decoding="async" width="1600" height="1600">
  </div>
  <div class="split-text">
    <h2 id="visit-title">ลองชุดจริงที่ร้าน<br>ก่อนตัดสินใจจอง</h2>
    <p>เลือกวันและเวลานัดได้เองจากหน้าบัญชี บอกชุดที่อยากลองไว้ล่วงหน้า ร้านจะเตรียมไว้รอ</p>
    <dl class="visit-facts">
      <div><dt>ที่อยู่</dt><dd><?= e(setting('shop_address')) ?></dd></div>
      <div><dt>เวลาเปิด</dt><dd><?= e(setting('shop_hours')) ?></dd></div>
      <div><dt>โทร</dt><dd><a href="tel:<?= e(preg_replace('/\D/', '', (string) setting('shop_phone'))) ?>"><?= e(setting('shop_phone')) ?></a></dd></div>
      <?php if (setting('shop_line')): ?><div><dt>LINE</dt><dd><?= e(setting('shop_line')) ?></dd></div><?php endif; ?>
    </dl>
    <div class="row">
      <a class="btn" href="<?= e(url('/account/appointments')) ?>">นัดลองชุด</a>
      <a class="block-link" href="<?= e(url('/dresses')) ?>">ดูชุดทั้งหมด <?= icon('arrow-right') ?></a>
    </div>
  </div>
</section>
