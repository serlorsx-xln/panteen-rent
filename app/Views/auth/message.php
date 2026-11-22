<div class="col col-narrow">
  <div class="auth">
    <div class="auth-card">
      <h1><?= e($heading) ?></h1>
      <p class="lead"><?= e($text) ?></p>
      <?php if (!empty($action)): ?><a class="btn" href="<?= e(url($action[1])) ?>"><?= e($action[0]) ?></a><?php endif; ?>
    </div>
  </div>
</div>
