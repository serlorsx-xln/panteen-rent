<?php
/** @var string $id @var string $label @var string $hint @var bool $required */
$required ??= false;
?>
<label class="drop drop-sm" data-drop>
  <input type="file" id="<?= e($id) ?>" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple <?= $required ? 'required' : '' ?> aria-describedby="<?= e($id) ?>-hint" <?= invalid_attr('photos') ?>>
  <?= icon('camera', 'ic ic-lg') ?>
  <b data-drop-label><?= e($label) ?></b>
  <span class="small muted" id="<?= e($id) ?>-hint"><?= e($hint) ?> · ไม่เกิน <?= \App\Services\Photos::MAX_PER_UPLOAD ?> รูป รูปละไม่เกิน 5 MB</span>
</label>
<?= field_error('photos') ?>
