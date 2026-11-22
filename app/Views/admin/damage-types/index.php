<?php
/** @var array $types */
use App\Services\Inspection;

$edit = null;
foreach ($types as $t) {
    if ((int) $t['id'] === input_int('edit')) { $edit = $t; }
}
$v = static fn(string $k, mixed $d = '') => old($k, $edit[$k] ?? $d);
?>
<div class="page-head">
  <div><h1>ตารางค่าเสียหาย</h1><p class="muted">ราคามาตรฐานที่ใช้ตอนตรวจสภาพชุดคืน ลูกค้าเห็นรายการและราคาในใบสรุปการคืน แบบร้อยละคิดจากมูลค่าชุดที่ตั้งไว้ในหน้าแก้ไขชุด</p></div>
</div>
<div class="detail">
  <div class="tablewrap">
    <table class="table">
      <thead><tr><th scope="col">รายการ</th><th scope="col">หมวด</th><th scope="col" class="r">ราคา</th><th scope="col">ใช้อยู่</th><th scope="col"><span class="sr-only">แก้ไข</span></th></tr></thead>
      <tbody>
        <?php foreach ($types as $t): ?>
        <tr<?= (int) $t['is_active'] ? '' : ' class="faint"' ?>>
          <td><b><?= e($t['name']) ?></b><?= $t['hint'] ? '<br><small class="faint">' . e($t['hint']) . '</small>' : '' ?></td>
          <td class="small"><?= e($t['area']) ?></td>
          <td class="r num nowrap"><?= $t['fee_type'] === 'percent' ? e((float) $t['amount']) . '% ของมูลค่า' : e(money($t['amount'])) ?></td>
          <td><?= (int) $t['is_active'] ? 'ใช้' : 'ปิด' ?></td>
          <td class="r"><a class="btn btn-xs btn-ghost" href="<?= e(url('/admin/damage-types', ['edit' => $t['id']])) ?>#form"><?= icon('pencil', 'ic ic-sm') ?>แก้ไข<span class="sr-only"> <?= e($t['name']) ?></span></a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <form class="action" id="form" method="post" action="<?= e(url('/admin/damage-types')) ?>">
    <?= csrf_field() ?>
    <h2><?= $edit ? 'แก้ไขรายการ' : 'เพิ่มรายการ' ?></h2>
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="field"><label class="label" for="dt-name">ชื่อรายการ</label><input class="input" id="dt-name" name="name" maxlength="120" required value="<?= e($v('name')) ?>" <?= invalid_attr('name') ?>><?= field_error('name') ?></div>
    <div class="field"><label class="label" for="dt-area">หมวด</label><select class="select" id="dt-area" name="area" <?= invalid_attr('area') ?>><?php foreach (Inspection::AREAS as $a): ?><option <?= $v('area') === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select><?= field_error('area') ?></div>
    <div class="grid-2 keep">
      <div class="field"><label class="label" for="dt-type">คิดแบบ</label><select class="select" id="dt-type" name="fee_type"><option value="fixed" <?= $v('fee_type', 'fixed') === 'fixed' ? 'selected' : '' ?>>บาท</option><option value="percent" <?= $v('fee_type') === 'percent' ? 'selected' : '' ?>>% ของมูลค่าชุด</option></select></div>
      <div class="field"><label class="label" for="dt-amount">จำนวน</label><input class="input num" id="dt-amount" name="amount" inputmode="decimal" required value="<?= e($v('amount')) ?>" <?= invalid_attr('amount') ?>><?= field_error('amount') ?></div>
    </div>
    <div class="field"><label class="label" for="dt-hint">คำอธิบาย <span class="opt">(ไม่บังคับ)</span></label><input class="input" id="dt-hint" name="hint" maxlength="200" value="<?= e($v('hint')) ?>"></div>
    <label class="check" style="margin-top:10px"><input type="checkbox" name="is_active" value="1" <?= (int) $v('is_active', 1) ? 'checked' : '' ?>><span>ใช้ในหน้าตรวจสภาพ</span></label>
    <button class="btn btn-block" type="submit" style="margin-top:12px"><?= $edit ? 'บันทึก' : 'เพิ่มรายการ' ?></button>
    <?php if ($edit): ?><a class="btn btn-ghost btn-block" href="<?= e(url('/admin/damage-types')) ?>" style="margin-top:6px">ยกเลิกการแก้ไข</a><?php endif; ?>
  </form>
</div>
