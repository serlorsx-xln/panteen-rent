<?php
/** @var list<array> $addresses the customer's saved addresses @var bool $staff counter booking */
use App\Core\View;
use App\Services\Shipping;

$staff ??= false;
$on = Shipping::enabled();
$delivery = (string) old('delivery_method', 'pickup');
$back = (string) old('return_method', 'store');
$sel = (string) old('address_id', $addresses ? (string) $addresses[0]['id'] : 'new');
$m = money(setting('ship_fee_metro'), false);
$u = money(setting('ship_fee_upcountry'), false);
$way = static function (string $name, string $value, string $current, string $title, string $text, string $fee, string $extra = ''): string {
    return '<label class="way"><input type="radio" name="' . $name . '" value="' . e($value) . '"' . ($current === $value ? ' checked' : '') . $extra . '>'
        . '<span class="way-body"><b>' . e($title) . '</b><small>' . e($text) . '</small></span><span class="way-fee">' . e($fee) . '</span></label>';
};
?>
<section class="panel" aria-labelledby="h-ship" data-ship>
  <div class="panel-title"><?= icon('truck') ?><h2 id="h-ship">รับและคืนชุด</h2></div>
  <?php if (!$on): ?>
  <p class="muted small">ตอนนี้ร้านรับและคืนชุดที่ร้านเท่านั้น</p>
  <input type="hidden" name="delivery_method" value="pickup"><input type="hidden" name="return_method" value="store">
  <?php else: ?>
  <fieldset class="ways">
    <legend class="label">รับชุด</legend>
    <?= $way('delivery_method', 'pickup', $delivery, 'รับที่ร้าน', 'ลองชุดและตรวจสภาพพร้อมพนักงานก่อนรับไป', 'ฟรี') ?>
    <?= $way('delivery_method', 'post', $delivery, 'ส่งพัสดุถึงบ้าน', 'กรุงเทพฯ และปริมณฑลขนส่ง ' . (int) setting('ship_days_metro') . ' วัน · ต่างจังหวัด ' . (int) setting('ship_days_upcountry') . ' วัน · ร้านส่งออกก่อน ' . setting('ship_cutoff') . ' น. ให้ถึงภายในวันรับชุด', $m . ' / ' . $u . ' บาท') ?>
    <?php if (Shipping::messengerEnabled()): ?>
    <?= $way('delivery_method', 'messenger', $delivery, 'เมสเซนเจอร์ส่งด่วน', 'เฉพาะ' . Shipping::ZONE['metro'] . ' ส่งถึงในวันรับชุด', money(setting('messenger_fee'))) ?>
    <?php endif; ?>
  </fieldset>
  <?= field_error('delivery_method') ?>
  <fieldset class="ways">
    <legend class="label">คืนชุด</legend>
    <?= $way('return_method', 'store', $back, 'คืนที่ร้าน', 'ตรวจสภาพต่อหน้า รับเงินมัดจำคืนทันที', 'ฟรี') ?>
    <?php if (Shipping::returnPostEnabled()): ?>
    <?= $way('return_method', 'post', $back, 'ส่งพัสดุคืน', 'ร้านแนบใบปะหน้าจ่ายค่าส่งแล้วไว้ในกล่อง ฝากส่งภายในวันคืนชุด เงินมัดจำโอนคืนหลังร้านตรวจสภาพ', $m . ' / ' . $u . ' บาท') ?>
    <?php endif; ?>
  </fieldset>
  <?= field_error('return_method') ?>

  <div class="ship-to" data-ship-address>
    <h3 class="label">ที่อยู่<?= $staff ? 'ลูกค้า' : '' ?>สำหรับส่งและรับพัสดุ</h3>
    <?php if ($addresses || $staff): ?>
    <div class="ways" data-saved-addresses>
      <?php foreach ($addresses as $a): ?>
      <label class="way"><input type="radio" name="address_id" value="<?= (int) $a['id'] ?>" data-province="<?= e($a['province']) ?>" <?= $sel === (string) $a['id'] ? 'checked' : '' ?>>
        <span class="way-body"><b><?= e(($a['label'] ? $a['label'] . ' · ' : '') . $a['recipient'] . ' · ' . phone_fmt($a['phone'])) ?></b><small><?= e(str_replace("\n", ' · ', Shipping::addressText($a))) ?></small></span>
        <span class="way-fee"><?= e(Shipping::ZONE[Shipping::zone($a['province']) ?? 'upcountry']) ?></span></label>
      <?php endforeach; ?>
      <label class="way"><input type="radio" name="address_id" value="new" <?= $sel === 'new' ? 'checked' : '' ?>><span class="way-body"><b>ส่งที่อยู่อื่น</b><small>กรอกที่อยู่ใหม่ด้านล่าง</small></span></label>
    </div>
    <?= field_error('address_id') ?>
    <?php else: ?>
    <input type="hidden" name="address_id" value="new">
    <?php endif; ?>
    <div class="new-address" data-new-address>
      <?php View::partial('address-fields', ['a' => []]); ?>
      <label class="check" style="margin-top:12px"><input type="checkbox" name="save_address" value="1" <?= old('save_address', $staff ? '' : '1') ? 'checked' : '' ?>><span>บันทึกที่อยู่นี้ไว้ใน<?= $staff ? 'สมุดที่อยู่ของลูกค้า' : 'บัญชีของฉัน' ?>สำหรับครั้งต่อไป</span></label>
    </div>
  </div>
  <?php endif; ?>
</section>
