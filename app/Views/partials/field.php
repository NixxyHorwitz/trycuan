<?php
/** Variabel: $key, $label, $type, $value, $error, $auto, opsional $hint, $attrs, $optional, dan $options untuk select */
$auto ??= 'off';
$described = $error ? "$key-error" : (isset($hint) ? "$key-hint" : '');
$common = 'id="' . e($key) . '" name="' . e($key) . '" autocomplete="' . e($auto) . '"' . (empty($optional) ? ' required ' : ' ') . ($attrs ?? '')
    . ($error ? ' aria-invalid="true"' : '') . ($described ? ' aria-describedby="' . e($described) . '"' : '');
?>
<div class="field">
  <label for="<?= e($key) ?>"><?= e($label) ?></label>
  <div class="input-wrap">
    <?php if ($type === 'textarea'): ?>
    <textarea <?= $common ?>>
<?= e($value ?? '') ?></textarea>
    <?php elseif ($type === 'select'): ?>
    <select <?= $common ?>><?php foreach ($options as $option => $text): ?><option value="<?= e($option) ?>"<?= $option === ($value ?? '') ? ' selected' : '' ?>><?= e($text) ?></option><?php endforeach ?></select>
    <?php else: ?>
    <input type="<?= e($type) ?>" value="<?= e($value ?? '') ?>" <?= $common ?>>
    <?php endif ?>
    <?php if ($type === 'password'): ?><button class="reveal" type="button" data-reveal="<?= e($key) ?>" aria-pressed="false">Lihat</button><?php endif ?>
  </div>
  <?php if ($error): ?><p class="error" id="<?= e($key) ?>-error"><?= e($error) ?></p>
  <?php elseif (isset($hint)): ?><p class="hint" id="<?= e($key) ?>-hint"><?= e($hint) ?></p><?php endif ?>
</div>
