<?php
/** @var array $c @var array $categories @var array $images @var array $codes @var ?array $inventory @var ?array $upcoming */
use App\Services\Status;
$isNew = empty($c['id']);
$v = fn(string $k, $d = '') => old($k, $c[$k] ?? $d);
$num = fn($x) => $x === null || $x === '' ? '' : rtrim(rtrim((string) $x, '0'), '.');
$action = $isNew ? url('/admin/costumes') : url('/admin/costumes/' . $c['id']);
?>
<nav class="crumbs"><a href="<?= e(url('/admin/costumes')) ?>"><?= icon('arrow-left', 'ic ic-sm') ?> ชุดทั้งหมด</a></nav>
<div class="page-head">
  <div><h1><?= $isNew ? 'เพิ่มชุดใหม่' : e($c['code'] . ' · ' . $c['name']) ?></h1>
  <?php if (!$isNew): ?><p class="muted"><?= e(Status::label(Status::INVENTORY, $inventory['state'])) ?> · <a href="<?= e(url('/dress/' . rawurlencode($c['code']))) ?>" target="_blank">ดูในหน้าร้าน</a></p><?php endif; ?></div>
  <?php if (!$isNew): ?><div class="row"><a class="btn btn-ghost" href="<?= e(url('/admin/costumes/' . $c['id'] . '/tag')) ?>" target="_blank"><?= icon('printer') ?>ป้าย QR</a><a class="btn btn-ghost" href="<?= e(url('/admin/bookings/new', ['dress' => $c['code']])) ?>"><?= icon('calendar-plus') ?>จองชุดนี้</a></div><?php endif; ?>
</div>

