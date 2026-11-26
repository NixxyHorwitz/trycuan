<div class="page-head"><h1><?= e($title) ?></h1></div>
<div class="docs">
  <aside class="docs-nav"><details open><summary><?= icon('list') ?>Daftar isi<?= icon('down') ?></summary><nav aria-label="Daftar isi"><?php foreach ($toc as $t): ?><a href="#<?= e($t['id']) ?>"><?= e($t['text']) ?></a><?php endforeach ?></nav></details></aside>
  <article class="sheet-doc"><div class="markdown"><?= $html ?></div></article>
</div>
