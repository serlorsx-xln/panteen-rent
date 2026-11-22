<?php
/** A member's delivery addresses. @var list<array> $list @var ?array $edit */
use App\Core\View;
use App\Services\Addresses;
use App\Services\Shipping;

$full = count($list) >= Addresses::MAX && !$edit;
?>
<div class="col">
  <div class="page-head">
    <h1>ที่อยู่จัดส่ง</h1>
    <p>ใช้ตอนเลือกให้ร้านส่งชุดถึงบ้านหรือส่งชุดคืนทางพัสดุ เก็บได้ <?= Addresses::MAX ?> ที่อยู่ แก้ไขที่นี่ไม่เปลี่ยนที่อยู่ของการจองที่ทำไปแล้ว</p>
  </div>
  <?php View::partial('acct-nav'); ?>
  <div class="addr-split">
    <section aria-labelledby="h-list">
      <h2 class="sr-only" id="h-list">ที่อยู่ที่บันทึกไว้</h2>
      <?php if (!$list): ?>
      <div class="empty" style="padding-top:24px"><?= icon('map-pin', 'ic ic-lg') ?><h2>ยังไม่มีที่อยู่</h2><p>เพิ่มไว้ก่อน หรือกรอกตอนจองชุดก็ได้</p></div>
      <?php endif; ?>
      <ul class="addr-list">
        <?php foreach ($list as $a): ?>
        <li class="addr<?= (int) $a['is_default'] ? ' is-default' : '' ?>">
          <div>
            <p class="addr-head"><b><?= e($a['label'] ?: $a['recipient']) ?></b><?= (int) $a['is_default'] ? ' <span class="status status-ok">ที่อยู่หลัก</span>' : '' ?></p>
            <p class="small"><?= e($a['recipient']) ?> · <span class="num"><?= e(phone_fmt($a['phone'])) ?></span></p>
            <p class="small muted"><?= nl2br(e(Shipping::addressText($a))) ?></p>
            <p class="small faint"><?= e(Shipping::ZONE[Shipping::zone($a['province']) ?? 'upcountry']) ?> · ค่าส่งเที่ยวละ <?= e(money(setting(Shipping::zone($a['province']) === 'metro' ? 'ship_fee_metro' : 'ship_fee_upcountry'))) ?></p>
          </div>
          <div class="addr-act">
            <a class="btn btn-xs btn-ghost" href="<?= e(url('/account/addresses', ['edit' => $a['id']])) ?>#form"><?= icon('pencil', 'ic ic-sm') ?>แก้ไข<span class="sr-only"> <?= e($a['label'] ?: $a['recipient']) ?></span></a>
            <?php if (!(int) $a['is_default']): ?>
            <form method="post" action="<?= e(url('/account/addresses/' . $a['id'] . '/default')) ?>"><?= csrf_field() ?><button class="btn btn-xs btn-ghost" type="submit">ตั้งเป็นหลัก</button></form>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/account/addresses/' . $a['id'] . '/delete')) ?>" data-confirm="ลบที่อยู่นี้?"><?= csrf_field() ?><button class="btn btn-xs btn-ghost" type="submit">ลบ<span class="sr-only"> <?= e($a['label'] ?: $a['recipient']) ?></span></button></form>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <section class="panel" id="form" aria-labelledby="h-form">
      <div class="panel-title"><?= icon('map-pin') ?><h2 id="h-form"><?= $edit ? 'แก้ไขที่อยู่' : 'เพิ่มที่อยู่' ?></h2></div>
      <?php if ($full): ?>
      <p class="muted">เก็บครบ <?= Addresses::MAX ?> ที่อยู่แล้ว ลบที่อยู่ที่ไม่ใช้ก่อนเพิ่มใหม่</p>
      <?php else: ?>
      <?php if (form_errors()['form'] ?? null): ?><p class="form-error" role="alert"><?= icon('circle-alert') ?><span><?= e(form_errors()['form']) ?></span></p><?php endif; ?>
      <form method="post" action="<?= e(url('/account/addresses')) ?>" novalidate>
        <?= csrf_field() ?>
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <?php View::partial('address-fields', ['a' => $edit ?? [], 'withLabel' => true]); ?>
        <label class="check" style="margin-top:12px"><input type="checkbox" name="is_default" value="1" <?= $edit && (int) $edit['is_default'] ? 'checked' : '' ?>><span>ใช้เป็นที่อยู่หลัก</span></label>
        <div class="row" style="margin-top:16px">
          <button class="btn" type="submit"><?= $edit ? 'บันทึก' : 'เพิ่มที่อยู่' ?></button>
          <?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(url('/account/addresses')) ?>">ยกเลิก</a><?php endif; ?>
        </div>
      </form>
      <?php endif; ?>
    </section>
  </div>
</div>
