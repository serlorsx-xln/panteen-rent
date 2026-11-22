<?php
/** @var array $b @var array $types @var array $photos @var list<string> $accessories @var ?array $parcel @var string $lateFrom @var bool $fixedDate */
use App\Core\View;
use App\Services\Inspection;
use App\Services\Shipping;

$byArea = [];
foreach ($types as $t) {
    $byArea[$t['area']][] = $t;
}
$value = $b['replacement_value'] !== null ? (float) $b['replacement_value'] : null;
$accType = null;
foreach ($types as $t) {
    if ($t['area'] === Inspection::ACCESSORY_AREA && $t['fee_type'] === 'fixed') { $accType = $t; break; }
}
$rows = 8;
$reference = array_intersect_key($photos, array_flip(['handover', 'arrival']));
?>
<nav class="crumbs"><a href="<?= e(url('/admin/bookings/' . $b['id'])) ?>"><?= icon('arrow-left', 'ic ic-sm') ?> การจอง <?= e($b['booking_no']) ?></a></nav>
<div class="page-head">
  <div>
    <h1>ตรวจสภาพชุดคืน</h1>
    <p class="muted"><?= e($b['costume_code']) ?> · <?= e($b['costume_name']) ?> · <?= e(full_name($b)) ?></p>
  </div>
</div>

