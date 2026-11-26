<?php /** Variabel: $user, $tab */ ?>
<section class="profile-head">
  <div class="cover"></div>
  <div class="who"><?= avatar($user, 'xl') ?><div><h1><?= e($user['name']) ?></h1><p><?= e($user['email']) ?></p></div><span class="tag on hidden sm:inline-flex">Bergabung <?= e(date('d/m/Y', strtotime($user['created_at']))) ?></span></div>
  <nav class="tabs" aria-label="Pengaturan profil"><a href="/profil"<?= $tab === 'profil' ? ' aria-current="page"' : '' ?>><?= icon('user') ?>Profil</a><a href="/profil/keamanan"<?= $tab === 'keamanan' ? ' aria-current="page"' : '' ?>><?= icon('lock') ?>Keamanan</a></nav>
</section>
