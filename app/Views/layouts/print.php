<?php include __DIR__ . '/head.php'; ?>
<body class="print-page">
<nav class="print-bar no-print" aria-label="เครื่องมือเอกสาร">
  <button class="btn btn-sm" type="button" data-print><?= icon('printer', 'ic ic-sm') ?>พิมพ์</button>
  <a class="btn btn-sm btn-ghost" href="javascript:history.back()"><?= icon('arrow-left', 'ic ic-sm') ?>กลับ</a>
</nav>
<main id="main"><?= $content ?></main>
</body>
</html>
