<?php /** @var array $pg @var callable $make */
if ($pg['pages'] > 1): ?>
<nav class="pager" aria-label="เลขหน้า">
  <?php if ($pg['page'] > 1): ?><a href="<?= e($make($pg['page'] - 1)) ?>" aria-label="หน้าก่อน"><?= icon('chevron-left', 'ic ic-sm') ?></a><?php endif; ?>
  <?php for ($i = max(1, $pg['page'] - 3); $i <= min($pg['pages'], $pg['page'] + 3); $i++): ?>
    <?php if ($i === $pg['page']): ?><span aria-current="page" class="num"><?= $i ?></span><?php else: ?><a class="num" href="<?= e($make($i)) ?>"><?= $i ?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if ($pg['page'] < $pg['pages']): ?><a href="<?= e($make($pg['page'] + 1)) ?>" aria-label="หน้าถัดไป"><?= icon('chevron-right', 'ic ic-sm') ?></a><?php endif; ?>
</nav>
<?php endif;
