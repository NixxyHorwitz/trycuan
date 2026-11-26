<?php /** Variabel: $googleText */ if (App\Services\GoogleLogin::enabled()): ?>
<a class="btn outline large block google" href="/masuk/google" data-turbo="false"><?php require ROOT . '/app/Views/partials/google_mark.php' ?><?= e($googleText) ?></a>
<p class="or"><span>atau pakai email</span></p>
<?php endif ?>
