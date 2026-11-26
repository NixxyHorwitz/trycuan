<?php
use App\Models\CampaignDoc;
$id = (int) $campaign['id'];
$field = static function (string $key, string $label, string $type, array $more = []) use ($errors, $old): void {
    $value = $old[$key] ?? '';
    $error = $errors[$key] ?? null;
    extract($more, EXTR_SKIP);
    require ROOT . '/app/Views/partials/field.php';
};
?>
<div class="page-head"><a class="back" href="/admin/campaign/<?= $id ?>/dokumen"><?= icon('back') ?>Materi dan panduan</a><h1><?= e($title) ?></h1><p><?= e($campaign['title']) ?></p></div>
<form class="card" method="post" action="<?= $doc ? '/admin/dokumen/' . (int) $doc['id'] : "/admin/campaign/$id/dokumen/baru" ?>" data-once="Menyimpan...">
  <?= csrf_field() ?>
  <input type="hidden" name="tindakan" value="simpan">
  <?php $field('title', 'Judul', 'text', ['attrs' => 'maxlength="120" minlength="3"']) ?>
  <div class="pair"><?php $field('kind', 'Jenis', 'select', ['options' => CampaignDoc::KINDS]); $field('status', 'Status', 'select', ['options' => ['published' => 'Terbit', 'draft' => 'Draf']]) ?></div>
  <div class="editor" data-editor data-preview="/admin/dokumen/pratinjau">
    <div class="editor-bar" role="toolbar" aria-label="Format">
      <?php foreach (['## ' => ['Judul', 'heading'], '**' => ['Tebal', 'bold'], '- ' => ['Daftar', 'list'], '1. ' => ['Bernomor', 'numbers'], '- [ ] ' => ['Centang', 'check'], '> ' => ['Catatan', 'quote'], '```' => ['Teks siap salin', 'copy'], '[]' => ['Link', 'link']] as $mark => [$name, $ic]): ?><button class="icon-btn" type="button" data-mark="<?= e($mark) ?>" aria-label="<?= $name ?>" title="<?= $name ?>"><?= icon($ic) ?></button><?php endforeach ?>
    </div>
    <div class="editor-panes">
      <div class="field"><label class="sr" for="body">Isi dokumen</label><textarea id="body" name="body" rows="20" maxlength="60000" required<?= !empty($errors['body']) ? ' aria-invalid="true"' : '' ?>>
<?= e($old['body']) ?></textarea><?php if (!empty($errors['body'])): ?><p class="error"><?= e($errors['body']) ?></p><?php endif ?></div>
      <article class="markdown" data-preview-out aria-label="Pratinjau"><p class="hint">Pratinjau muncul di sini.</p></article>
    </div>
  </div>
  <?php if ($files): ?><details><summary>Sisipkan berkas<?= icon('down') ?></summary><div class="tags"><?php foreach ($files as $f): $image = !str_ends_with($f['file'], 'pdf'); ?><button class="chip" type="button" data-insert="<?= e(($image ? '!' : '') . '[' . $f['name'] . '](berkas:' . $f['id'] . ')') ?>"><?= e($f['name']) ?></button><?php endforeach ?></div></details><?php endif ?>
  <div class="save-bar"><a class="btn outline" href="/admin/campaign/<?= $id ?>/dokumen">Batal</a><button class="btn primary" type="submit">Simpan</button></div>
</form>
