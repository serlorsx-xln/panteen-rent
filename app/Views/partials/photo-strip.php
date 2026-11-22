<?php
/** @var array<string, list<array>> $photos stage => rows (Photos::forBooking) */
use App\Services\Status;

$photos = array_filter($photos);
?>
<?php if ($photos): ?>
<div class="shots">
  <?php foreach ($photos as $stage => $rows): ?>
  <figure class="shots-group">
    <figcaption><?= e(Status::PHOTO_STAGE[$stage] ?? $stage) ?> <span class="faint">· <?= count($rows) ?> รูป · <?= e(thai_datetime($rows[0]['created_at'])) ?></span></figcaption>
    <ul>
      <?php foreach ($rows as $i => $p): ?>
      <li><a href="<?= e(url('/condition/' . $p['id'])) ?>" target="_blank" rel="noopener"><img src="<?= e(url('/condition/' . $p['id'])) ?>" alt="<?= e((Status::PHOTO_STAGE[$stage] ?? 'รูป') . ' รูปที่ ' . ($i + 1)) ?>" loading="lazy" width="96" height="128"></a></li>
      <?php endforeach; ?>
    </ul>
  </figure>
  <?php endforeach; ?>
</div>
<?php endif; ?>
