<!doctype html>
<html lang="id" data-theme="<?= theme() ?>" data-accent="<?= e(setting('accent')) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="<?= theme() === 'dark' ? '#0F0F12' : '#F6F6F7' ?>">
  <meta name="turbo-cache-control" content="<?= !empty($noCache) ? 'no-cache' : 'no-preview' ?>">
  <title><?= e(App\Services\Seo::title($title)) ?></title>
<?= App\Services\Seo::tags($title, $seo ?? null) ?>
  <link rel="icon" href="<?= e(App\Services\SiteImage::url(setting('site_logo')) ?: '/assets/favicon.svg') ?>">
  <link rel="apple-touch-icon" href="/assets/icon-192.png">
  <link rel="manifest" href="/site.webmanifest">
  <?php foreach ([400, 500, 600] as $weight): ?><link rel="preload" href="/assets/build/font-poppins-latin-<?= $weight ?>-normal.woff2" as="font" type="font/woff2" crossorigin>
  <?php endforeach ?>
  <link rel="stylesheet" href="<?= e(asset('build/app.css')) ?>">
  <?php if (empty($plain)): ?><script type="module" src="<?= e(asset('build/app.js')) ?>" data-turbo-track="reload"></script><?php endif ?>
</head>
