<div class="page-head"><h1>Pengumuman</h1><p>Tampil di lonceng dan beranda freelancer.</p></div>
<div class="split">
  <section class="card">
    <h2>Terbit</h2>
    <?php if (!$rows): ?><p class="hint">Belum ada pengumuman.</p><?php endif ?>
    <ul class="list"><?php foreach ($rows as $r): $live = $r['status'] === 'published'; ?>
      <li><div><div class="tags mb-1"><span class="tag<?= $live ? ' good' : '' ?>"><?= $live ? 'Terbit' : 'Arsip' ?></span><?php if ($r['level'] === 'penting'): ?><span class="tag wait">Penting</span><?php endif ?></div><strong><?= e($r['title']) ?></strong><p class="line-clamp-2 text-soft"><?= e($r['body']) ?></p><p class="hint"><?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?>, <?= e($r['name'] ?? 'Admin') ?></p></div>
      <form class="bare" method="post" action="/admin/pengumuman/<?= (int) $r['id'] ?>"><?= csrf_field() ?><input type="hidden" name="tindakan" value="<?= $live ? 'arsipkan' : 'terbitkan' ?>"><button class="btn outline small" type="submit"><?= $live ? 'Arsipkan' : 'Terbitkan lagi' ?></button></form></li>
    <?php endforeach ?></ul>
  </section>
  <form class="card" method="post" action="/admin/pengumuman" data-once="Menerbitkan...">
    <h2>Buat pengumuman</h2>
    <?= csrf_field() ?>
    <?php foreach ([['title', 'Judul', 'text', 'maxlength="120"'], ['body', 'Isi', 'textarea', 'rows="5" maxlength="2000"'], ['level', 'Jenis', 'select', '']] as [$k, $l, $t, $at])
        (function () use ($errors, $old, $k, $l, $t, $at) { $key = $k; $label = $l; $type = $t; $attrs = $at; $options = ['info' => 'Info', 'penting' => 'Penting']; $value = $old[$k] ?? ($k === 'level' ? 'info' : ''); $error = $errors[$k] ?? null; require ROOT . '/app/Views/partials/field.php'; })(); ?>
    <button class="btn primary" type="submit"><?= icon('send') ?>Terbitkan</button>
  </form>
</div>
