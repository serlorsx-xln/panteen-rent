<?php
/** @var array $items @var array $pg @var string $q @var int $cat @var string $status @var array $categories */
use App\Services\Status;
$qs = fn(array $o = []) => url('/admin/costumes', array_filter(array_merge(['q' => $q, 'category' => $cat ?: null, 'status' => $status], $o), fn($v) => $v !== '' && $v !== null));
?>
<div class="page-head">
  <div><h1>ชุด</h1><p class="muted"><?= (int) $pg['total'] ?> ชุด<?= $status === 'retired' ? ' ที่เลิกให้เช่า' : '' ?></p></div>
  <a class="btn" href="<?= e(url('/admin/costumes/new')) ?>"><?= icon('plus') ?>เพิ่มชุด</a>
</div>
<form class="filterbar" method="get" action="<?= e(url('/admin/costumes')) ?>">
  <div class="field"><label class="label" for="cq">ค้นหา</label><input class="input" id="cq" type="search" name="q" value="<?= e($q) ?>" placeholder="รหัส ชื่อ หรือสี"></div>
  <div class="field"><label class="label" for="cc">ประเภท</label>
    <select class="select" id="cc" name="category" data-autosubmit><option value="">ทุกประเภท</option>
      <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="field"><label class="label" for="cs">สถานะ</label>
    <select class="select" id="cs" name="status" data-autosubmit><option value="">ใช้งานอยู่ทั้งหมด</option>
      <?php foreach (Status::COSTUME as $k => [$l]): ?><option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select></div>
  <button class="btn btn-ghost" type="submit"><?= icon('search', 'ic ic-sm') ?>ค้นหา</button>
</form>
<?php if (!$items): ?>
<div class="empty"><?= icon('dress', 'ic ic-lg') ?><h2>ไม่พบชุด</h2><p>ลองเปลี่ยนตัวกรอง หรือ<a href="<?= e(url('/admin/costumes/new')) ?>">เพิ่มชุดใหม่</a></p></div>
<?php else: ?>
<div class="tablewrap">
  <table class="table">
    <thead><tr><th>ชุด</th><th>ประเภท</th><th>ไซส์</th><th class="r">ค่าเช่า</th><th class="r">มัดจำ</th><th>ตอนนี้</th><th>คิวถัดไป</th><th class="r">เช่าไปแล้ว</th></tr></thead>
    <tbody>
      <?php foreach ($items as $c): ?>
      <tr>
        <td><a class="cell-main" href="<?= e(url('/admin/costumes/' . $c['id'])) ?>"><img class="thumb" src="<?= e(mini($c['image'])) ?>" alt=""><span><b><?= e($c['code']) ?><?= $c['is_featured'] ? ' <small title="แนะนำหน้าร้าน">· แนะนำ</small>' : '' ?></b><small><?= e(str_limit($c['name'], 34)) ?></small></span></a></td>
        <td class="nowrap"><span class="swatch" style="background:<?= e($c['category_color']) ?>;width:12px;height:12px"></span> <?= e($c['category_name']) ?></td>
        <td><?= e($c['size_label'] === 'FREE' ? 'ฟรีไซส์' : $c['size_label']) ?></td>
        <td class="r num"><?= e(number_format((float) $c['rent_price'])) ?></td>
        <td class="r num"><?= e(number_format((float) $c['deposit'])) ?></td>
        <td><?= Status::badge(Status::INVENTORY, $c['inventory']) ?></td>
        <td class="num nowrap"><?= $c['next_pickup'] ? e(thai_date($c['next_pickup'], false, false)) : '<span class="faint">–</span>' ?></td>
        <td class="r num"><?= (int) $c['rentals'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php \App\Core\View::partial('pager', ['pg' => $pg, 'make' => fn($p) => $qs(['page' => $p])]); ?>
<?php endif; ?>
