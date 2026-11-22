<?php
/**
 * Dress cards. With a date chosen, each card says in words whether the dress is free
 * that day, and a taken dress is dimmed. A new dress says so in its size line, but only where new ones are
 * the exception: under a heading that already says มาใหม่ ($markNew false), or when most cards are new, the word
 * would only repeat itself.
 * @var array $items @var array $filters @var ?bool $markNew
 */
use App\Core\Auth;

$date = $filters['date'] ?? null;
$newSince = add_days(today(), -45);
$baseDays = (int) setting('base_days');
$member = Auth::check() && !Auth::isStaff();
$day = $date ? thai_date($date, false, false) : '';
$isNew = static fn (array $it): bool => !empty($it['arrived_at']) && $it['arrived_at'] >= $newSince;
$newShown = count(array_filter($items, $isNew));
$markNew = ($markNew ?? true) && $items && $newShown * 3 < count($items);
foreach ($items as $i => $it):
    $busy = $date && (int) $it['busy'] > 0;
    $href = url('/dress/' . rawurlencode($it['code']), array_filter(['date' => $date]));
    $size = $it['size_label'] === 'FREE' ? 'ฟรีไซส์' : 'ไซส์ ' . $it['size_label'];
    $fav = !empty($it['fav']);
    $small = thumb($it['image']);
    $set = photo_srcset($it['image']);
?>
<article class="vit<?= $date ? ($busy ? ' is-busy' : ' is-free') : '' ?>" data-code="<?= e($it['code']) ?>" style="--i:<?= (int) $i ?>">
  <a class="vit-link" href="<?= e($href) ?>">
    <span class="vit-photo">
      <img src="<?= e($small) ?>"<?php if ($set): ?> srcset="<?= e($set) ?>" sizes="(max-width: 899px) 47vw, 25vw"<?php endif; ?> alt="" loading="<?= $i < 4 ? 'eager' : 'lazy' ?>" decoding="async" width="575" height="862">
    </span>
    <span class="vit-cap">
      <?php if ($date): ?><span class="vit-avail"><?= $busy ? 'ไม่ว่าง ' : 'ว่าง ' ?><?= e($day) ?></span><?php endif; ?>
      <span class="vit-name"><?= e($it['name']) ?></span>
      <span class="vit-meta"><?= e($size) ?><?= $it['color_name'] ? ' · สี' . e($it['color_name']) : '' ?><?= $markNew && $isNew($it) ? ' · มาใหม่' : '' ?></span>
      <span class="vit-price"><?= e(money($it['rent_price'])) ?> <small>/ <?= $baseDays ?> วัน</small></span>
    </span>
  </a>
  <?php if ($member): ?>
  <form class="vit-fav" method="post" action="<?= e(url('/favorites/' . rawurlencode($it['code']))) ?>" data-fav>
    <?= csrf_field() ?>
    <button type="submit" data-name="<?= e($it['name']) ?>" aria-pressed="<?= $fav ? 'true' : 'false' ?>" aria-label="<?= $fav ? 'นำออกจากชุดที่ถูกใจ' : 'เก็บไว้ในชุดที่ถูกใจ' ?>: <?= e($it['name']) ?>"><?= icon('heart', 'ic' . ($fav ? ' is-filled' : '')) ?></button>
  </form>
  <?php endif; ?>
</article>
<?php endforeach;
