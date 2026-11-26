<?php
/** Variabel: $user */
$adminArea = App\Core\Area::isAdmin();
$links = $adminArea
    ? [['/admin/akun-saya', 'user', 'Akun saya'], ['/admin/pengaturan', 'settings', 'Pengaturan'], ['/admin/tim', 'shield', 'Tim admin']]
    : [['/profil', 'user', 'Profil'], ['/profil/keamanan', 'lock', 'Keamanan'], ['/saldo', 'wallet', 'Saldo dan rekening'], ['/panduan', 'book', 'Panduan']];
if (!$adminArea && setting('contact_url') !== '') $links[] = [setting('contact_url'), 'send', 'Bantuan'];
?>
<?php /* Tidak digambar ulang saat pindah halaman; ID berubah bila avatar, nama, atau token berubah. */ ?>
<details class="menu" id="menu-<?= hash('crc32b', $user['name'] . $user['avatar_style'] . $user['avatar_seed'] . App\Core\Csrf::token() . setting('contact_url')) ?>" data-turbo-permanent>
  <summary aria-label="Menu akun"><?= avatar($user) ?></summary>
  <div class="menu-panel">
    <div class="menu-head"><?= avatar($user) ?><div><strong><?= e($user['name']) ?></strong><span><?= e($user['email']) ?></span></div></div>
    <?php foreach ($links as [$href, $ic, $label]): ?><a href="<?= e($href) ?>"<?= str_starts_with($href, 'https:') ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= icon($ic) ?><?= e($label) ?></a><?php endforeach ?>
    <form class="bare" method="post" action="<?= $adminArea ? '/admin/keluar' : '/keluar' ?>" data-turbo="false"><?= csrf_field() ?><button type="submit"><?= icon('logout') ?>Keluar</button></form>
  </div>
</details>
