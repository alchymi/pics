<?php
// public/index.php
$dir = __DIR__ . '/pictures';
$base = '/pictures'; // chemin web
$files = [];
if (is_dir($dir)) {
  foreach (scandir($dir) as $f) {
    if ($f[0] === '.') continue;
    $path = $dir . '/' . $f;
    if (is_file($path) && preg_match('~\.(jpe?g|png|gif|webp)$~i', $f)) {
      $files[] = [
        'name' => $f,
        'url'  => $base . '/' . $f,
        'ts'   => filemtime($path) ?: 0,
        'size' => filesize($path) ?: 0,
      ];
    }
  }
}
usort($files, fn($a,$b) => $b['ts'] <=> $a['ts']); // plus récent d’abord
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>pics.funtools.cloud – Gallery</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Masonry avec colonnes */
    .masonry { column-gap: 1rem; }
    .masonry-item { break-inside: avoid; margin-bottom: 1rem; }
    /* Lightbox plein écran */
    #lightbox {
      position: fixed; inset: 0; display: none; align-items: center; justify-content: center;
      background: rgba(0,0,0,.9); z-index: 50;
    }
    #lightbox.open { display: flex; }
    #lightbox img { max-width: 95vw; max-height: 90vh; }
  </style>
</head>
<body class="bg-zinc-950 text-zinc-100">
  <header class="sticky top-0 z-40 bg-zinc-900/80 backdrop-blur border-b border-zinc-800">
    <div class="mx-auto max-w-7xl px-4 py-3 flex items-center gap-3">
      <h1 class="text-xl font-semibold">pics.funtools.cloud</h1>
      <span class="text-sm text-zinc-400">— <?= count($files) ?> images</span>
      <div class="ml-auto flex items-center gap-2">
        <a href="/upload.php" class="text-xs px-3 py-1.5 rounded bg-emerald-600 hover:bg-emerald-500">Upload API</a>
        <button id="copyIndex" class="text-xs px-3 py-1.5 rounded bg-zinc-700 hover:bg-zinc-600">Copy page URL</button>
      </div>
    </div>
  </header>

  <main class="mx-auto max-w-7xl px-4 py-6">
    <!-- Masonry responsive -->
    <div class="masonry columns-1 sm:columns-2 md:columns-3 lg:columns-4">
      <?php foreach ($files as $img): ?>
        <figure class="masonry-item group overflow-hidden rounded-xl bg-zinc-900 border border-zinc-800">
          <img
            src="<?= htmlspecialchars($img['url']) ?>"
            alt="<?= htmlspecialchars($img['name']) ?>"
            loading="lazy"
            class="w-full h-auto transition-transform duration-300 ease-out group-hover:scale-[1.02] cursor-zoom-in"
            onclick="openLightbox('<?= htmlspecialchars($img['url']) ?>', '<?= htmlspecialchars($img['name']) ?>')"
          >
          <figcaption class="px-3 py-2 text-xs text-zinc-400 flex items-center justify-between">
            <span class="truncate" title="<?= htmlspecialchars($img['name']) ?>"><?= htmlspecialchars($img['name']) ?></span>
            <button
              class="shrink-0 ml-2 rounded bg-zinc-800 hover:bg-zinc-700 px-2 py-1"
              onclick="copyToClipboard(location.origin + '<?= htmlspecialchars($img['url']) ?>')"
              title="Copier l’URL">
              Copy
            </button>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>

    <?php if (!count($files)): ?>
      <p class="text-zinc-400 text-sm">Aucune image pour le moment. Utilise l’API <code class="px-1 py-0.5 bg-zinc-800 rounded">/upload.php</code>.</p>
    <?php endif; ?>
  </main>

  <!-- Lightbox -->
  <div id="lightbox" onclick="closeLightbox()" aria-hidden="true">
    <img id="lightbox-img" src="" alt="">
  </div>

  <script>
    const lb = document.getElementById('lightbox');
    const lbImg = document.getElementById('lightbox-img');

    function openLightbox(src, alt) {
      lbImg.src = src;
      lbImg.alt = alt || '';
      lb.classList.add('open');
    }
    function closeLightbox() {
      lb.classList.remove('open');
      lbImg.src = '';
    }
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && lb.classList.contains('open')) closeLightbox();
    });

    function copyToClipboard(text) {
      navigator.clipboard.writeText(text).then(() => {
        alert('URL copiée');
      }).catch(() => {});
    }
    document.getElementById('copyIndex')?.addEventListener('click', () => copyToClipboard(location.href));
  </script>
</body>
</html>