<form method="post" action="<?= e(url('/admin/bookings/' . $b['id'] . '/inspect')) ?>" enctype="multipart/form-data" class="detail" data-inspect
      data-due="<?= e($b['return_date']) ?>" data-fee="<?= e((float) $b['late_fee_per_day']) ?>" data-deposit="<?= e((float) $b['deposit_amount']) ?>"
      data-value="<?= e($value ?? '') ?>" data-acc="<?= e($accType ? (float) $accType['amount'] : 0) ?>"
      data-confirm="ปิดการเช่าตามผลตรวจนี้? ระบบจะคืนเงินมัดจำตามยอดที่คำนวณและส่งใบสรุปให้ลูกค้า">
  <?= csrf_field() ?>
  <div>
    <?php if (form_errors()): ?><p class="form-error" role="alert" style="margin-bottom:14px"><?= icon('circle-alert') ?><span><?= e(form_errors()['form'] ?? 'ตรวจข้อมูลอีกครั้ง') ?></span></p><?php endif; ?>

    <section class="box" aria-labelledby="h-ref">
      <div class="box-head"><h2 id="h-ref">ก่อนตรวจ: สภาพตอนส่งมอบ</h2></div>
      <?php if ($b['arrival_note']): ?><p class="small late">ลูกค้าแจ้งตอนได้รับ: <?= e($b['arrival_note']) ?></p><?php endif; ?>
      <?php if (array_filter($reference)): View::partial('photo-strip', ['photos' => $reference]); else: ?>
      <p class="small muted">ไม่มีรูปตอนส่งมอบ ถ้าพบร่องรอยที่ไม่แน่ใจว่าเกิดตอนเช่า ให้เขียนไว้ในหมายเหตุแทนการคิดเงิน</p>
      <?php endif; ?>
    </section>

    <fieldset class="box" style="margin-top:14px">
      <legend class="box-legend">1. เช็กลิสต์ <span class="small muted">ติ๊กจุดที่ปกติ</span></legend>
      <div class="checkgrid">
        <?php foreach (Inspection::CHECKS as $k => $label): ?>
        <label class="check"><input type="checkbox" name="checks[]" value="<?= e($k) ?>" data-check><span><?= e($label) ?></span></label>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-ghost btn-sm" type="button" data-check-all style="margin-top:10px"><?= icon('check', 'ic ic-sm') ?>ปกติทุกจุด</button>
      <?= field_error('checks') ?>
    </fieldset>

    <?php if ($accessories): ?>
    <fieldset class="box" style="margin-top:14px">
      <legend class="box-legend">2. ของที่มากับชุด <span class="small muted">ติ๊กชิ้นที่ไม่ได้คืน</span></legend>
      <div class="checkgrid">
        <?php foreach ($accessories as $a): ?>
        <label class="check"><input type="checkbox" name="missing[]" value="<?= e($a) ?>" data-missing><span><?= e($a) ?> หาย</span></label>
        <?php endforeach; ?>
      </div>
      <?php if ($accType): ?><p class="hint" style="margin-top:8px">คิดชิ้นละ <?= e(money($accType['amount'])) ?> ตามตาราง “<?= e($accType['name']) ?>”</p><?php endif; ?>
    </fieldset>
    <?php endif; ?>

    <fieldset class="box" style="margin-top:14px">
      <legend class="box-legend"><?= $accessories ? '3' : '2' ?>. ความเสียหาย <span class="small muted">เลือกจากตารางค่าเสียหายของร้าน</span></legend>
      <?php if ($value === null): ?><p class="small late" style="margin-bottom:8px">ชุดนี้ยังไม่ได้ตั้งมูลค่าชุด รายการแบบร้อยละต้องใส่จำนวนเงินเอง และไม่มีเพดานค่าเสียหาย</p><?php endif; ?>
      <div class="tablewrap" style="border:0">
      <table class="table dmg-rows">
        <thead><tr><th scope="col">รายการ</th><th scope="col" style="width:76px">จำนวน</th><th scope="col" style="width:120px">บาท <span class="faint">(แก้ได้)</span></th><th scope="col">ตำแหน่ง / รายละเอียด</th></tr></thead>
        <tbody>
          <?php for ($i = 0; $i < $rows; $i++): ?>
          <tr data-dmg-row>
            <td><label class="sr-only" for="dt<?= $i ?>">รายการที่ <?= $i + 1 ?></label>
              <select class="select" id="dt<?= $i ?>" name="rows[<?= $i ?>][type_id]" data-dmg-type>
                <option value="">—</option>
                <?php foreach ($byArea as $area => $list): ?>
                <optgroup label="<?= e($area) ?>">
                  <?php foreach ($list as $t): $unit = Inspection::unitPrice($t, $value); ?>
                  <option value="<?= (int) $t['id'] ?>" data-unit="<?= e($unit ?? '') ?>"><?= e($t['name']) ?> · <?= $unit === null ? 'ใส่เงินเอง' : e(money($unit, false)) ?><?= $t['fee_type'] === 'percent' ? ' (' . e((float) $t['amount']) . '% ของมูลค่า)' : '' ?></option>
                  <?php endforeach; ?>
                </optgroup>
                <?php endforeach; ?>
              </select></td>
            <td><label class="sr-only" for="dq<?= $i ?>">จำนวน</label><input class="input num" id="dq<?= $i ?>" name="rows[<?= $i ?>][qty]" type="number" min="1" max="99" value="1" data-dmg-qty></td>
            <td><label class="sr-only" for="da<?= $i ?>">จำนวนเงิน</label><input class="input num" id="da<?= $i ?>" name="rows[<?= $i ?>][amount]" inputmode="decimal" placeholder="ตามตาราง" data-dmg-amount></td>
            <td><label class="sr-only" for="dn<?= $i ?>">รายละเอียด</label><input class="input" id="dn<?= $i ?>" name="rows[<?= $i ?>][note]" maxlength="255" placeholder="เช่น ชายกระโปรงด้านหลังซ้าย"></td>
          </tr>
          <?php endfor; ?>
        </tbody>
      </table>
      </div>
      <button class="btn btn-ghost btn-sm" type="button" data-dmg-more hidden style="margin-top:8px"><?= icon('plus', 'ic ic-sm') ?>เพิ่มรายการ</button>
      <?= field_error('damage') ?>
      <p class="hint" style="margin-top:8px">ราคาในตารางแก้ได้ที่ <a href="<?= e(url('/admin/damage-types')) ?>" target="_blank">ตารางค่าเสียหาย</a> · ไม่ต้องคิดจุดที่ลูกค้าแจ้งไว้ตอนได้รับหรือมีในรูปตอนส่งมอบ</p>
    </fieldset>

    <fieldset class="box" style="margin-top:14px">
      <legend class="box-legend">สภาพชุดหลังตรวจ</legend>
      <div class="ways">
        <?php foreach (Inspection::OUTCOME as $k => $label): ?>
        <label class="way"><input type="radio" name="outcome" value="<?= e($k) ?>" <?= $k === 'ready' ? 'checked' : '' ?>><span class="way-body"><b><?= e($label) ?></b><small><?= ['ready' => 'ซักแล้วกลับขึ้นราวให้เช่าได้ตามปกติ', 'maintenance' => 'ซ่อมก่อน ชุดจะไม่แสดงให้จองจนกว่าจะเปลี่ยนสถานะกลับ', 'retired' => 'เลิกให้เช่าชุดนี้'][$k] ?></small></span></label>
        <?php endforeach; ?>
      </div>
      <div class="field" style="margin-top:12px"><label class="label" for="i-note">หมายเหตุการตรวจ <span class="opt">(จำเป็นถ้ามีจุดไม่ผ่านแต่ไม่คิดเงิน)</span></label>
        <textarea class="textarea" id="i-note" name="note" maxlength="300" rows="2" placeholder="เช่น รอยเล็กที่ชายเป็นรอยเดิมตามรูปตอนส่งมอบ ไม่คิดเงิน"></textarea></div>
      <div style="margin-top:12px"><?php View::partial('photo-input', ['id' => 'inspect-photos', 'label' => 'รูปตอนตรวจคืน', 'hint' => 'ถ่ายทุกจุดที่คิดค่าเสียหาย ลูกค้าจะเห็นรูปเหล่านี้']); ?></div>
    </fieldset>
  </div>

  <aside class="actions" aria-label="สรุปยอด">
    <div class="action" style="position:sticky;top:16px">
      <h3>สรุปการคืน</h3>
      <dl class="kv">
        <dt>ส่งคืนทาง</dt><dd><?= $parcel ? e(Shipping::carrierName($parcel['carrier'])) : 'ที่ร้าน' ?></dd>
        <?php if ($parcel): ?><dt>พัสดุ</dt><dd><?= $parcel['status'] === 'lost' ? '<span class="late">สูญหาย</span>' : 'ได้รับ ' . e(thai_datetime((string) $parcel['arrived_at'])) ?></dd><?php endif; ?>
      </dl>
      <div class="field" style="margin-top:10px">
        <label class="label" for="i-date"><?= $fixedDate ? 'วันฝากส่ง (ใช้คิดคืนช้า)' : 'วันที่คืนจริง' ?></label>
        <input class="input" id="i-date" type="date" name="returned_on" value="<?= e($lateFrom) ?>" min="<?= e(substr((string) $b['picked_up_at'], 0, 10)) ?>" max="<?= e(today()) ?>" <?= $fixedDate ? 'readonly aria-describedby="i-date-h"' : '' ?> data-insp-date>
        <?php if ($fixedDate): ?><span class="hint" id="i-date-h">แก้ได้ตอนกด “ได้รับพัสดุคืน”</span><?php endif; ?>
      </div>
      <dl class="kv" style="margin-top:12px" aria-live="polite">
        <dt>คืนช้า</dt><dd data-rp-late></dd>
        <dt>ค่าเสียหาย</dt><dd data-rp-damage></dd>
        <dt>เงินมัดจำ</dt><dd><?= e(money($b['deposit_amount'])) ?></dd>
        <dt>คืนมัดจำ</dt><dd data-rp-refund></dd>
        <dt data-rp-extra-row>เก็บเพิ่ม</dt><dd data-rp-extra-row data-rp-extra></dd>
      </dl>
      <p class="hint" data-rp-cap hidden style="margin-top:6px">ค่าเสียหายเกินมูลค่าชุด <?= $value !== null ? e(money($value)) : '' ?> คิดเท่ามูลค่าชุด</p>
      <button class="btn btn-block" type="submit" style="margin-top:14px"><?= icon('clipboard-check') ?>ปิดการเช่าและส่งใบสรุป</button>
      <p class="hint" style="margin-top:8px">ลูกค้าโต้แย้งได้ภายใน <?= (int) setting('dispute_days') ?> วันหลังตรวจ</p>
    </div>
  </aside>
</form>
