<?php
/** @var array $a current values (an addresses row, or empty) @var bool $withLabel ask for a short name (address book only) */
use App\Services\Shipping;

$withLabel ??= false;
$v = static fn(string $k): string => (string) old('addr_' . $k, $a[$k] ?? '');
$f = static function (string $k, string $label, string $attrs = '', string $hint = '') use ($v): string {
    return '<div class="field"><label class="label" for="addr_' . $k . '">' . $label . '</label>'
        . '<input class="input' . (str_contains($attrs, 'inputmode') ? ' num' : '') . '" id="addr_' . $k . '" name="addr_' . $k . '" value="' . e($v($k)) . '" ' . $attrs . invalid_attr('addr_' . $k) . '>'
        . (field_error('addr_' . $k) ?: ($hint ? '<span class="hint">' . e($hint) . '</span>' : '')) . '</div>';
};
?>
<div class="addr-fields">
  <?php if ($withLabel): ?>
  <?= $f('label', 'ชื่อเรียกที่อยู่ <span class="opt">(ไม่บังคับ)</span>', 'maxlength="40" placeholder="เช่น บ้าน หอพัก ที่ทำงาน"') ?>
  <?php endif; ?>
  <div class="grid-2">
    <?= $f('recipient', 'ชื่อผู้รับ', 'maxlength="150" autocomplete="name"') ?>
    <?= $f('phone', 'เบอร์โทรผู้รับ', 'type="tel" inputmode="tel" maxlength="20" autocomplete="tel"', 'ขนส่งโทรหาเบอร์นี้ก่อนส่ง') ?>
  </div>
  <?= $f('line1', 'บ้านเลขที่ อาคาร ห้อง ซอย ถนน', 'maxlength="255" autocomplete="address-line1"') ?>
  <div class="grid-2">
    <?= $f('subdistrict', 'แขวง / ตำบล', 'maxlength="100"') ?>
    <?= $f('district', 'เขต / อำเภอ', 'maxlength="100"') ?>
  </div>
  <div class="grid-2">
    <div class="field">
      <label class="label" for="addr_province">จังหวัด</label>
      <select class="select" id="addr_province" name="addr_province" autocomplete="address-level1" data-province <?= invalid_attr('addr_province') ?>>
        <option value="">เลือกจังหวัด</option>
        <?php foreach (Shipping::PROVINCES as $p): ?><option value="<?= e($p) ?>" <?= $v('province') === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
      </select>
      <?= field_error('addr_province') ?>
    </div>
    <?= $f('postcode', 'รหัสไปรษณีย์', 'inputmode="numeric" maxlength="5" pattern="[0-9]{5}" autocomplete="postal-code"') ?>
  </div>
  <?= $f('note', 'จุดสังเกตสำหรับคนส่งของ <span class="opt">(ไม่บังคับ)</span>', 'maxlength="255" placeholder="เช่น ฝากป้อม รปภ. ได้ ตึกสีขาวตรงข้ามเซเว่น"') ?>
</div>
