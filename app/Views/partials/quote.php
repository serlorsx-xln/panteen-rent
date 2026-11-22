<?php
/** @var array $q @var array $c @var string $pickup @var string $return @var ?array $plan */
use App\Services\Shipping;

$plan ??= null;
?>
<div class="strip">
  <div class="strip-row">
    <span>ค่าเช่า <?= (int) $q['base_days'] ?> วันแรก<small><?= e(thai_date($pickup, false, false)) ?> – <?= e(thai_date($return, false, false)) ?> · รวม <?= (int) $q['days'] ?> วัน</small></span>
    <span><?= e(money($q['rent'])) ?></span>
  </div>
  <?php if ($q['extra_days'] > 0): ?>
  <div class="strip-row">
    <span>วันเพิ่ม <?= (int) $q['extra_days'] ?> วัน<small><?= e(money($c['extra_day_price'])) ?> ต่อวัน</small></span>
    <span><?= e(money($q['extra'])) ?></span>
  </div>
  <?php endif; ?>
  <?php if ($q['delivery_fee'] > 0): ?>
  <div class="strip-row">
    <span>ค่าจัดส่ง<small><?= e(Shipping::DELIVERY[$plan['delivery'] ?? 'post'] . (($plan['zone'] ?? null) ? ' · ' . Shipping::ZONE[$plan['zone']] : '')) ?></small></span>
    <span><?= e(money($q['delivery_fee'])) ?></span>
  </div>
  <?php endif; ?>
  <?php if ($q['return_fee'] > 0): ?>
  <div class="strip-row">
    <span>ค่าส่งคืน<small>ร้านแนบใบปะหน้าจ่ายค่าส่งแล้วไว้ในกล่อง</small></span>
    <span><?= e(money($q['return_fee'])) ?></span>
  </div>
  <?php endif; ?>
  <div class="strip-row">
    <span>เงินมัดจำ<small>ได้คืนเมื่อคืนชุดตรงเวลาและสภาพสมบูรณ์</small></span>
    <span><?= e(money($q['deposit'])) ?></span>
  </div>
  <div class="strip-row strip-total">
    <span>ชำระตอนจอง</span>
    <span class="num"><?= e(money($q['total'], false)) ?> <small>บาท</small></span>
  </div>
  <?php if (!empty($plan['ship_by'])): ?>
  <div class="strip-row strip-note">
    <span>ร้านส่งออก<small><?= $plan['delivery'] === 'messenger' ? 'เมสเซนเจอร์ถึงในวันเดียวกัน' : 'ขนส่ง ' . (int) $plan['out_days'] . ' วัน ถึงภายใน ' . e(thai_date($pickup, false, false)) ?></small></span>
    <span><?= e(thai_date($plan['ship_by'], false, false)) ?></span>
  </div>
  <?php endif; ?>
  <?php if (($plan['return'] ?? 'store') === 'post'): ?>
  <div class="strip-row strip-note">
    <span>ฝากส่งคืน<small>นับวันที่ฝากส่งเป็นวันคืน</small></span>
    <span>ภายใน <?= e(thai_date($return, false, false)) ?></span>
  </div>
  <?php endif; ?>
  <div class="strip-row strip-note">
    <span>คืนช้า<small>หักจากเงินมัดจำ</small></span>
    <span><?= e(money($q['late_fee_per_day'])) ?> ต่อวัน</span>
  </div>
</div>
