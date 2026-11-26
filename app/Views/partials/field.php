<?php
/**
 * Variabel: $key, $label, $type, $value, $error, $auto, opsional $hint, $attrs, $optional, $options untuk select.
 * $strong: batas panjang password baru. $again: nama kolom ulangi password. $match: kolom yang harus sama. $money: rupiah.
 */
$auto ??= 'off';
$described = $error ? "$key-error" : (isset($hint) ? "$key-hint" : '');
$common = 'id="' . e($key) . '" name="' . e($key) . '" autocomplete="' . e($auto) . '"' . (empty($optional) ? ' required ' : ' ') . ($attrs ?? '')
    . ($error ? ' aria-invalid="true"' : '') . ($described ? ' aria-describedby="' . e($described) . '"' : '')
    . ($type === 'password' ? ' data-password' : '') . (isset($strong) ? ' data-strong data-min="' . (int) $strong . '"' : '')
    . (isset($again) ? ' data-again="' . e($again) . '"' : '') . (isset($match) ? ' data-match="' . e($match) . '"' : '')
    . ($type === 'password' && !isset($strong) && !isset($match) ? ' data-novalid' : '');
$lead = ['email' => 'mail', 'password' => 'lock'][$type] ?? ($key === 'name' ? 'user' : null);
?>
<div class="field">
  <div class="label-row"><label for="<?= e($key) ?>"><?= e($label) ?></label><?php if (isset($strong)): ?><button class="text-btn" type="button" data-generate="<?= e($key) ?>"><?= icon('spark') ?>Buatkan</button><?php endif ?></div>
  <div class="input-wrap<?= $lead ? ' with-icon' : '' ?><?= !empty($money) ? ' money' : '' ?>">
    <?php if ($type === 'textarea'): ?>
    <textarea <?= $common ?>>
<?= e($value ?? '') ?></textarea>
    <?php elseif ($type === 'select'): ?>
    <select <?= $common ?>><?php foreach ($options as $option => $text): ?><option value="<?= e($option) ?>"<?= (string) $option === (string) ($value ?? '') ? ' selected' : '' ?>><?= e($text) ?></option><?php endforeach ?></select>
    <?php else: ?>
    <?php if ($lead) echo icon($lead) ?><?php if (!empty($money)): ?><span class="unit">Rp</span><?php endif ?>
    <input type="<?= !empty($money) ? 'text' : e($type) ?>" value="<?= e($value ?? '') ?>"<?= !empty($money) ? ' data-money' : '' ?> <?= $common ?>>
    <?php endif ?>
    <?php if ($type === 'password'): ?><button class="reveal" type="button" data-reveal="<?= e($key) ?>" aria-pressed="false" aria-label="Lihat password"><?= icon('eye') ?><?= icon('eyeoff') ?></button><?php elseif (!in_array($type, ['select', 'textarea', 'datetime-local'], true)): ?><span class="tick"><?= icon('check') ?></span><?php endif ?>
  </div>
  <?php if ($type === 'password'): ?><p class="caps" data-caps hidden><?= icon('alert') ?>Caps Lock aktif</p><?php endif ?>
  <?php if (isset($strong)): ?>
  <div class="strength" data-strength data-score="-1" aria-live="polite">
    <div class="bars"><i></i><i></i><i></i><i></i></div><span data-level>Belum diisi</span>
    <ul><?php foreach (['length' => "Minimal $strong karakter", 'mix' => 'Huruf besar, kecil, angka, atau simbol (minimal tiga)', 'pattern' => 'Tanpa pola seperti 12345 atau qwerty', 'personal' => 'Tanpa nama atau email kamu'] as $rule => $text): ?><li data-rule="<?= $rule ?>"><?= icon('check') ?><?= e($text) ?></li><?php endforeach ?></ul>
  </div>
  <?php endif ?>
  <?php if ($error): ?><p class="error" id="<?= e($key) ?>-error"><?= e($error) ?></p><?php endif ?>
  <?php if (isset($hint)): ?><p class="hint" id="<?= e($key) ?>-hint"<?= $error ? ' hidden' : '' ?>><?= e($hint) ?></p><?php endif ?>
</div>
