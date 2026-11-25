<?php require ROOT . '/app/Views/partials/head.php' ?>
<body>
  <header class="topbar">
    <a class="brand" href="/"><span class="mark"><?= icon('trend') ?></span>TryCuan</a>
    <nav aria-label="Akun">
      <?php if ($user): ?>
        <a class="btn primary" href="/dashboard">Dashboard</a>
      <?php else: ?>
        <a class="btn ghost" href="/masuk">Masuk</a>
        <a class="btn primary" href="/daftar">Daftar</a>
      <?php endif ?>
    </nav>
  </header>
  <main class="public"><?= $content ?></main>
</body>
</html>