<div class="formgrid">
<form class="box" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <div class="box-head"><h2>ข้อมูลชุด</h2></div>
  <div class="grid-2">
    <div class="field">
      <label class="label" for="category_id">ประเภท</label>
      <select class="select" id="category_id" name="category_id" <?= invalid_attr('category_id') ?> <?= $isNew ? 'data-code-map=\'' . e(json_encode($codes)) . '\'' : '' ?>>
        <?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>" <?= (int) $v('category_id') === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?>
      </select><?= field_error('category_id') ?>
    </div>
    <div class="field">
      <label class="label" for="code">รหัสชุด</label>
      <input class="input num" id="code" name="code" value="<?= e($v('code')) ?>" maxlength="20" required <?= invalid_attr('code') ?> style="text-transform:uppercase">
      <?= field_error('code') ?: '<span class="hint">ลูกค้าใช้รหัสนี้อ้างอิงตอนทักร้าน</span>' ?>
    </div>
  </div>
  <div class="field" style="margin-top:14px"><label class="label" for="name">ชื่อชุด</label><input class="input" id="name" name="name" value="<?= e($v('name')) ?>" maxlength="150" required <?= invalid_attr('name') ?>><?= field_error('name') ?></div>
  <div class="field" style="margin-top:14px"><label class="label" for="description">รายละเอียด</label><textarea class="input" id="description" name="description" rows="4" maxlength="2000" placeholder="ผ้า ทรง จุดเด่น วิธีใส่ สิ่งที่มาพร้อมชุด"><?= e($v('description')) ?></textarea><?= field_error('description') ?></div>
  <div class="grid-2" style="margin-top:14px">
    <div class="field"><label class="label" for="color_name">สี</label><input class="input" id="color_name" name="color_name" value="<?= e($v('color_name')) ?>" maxlength="50" placeholder="เช่น ชมพูกะปิ"><?= field_error('color_name') ?></div>
    <div class="field"><label class="label" for="size_label">ไซส์</label>
      <select class="select" id="size_label" name="size_label"><?php foreach (['XS', 'S', 'M', 'L', 'XL', '2XL', 'FREE'] as $s): ?><option value="<?= $s ?>" <?= $v('size_label') === $s ? 'selected' : '' ?>><?= $s === 'FREE' ? 'ฟรีไซส์' : $s ?></option><?php endforeach; ?></select></div>
  </div>

  <div class="box-head" style="margin-top:22px"><h2>สัดส่วนที่ใส่ได้ (นิ้ว)</h2></div>
  <p class="small muted" style="margin:-4px 0 10px">ใช้เทียบกับสัดส่วนที่ลูกค้าบันทึกไว้ เพื่อบอกว่าชุดนี้น่าจะพอดีไหม</p>
  <div class="grid-3">
    <?php foreach (['bust_min' => 'รอบอก ต่ำสุด', 'bust_max' => 'รอบอก สูงสุด', 'waist_min' => 'รอบเอว ต่ำสุด', 'waist_max' => 'รอบเอว สูงสุด', 'hip_max' => 'สะโพก สูงสุด', 'length_in' => 'ความยาวชุด'] as $k => $l): ?>
    <div class="field"><label class="label" for="<?= $k ?>"><?= e($l) ?></label><input class="input num" id="<?= $k ?>" name="<?= $k ?>" inputmode="decimal" value="<?= e($num($v($k))) ?>" <?= invalid_attr($k) ?>><?= field_error($k) ?></div>
    <?php endforeach; ?>
  </div>

  <div class="box-head" style="margin-top:22px"><h2>ราคา (บาท)</h2></div>
  <div class="grid-2">
    <div class="field"><label class="label" for="rent_price">ค่าเช่า <?= (int) setting('base_days') ?> วัน</label><input class="input num" id="rent_price" name="rent_price" inputmode="decimal" value="<?= e($num($v('rent_price'))) ?>" required <?= invalid_attr('rent_price') ?>><?= field_error('rent_price') ?></div>
    <div class="field"><label class="label" for="extra_day_price">วันเพิ่ม / วัน</label><input class="input num" id="extra_day_price" name="extra_day_price" inputmode="decimal" value="<?= e($num($v('extra_day_price'))) ?>" required <?= invalid_attr('extra_day_price') ?>><?= field_error('extra_day_price') ?></div>
    <div class="field"><label class="label" for="deposit">เงินมัดจำ</label><input class="input num" id="deposit" name="deposit" inputmode="decimal" value="<?= e($num($v('deposit'))) ?>" required <?= invalid_attr('deposit') ?>><?= field_error('deposit') ?></div>
    <div class="field"><label class="label" for="late_fee_per_day">ค่าปรับคืนช้า / วัน</label><input class="input num" id="late_fee_per_day" name="late_fee_per_day" inputmode="decimal" value="<?= e($num($v('late_fee_per_day'))) ?>" required <?= invalid_attr('late_fee_per_day') ?>><?= field_error('late_fee_per_day') ?></div>
  </div>
  <?php if (!$isNew): ?><p class="hint" style="margin-top:8px">เปลี่ยนราคาแล้วมีผลกับการจองใหม่เท่านั้น</p><?php endif; ?>

  <div class="box-head" style="margin-top:22px"><h2>ส่งชุดและตรวจคืน</h2></div>
  <div class="grid-2">
    <div class="field"><label class="label" for="replacement_value">มูลค่าชุด <span class="opt">(บาท)</span></label><input class="input num" id="replacement_value" name="replacement_value" inputmode="decimal" value="<?= e($num($v('replacement_value'))) ?>" <?= invalid_attr('replacement_value') ?>><?= field_error('replacement_value') ?: '<span class="hint">เพดานค่าเสียหาย และฐานของรายการคิดเป็นร้อยละ</span>' ?></div>
    <div class="field"><label class="label" for="accessories">ของที่มากับชุด <span class="opt">(ไม่บังคับ)</span></label><input class="input" id="accessories" name="accessories" maxlength="300" value="<?= e($v('accessories')) ?>" placeholder="เช่น สไบ, เข็มขัด, ถุงคลุมชุด" <?= invalid_attr('accessories') ?>><?= field_error('accessories') ?: '<span class="hint">คั่นด้วยจุลภาค ขึ้นในใบแพ็กและหน้าตรวจคืน</span>' ?></div>
  </div>

  <div class="box-head" style="margin-top:22px"><h2>การแสดงผล</h2></div>
  <div class="grid-2">
    <div class="field"><label class="label" for="status">สถานะ</label>
      <select class="select" id="status" name="status"><?php foreach (Status::COSTUME as $k => [$l]): ?><option value="<?= $k ?>" <?= $v('status') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
      <span class="hint">ซ่อม/เลิกให้เช่า จะไม่แสดงในหน้าร้านและจองไม่ได้</span></div>
    <div class="field"><label class="label" for="arrived_at">วันที่รับชุดเข้าร้าน</label><input class="input" id="arrived_at" type="date" name="arrived_at" value="<?= e($v('arrived_at')) ?>"><span class="hint">ชุดที่เข้าภายใน 45 วันขึ้นป้าย “ใหม่”</span></div>
  </div>
  <label class="check" style="margin-top:12px"><input type="checkbox" name="is_featured" value="1" <?= $v('is_featured') ? 'checked' : '' ?>><span>แนะนำชุดนี้ (ขึ้นก่อนในหน้าร้าน)</span></label>

  <?php if ($isNew): ?>
  <div class="box-head" style="margin-top:22px"><h2>รูปชุด</h2></div>
  <div class="field"><label class="label" for="images">เลือกรูป <span class="opt">(JPG/PNG/WebP ไม่เกิน 5 MB ต่อรูป ใส่ได้หลายรูป)</span></label><input class="input" id="images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple></div>
  <div class="field" style="margin-top:10px"><label class="label" for="credit">ที่มาของภาพ <span class="opt">(ถ้าไม่ได้ถ่ายเอง)</span></label><input class="input" id="credit" name="credit" maxlength="255"></div>
  <?php endif; ?>

  <div class="row" style="margin-top:22px"><button class="btn btn-lg" type="submit"><?= $isNew ? 'เพิ่มชุด' : 'บันทึก' ?></button><a class="btn btn-ghost btn-lg" href="<?= e(url('/admin/costumes')) ?>">ยกเลิก</a></div>
