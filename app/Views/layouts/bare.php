<?php include __DIR__ . '/head.php'; ?>
<body>
<header class="topbar"><div class="col"><a class="wordmark" href="<?= e(url('/')) ?>"><?= e(setting('shop_name')) ?></a></div></header>
<main id="main"><?= $content ?></main>
</body>
</html>
