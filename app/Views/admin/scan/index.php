<?php
/** @var string $q @var ?array $found */
use App\Services\Status;
?>
<div class="page-head">
  <div><h1>สแกนชุด</h1><p class="muted">ยิงบาร์โค้ด/QR ที่ป้ายชุดหรือใบแพ็ก หรือพิมพ์รหัสชุด เช่น GR-001 หรือเลขจอง เช่น PT69-0012 ระบบพาไปขั้นตอนถัดไปของชุดนั้น</p></div>
</div>
<div class="scan" data-scan>
  <form method="get" action="<?= e(url('/admin/scan')) ?>" class="scan-form">
    <label class="label" for="scan-q">รหัสชุดหรือเลขจอง</label>
    <div class="row">
      <input class="input num scan-input" id="scan-q" name="q" value="<?= e($q) ?>" autocomplete="off" autocapitalize="characters" spellcheck="false" autofocus placeholder="สแกนหรือพิมพ์ แล้วกด Enter">
      <button class="btn" type="submit"><?= icon('search') ?>ค้นหา</button>
    </div>
  </form>
  <div class="scan-cam" data-scan-cam hidden>
    <button class="btn btn-ghost" type="button" data-scan-start><?= icon('camera') ?>ใช้กล้องสแกน QR</button>
    <video data-scan-video playsinline muted hidden></video>
    <p class="small muted" data-scan-msg role="status"></p>
  </div>
</div>

<?php if ($found && isset($found['none'])): ?>
<div class="empty"><?= icon('search', 'ic ic-lg') ?><h2>ไม่พบ “<?= e($q) ?>”</h2><p>ตรวจรหัสชุดหรือเลขจองอีกครั้ง</p></div>
<?php elseif ($found): $c = $found['costume']; ?>
<section class="box" style="margin-top:16px" aria-labelledby="h-found">
  <div class="box-head"><h2 id="h-found"><?= e($c['code']) ?> · <?= e($c['name']) ?></h2>
    <?= Status::badge(Status::INVENTORY, $found['state']['state']) ?>
    <a href="<?= e(url('/admin/costumes/' . $c['id'])) ?>">ข้อมูลชุด</a></div>
  <?php if (!$found['next']): ?>
  <p class="muted">ชุดนี้ไม่ได้อยู่ในการเช่าใด อยู่บนราวหรือรอซัก</p>
  <?php else: ?>
  <ul class="rows">
    <?php foreach ($found['next'] as $n): $b = $n['b']; ?>
    <li style="grid-template-columns:minmax(0,1fr) auto auto">
      <span><b class="num"><?= e($b['booking_no']) ?></b> · <?= e(full_name($b)) ?><div class="sub"><?= e(thai_date($b['pickup_date'])) ?> – <?= e(thai_date($b['return_date'])) ?></div></span>
      <?= Status::badge(Status::BOOKING, $b['status']) ?>
      <a class="btn btn-sm" href="<?= e(url($n['href'])) ?>"><?= e($n['label']) ?></a>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<?php endif; ?>