</form>

<?php if (!$isNew): ?>
<aside class="stack" style="gap:14px">
  <section class="box" id="images" aria-labelledby="h-img">
    <div class="box-head"><h2 id="h-img">รูป (<?= count($images) ?>)</h2></div>
    <?php if (!$images): ?><p class="small muted">ยังไม่มีรูป ชุดที่ไม่มีรูปจะแสดงเป็นภาพว่างในหน้าร้าน</p><?php endif; ?>
    <div class="imgs">
      <?php foreach ($images as $i => $img): ?>
      <figure>
        <?php if ($i === 0): ?><span class="cover-tag">ปก</span><?php endif; ?>
        <img src="<?= e(thumb($img['path'])) ?>" alt="รูปที่ <?= $i + 1 ?>" loading="lazy">
        <figcaption>
          <?php if ($i > 0): ?><form method="post" action="<?= e(url('/admin/costumes/' . $c['id'] . '/images/' . $img['id'] . '/cover')) ?>"><?= csrf_field() ?><button class="btn btn-xs btn-ghost" type="submit">ตั้งเป็นปก</button></form><?php else: ?><span></span><?php endif; ?>
          <form method="post" action="<?= e(url('/admin/costumes/' . $c['id'] . '/images/' . $img['id'] . '/delete')) ?>" data-confirm="ลบรูปนี้?"><?= csrf_field() ?><button class="iconbtn" type="submit" aria-label="ลบรูปที่ <?= $i + 1 ?>" style="width:32px;height:32px"><?= icon('trash-2', 'ic ic-sm') ?></button></form>
        </figcaption>
      </figure>
      <?php endforeach; ?>
    </div>
    <form method="post" action="<?= e(url('/admin/costumes/' . $c['id'] . '/images')) ?>" enctype="multipart/form-data" style="margin-top:12px" class="stack">
      <?= csrf_field() ?>
      <label class="label" for="more-images">เพิ่มรูป</label>
      <input class="input" id="more-images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required>
      <label class="sr-only" for="more-credit">ที่มาของภาพ</label>
      <input class="input" id="more-credit" name="credit" maxlength="255" placeholder="ที่มาของภาพ (ถ้าไม่ได้ถ่ายเอง)">
      <button class="btn btn-ghost btn-sm" type="submit"><?= icon('upload', 'ic ic-sm') ?>อัปโหลด</button>
    </form>
  </section>
  <section class="box" aria-labelledby="h-up">
    <div class="box-head"><h2 id="h-up">คิวของชุดนี้</h2><a href="<?= e(url('/admin/bookings', ['q' => $c['code']])) ?>">ประวัติทั้งหมด</a></div>
    <?php if (!$upcoming): ?><p class="small muted">ไม่มีการจองที่ค้างอยู่</p><?php endif; ?>
    <ul class="rows">
      <?php foreach ($upcoming as $b): ?>
      <li style="grid-template-columns:1fr auto"><a href="<?= e(url('/admin/bookings/' . $b['id'])) ?>"><b><?= e($b['nickname'] ?: $b['first_name']) ?></b><div class="sub"><?= e(thai_date($b['pickup_date'], false, false)) ?> – <?= e(thai_date($b['return_date'], false, false)) ?></div></a><?= Status::badge(Status::BOOKING, $b['status']) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
</aside>
<?php endif; ?>
</div>
