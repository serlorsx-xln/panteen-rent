<?php
/** @var array $items @var ?array $editing */
$f = $editing ?? ['id' => null, 'name' => '', 'slug' => '', 'color' => '#F2D36B', 'description' => '', 'sort_order' => count($items) + 1, 'is_active' => 1];
$v = fn($k) => old($k, $f[$k] ?? '');
?>
<div class="page-head">
  <div><h1>หมวดชุด</h1><p class="muted">หมวดคือเมนูหลักของหน้าร้าน ลูกค้ากดเพื่อดูชุดเฉพาะหมวด</p></div>
</div>
<div class="formgrid">
  <div class="tablewrap">
    <table class="table">
      <thead><tr><th>หมวด</th><th class="r">ชุดที่ใช้อยู่</th><th class="r">ถูกเช่า</th><th class="c">ลำดับ</th><th>หน้าร้าน</th><th><span class="sr-only">จัดการ</span></th></tr></thead>
      <tbody>
        <?php foreach ($items as $c): ?>
        <tr>
          <td><div class="cell-main"><span class="swatch" style="background:<?= e($c['color']) ?>"></span><span><b><?= e($c['name']) ?></b><small class="num">/<?= e($c['slug']) ?></small></span></div></td>
          <td class="r num"><a href="<?= e(url('/admin/costumes', ['category' => $c['id']])) ?>"><?= (int) $c['costume_count'] ?></a></td>
          <td class="r num"><?= (int) $c['rentals'] ?></td>
          <td class="c num"><?= (int) $c['sort_order'] ?></td>
          <td><?= $c['is_active'] ? '<span class="status status-ok">แสดง</span>' : '<span class="status status-off">ซ่อน</span>' ?></td>
          <td class="r nowrap">
            <a class="btn btn-xs btn-ghost" href="<?= e(url('/admin/categories', ['edit' => $c['id']])) ?>#form">แก้ไข</a>
            <?php if ((int) $c['all_count'] === 0): ?>
            <form method="post" action="<?= e(url('/admin/categories/' . $c['id'] . '/delete')) ?>" style="display:inline" data-confirm="ลบหมวด “<?= e($c['name']) ?>”?"><?= csrf_field() ?><button class="btn btn-xs btn-ghost" type="submit">ลบ</button></form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <form class="box" id="form" method="post" action="<?= e(url($f['id'] ? '/admin/categories/' . $f['id'] : '/admin/categories')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="box-head"><h2><?= $f['id'] ? 'แก้ไข ' . e($f['name']) : 'เพิ่มหมวดใหม่' ?></h2><?php if ($f['id']): ?><a href="<?= e(url('/admin/categories')) ?>">ยกเลิก</a><?php endif; ?></div>
    <div class="field"><label class="label" for="name">ชื่อหมวด</label><input class="input" id="name" name="name" value="<?= e($v('name')) ?>" maxlength="100" required <?= invalid_attr('name') ?>><?= field_error('name') ?></div>
    <div class="field" style="margin-top:12px"><label class="label" for="slug">ชื่อในลิงก์</label><input class="input num" id="slug" name="slug" value="<?= e($v('slug')) ?>" maxlength="100" required pattern="[a-z0-9-]+" <?= invalid_attr('slug') ?>><?= field_error('slug') ?: '<span class="hint">ภาษาอังกฤษตัวเล็ก ตัวเลข และ - เช่น graduation</span>' ?></div>
    <div class="field" style="margin-top:12px"><label class="label" for="color">สีประจำหมวด</label>
      <div class="color-input"><input type="color" id="color" name="color" value="<?= e($v('color')) ?>" <?= invalid_attr('color') ?>><span class="hint">ใช้แยกหมวดในตารางชุดและรายงานของหลังร้าน เลือกสีที่ต่างจากหมวดอื่นชัด ๆ</span></div><?= field_error('color') ?></div>
    <div class="field" style="margin-top:12px"><label class="label" for="description">คำอธิบาย <span class="opt">(ถ้ามี)</span></label><textarea class="input" id="description" name="description" rows="2" maxlength="500"><?= e($v('description')) ?></textarea></div>
    <div class="field" style="margin-top:12px"><label class="label" for="sort_order">ลำดับ</label><input class="input num" id="sort_order" name="sort_order" inputmode="numeric" value="<?= e((string) $v('sort_order')) ?>" style="max-width:100px"></div>
    <label class="check" style="margin-top:12px"><input type="checkbox" name="is_active" value="1" <?= $v('is_active') ? 'checked' : '' ?>><span>แสดงหมวดนี้ในหน้าร้าน</span></label>
    <button class="btn btn-block" type="submit" style="margin-top:16px"><?= $f['id'] ? 'บันทึก' : 'เพิ่มหมวด' ?></button>
  </form>
</div>
