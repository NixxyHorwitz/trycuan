<?php
$link = static function (array $set) use ($query, $status, $sort): string {
    $params = array_filter($set + ['cari' => $query, 'status' => $status, 'urut' => $sort], static fn ($v): bool => $v !== '' && $v !== 1);
    return $params ? '?' . http_build_query($params) : '';
};
?>
<div class="page-head row"><div><h1>Freelancer</h1><p><?= angka($total) ?> user</p></div><a class="btn outline" href="/admin/user/ekspor<?= e($link([])) ?>" data-turbo="false"><?= icon('download') ?>Ekspor CSV</a></div>
<form class="bare toolbar" method="get" action="/admin/user" role="search">
  <div class="search"><?= icon('search') ?><input name="cari" type="search" value="<?= e($query) ?>" placeholder="Nama, email, atau username" maxlength="80" aria-label="Cari freelancer"></div>
  <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif ?>
  <select name="urut" data-submit aria-label="Urutkan" class="min-h-11! w-auto!"><?php foreach (['' => 'Terbaru', 'lama' => 'Terlama', 'saldo' => 'Saldo terbesar', 'views' => 'Views terbanyak'] as $key => $label): ?><option value="<?= $key ?>"<?= $sort === $key ? ' selected' : '' ?>><?= $label ?></option><?php endforeach ?></select>
  <button class="btn outline" type="submit">Cari</button>
</form>
<div class="chips"><?php foreach (['' => ['Semua', 'total'], 'active' => ['Aktif', 'active'], 'blocked' => ['Diblokir', 'blocked']] as $key => [$label, $count]): ?><a class="chip" href="/admin/user<?= e($link(['status' => $key])) ?>"<?= $status === $key ? ' aria-current="true"' : '' ?>><?= $label ?> <b><?= (int) $counts[$count] ?></b></a><?php endforeach ?></div>
<?php if (!$users): ?><section class="empty"><?= icon('users') ?><p>Tidak ada user yang cocok.</p></section><?php else: ?>
<section class="card py-0!">
  <div class="table-wrap"><table class="fold"><thead><tr><th>Freelancer</th><th>Threads</th><th class="right">Views</th><th class="right">Saldo</th><th>Daftar</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($users as $u): ?><tr>
      <td><a class="entry" href="/admin/user/<?= (int) $u['id'] ?>"><?= avatar_of($u['id'], $u['name']) ?><span><strong class="block font-medium hover:text-accent"><?= e($u['name']) ?></strong><span class="hint block"><?= e($u['email']) ?></span></span></a></td>
      <td data-label="Threads"><?= $u['username'] ? '@' . e($u['username']) : '<span class="hint">Belum ada</span>' ?></td>
      <td class="right" data-label="Views"><?= angka($u['views']) ?></td><td class="right" data-label="Saldo"><?= e(rupiah((int) $u['balance'])) ?></td>
      <td data-label="Daftar"><?= e(date('d/m/Y', strtotime($u['created_at']))) ?></td>
      <td data-label="Status"><span class="tag<?= $u['status'] === 'blocked' ? ' fail' : ' good' ?>"><?= $u['status'] === 'blocked' ? 'Diblokir' : 'Aktif' ?></span></td>
    </tr><?php endforeach ?>
  </tbody></table></div>
</section>
<?php endif ?>
<?php if ($pages > 1): ?><nav class="pager" aria-label="Halaman">
  <?php if ($page > 1): ?><a class="btn outline" href="/admin/user<?= e($link(['halaman' => $page - 1])) ?>">Sebelumnya</a><?php else: ?><span></span><?php endif ?>
  <span class="hint"><?= $page ?> dari <?= $pages ?></span>
  <?php if ($page < $pages): ?><a class="btn outline" href="/admin/user<?= e($link(['halaman' => $page + 1])) ?>">Berikutnya</a><?php else: ?><span></span><?php endif ?>
</nav><?php endif ?>
