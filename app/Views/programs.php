<div class="page-head"><h1><?= e($title) ?></h1><p>Fee dihitung dari total views semua utas yang disetujui di akunmu.</p></div>
<?php if (!$campaigns): ?><section class="empty"><?= icon('campaign') ?><h2>Belum ada campaign</h2><p>Campaign yang dibuka akan muncul di sini.</p></section><?php endif ?>
<div class="cards"><?php foreach ($campaigns as $c) require ROOT . '/app/Views/partials/program_card.php' ?></div>
