<div class="page-head"><div><h1>Admin</h1><p>Ringkasan platform.</p></div></div>
<div class="stats">
  <div class="card stat"><span>Freelancer</span><strong><?= (int) $counts['total'] ?></strong></div>
  <div class="card stat"><span>Aktif</span><strong><?= (int) $counts['active'] ?></strong></div>
  <div class="card stat"><span>Diblokir</span><strong><?= (int) $counts['blocked'] ?></strong></div>
</div>
<div class="actions">
<a class="btn primary" href="/admin/campaign">Kelola campaign</a>
<a class="btn outline" href="/admin/klik">Log klik</a>

</div>
