<?php
use App\Models\PayoutMethod;
use App\Models\Setting;
$old += ['amount' => (string) $balance];
$field = static function (string $key, string $label, string $type, array $more = []) use ($errors, $old): void {
    $value = $type === 'password' ? '' : ($old[$key] ?? '');
    $error = $errors[$key] ?? null;
    extract($more, EXTR_SKIP);
    require ROOT . '/app/Views/partials/field.php';
};
$minimum = Setting::int('min_withdrawal');
$methodErrors = array_intersect_key($errors, array_flip(['provider', 'account_name', 'account_no', 'password']));
?>
<div class="page-head"><h1>Saldo</h1><p>Fee masuk setelah masa tahan dan pemeriksaan admin.</p></div>
<div class="split">
  <section class="hero-card">
    <div><p class="hint">Bisa ditarik</p><p class="amount"><?= e(rupiah($balance)) ?></p></div>
    <?php if ($pending): ?>
    <p class="hint">Penarikan <?= e(rupiah((int) $pending['amount'])) ?> ke <?= e(PayoutMethod::PROVIDERS[$pending['provider']] ?? $pending['provider']) ?> sedang diproses.</p>
    <?php if (!empty($errors['amount'])): ?><p class="notice bad mb-0!"><?= e($errors['amount']) ?></p><?php endif ?>
    <?php elseif (!$method): ?><p class="hint">Isi rekening pencairan untuk mulai menarik saldo.</p>
    <?php elseif ($balance < $minimum && empty($errors['amount'])): ?><div class="stack gap-2!"><progress max="<?= $minimum ?>" value="<?= max(0, $balance) ?>" aria-label="Menuju minimum penarikan"></progress><p class="hint">Minimum penarikan <?= e(rupiah($minimum)) ?>, kurang <?= e(rupiah($minimum - $balance)) ?>.</p></div>
    <?php else: ?>
    <?php if ($needsRefresh): ?><p class="hint">Views belum terbaru. <a class="underline" href="/dashboard">Refresh di beranda</a> sebelum menarik.</p><?php endif ?>
    <div><button class="btn primary large" type="button" data-open="withdraw"><?= icon('up') ?>Tarik saldo</button></div>
    <?php endif ?>
  </section>
  <section class="card">
    <h2>Rekening pencairan</h2>
    <?php if ($method): ?><p><strong><?= e(PayoutMethod::PROVIDERS[$method['provider']] ?? $method['provider']) ?></strong> <?= e(PayoutMethod::masked($method)) ?></p><p class="hint"><?= e($method['account_name']) ?></p><?php endif ?>
    <details class="mt-3"<?= !$method || $methodErrors ? ' open' : '' ?>>
      <summary><?= $method ? 'Ganti rekening' : 'Isi rekening' ?><?= icon('down') ?></summary>
      <form method="post" action="/saldo/rekening" data-once>
        <?= csrf_field() ?>
        <?php
        $field('provider', 'Bank atau e-wallet', 'select', ['options' => ['' => 'Pilih'] + PayoutMethod::PROVIDERS]);
        $field('account_name', 'Nama pemilik', 'text', ['attrs' => 'minlength="2" maxlength="80"', 'auto' => 'name']);
        $field('account_no', 'Nomor rekening atau HP', 'text', ['attrs' => 'maxlength="26" inputmode="numeric" data-pattern="^[\d\s.\-]{6,26}$" data-pattern-text="Nomor hanya angka, minimal 6 digit."', 'hint' => 'Disimpan terenkripsi.']);
        $field('password', 'Password akun', 'password', ['auto' => 'current-password']);
        ?>
        <button class="btn primary" type="submit">Simpan rekening</button>
      </form>
    </details>
  </section>
</div>
<?php if (!$pending && $method && ($balance >= $minimum || !empty($errors['amount']))): ?>
<?php if (!empty($errors['amount'])): ?><div class="notice bad gap" role="alert"><?= icon('alert') ?><span><?= e($errors['amount']) ?></span></div><?php endif ?>
<dialog class="modal" id="withdraw" aria-label="Tarik saldo">
  <h2>Tarik saldo</h2>
  <p class="hint mb-4">Ke <?= e(PayoutMethod::PROVIDERS[$method['provider']] ?? $method['provider']) ?> <?= e(PayoutMethod::masked($method)) ?>, <?= e($method['account_name']) ?>. Saldo <?= e(rupiah($balance)) ?>.</p>
  <form method="post" action="/saldo/tarik" data-once="Mengajukan...">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <?php $field('amount', 'Nominal', 'text', ['money' => true, 'hint' => 'Minimum ' . rupiah($minimum), 'attrs' => 'data-minimum="' . $minimum . '" data-maximum="' . $balance . '" inputmode="numeric"']) ?>
    <div class="actions"><button class="btn outline" type="button" data-close>Batal</button><button class="btn primary" type="submit">Ajukan penarikan</button></div>
  </form>
</dialog>
<?php endif ?>
<section class="card gap" data-filter-group>
  <div class="card-head"><h2>Riwayat</h2></div>
  <?php if (!$entries): ?><p class="hint">Belum ada mutasi.</p><?php else: ?>
  <div class="chips mx-0! px-0!"><?php foreach (['' => 'Semua', 'fee' => 'Fee', 'withdrawal' => 'Penarikan', 'refund' => 'Dikembalikan'] as $key => $label): ?><button class="chip" type="button" data-filter="<?= $key ?>" aria-pressed="<?= $key === '' ? 'true' : 'false' ?>"><?= $label ?></button><?php endforeach ?></div>
  <p class="hint" data-none hidden>Tidak ada mutasi jenis ini.</p>
  <ul class="list">
    <?php foreach ($entries as $row): $wd = $row['withdrawal_status']; ?>
    <li data-item="<?= e($row['type']) ?>">
      <div class="entry"><span class="bubble <?= $row['amount'] > 0 ? 'in' : 'out' ?>"><?= icon($row['amount'] > 0 ? 'in' : 'up') ?></span><div><strong class="font-medium"><?= ['fee' => 'Fee campaign', 'withdrawal' => 'Penarikan', 'refund' => 'Penarikan dikembalikan'][$row['type']] ?></strong>
        <p class="hint"><?= e(date('d/m/Y H:i', strtotime($row['created_at']))) ?> WIB<?= $row['title'] ? ', ' . e($row['title']) : '' ?><?= $row['type'] === 'refund' && $row['reason'] ? '. Alasan: ' . e($row['reason']) : '' ?></p></div></div>
      <div class="meta">
        <?php if ($row['type'] === 'withdrawal'): ?><span class="tag<?= ['paid' => ' good', 'rejected' => ' fail'][$wd] ?? ' wait' ?>"><?= ['pending' => 'Diproses', 'paid' => 'Dibayar', 'rejected' => 'Ditolak'][$wd] ?></span><?php endif ?>
        <?php if ($row['type'] === 'withdrawal' && $row['proof_file']): ?><a href="/penarikan/<?= (int) $row['withdrawal_id'] ?>/bukti" target="_blank" rel="noopener">Bukti</a><?php endif ?>
        <strong class="<?= $row['amount'] > 0 ? 'ok' : '' ?>"><?= $row['amount'] > 0 ? '+' : '-' ?><?= e(rupiah(abs((int) $row['amount']))) ?></strong>
      </div>
    </li>
    <?php endforeach ?>
  </ul>
  <?php endif ?>
</section>
