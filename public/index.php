<?php
// public/index.php
header('X-Content-Type-Options: nosniff');

$dir  = __DIR__ . '/pictures';
$base = '/pictures';

$all = [];
if (is_dir($dir)) {
  foreach (scandir($dir) as $f) {
    if ($f[0] === '.') continue;
    $path = $dir . '/' . $f;
    if (is_file($path) && preg_match('~\.(jpe?g|png|gif|webp)$~i', $f)) {
      $all[] = [
        'name' => $f,
        'url'  => $base . '/' . rawurlencode($f),
        'ts'   => filemtime($path) ?: 0,
        'size' => filesize($path) ?: 0,
      ];
    }
  }
}
usort($all, fn($a,$b) => $b['ts'] <=> $a['ts']); // plus récent d’abord

// --- API JSON pour lazy load ---
$mode   = $_GET['mode']   ?? '';
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit  = min(200, max(1, (int)($_GET['limit'] ?? 40))); // sécurité

if ($mode === 'json') {
  $slice = array_slice($all, $offset, $limit);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'total'  => count($all),
    'offset' => $offset,
    'limit'  => $limit,
    'items'  => $slice,
    'next'   => ($offset + $limit < count($all)) ? ($offset + $limit) : null,
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

// --- Page HTML initiale (premier batch) ---
$initialLimit = 40;
$initial = array_slice($all, 0, $initialLimit);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>pics.funtools.cloud – Gallery</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .masonry { column-gap: 1rem; }
    .masonry-item { break-inside: avoid; margin-bottom: 1rem; }
    #lightbox { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(0,0,0,.9); z-index: 50; }
    #lightbox.open { display: flex; }
    #lightbox img { max-width: 95vw; max-height: 90vh; }
  </style>
</head>
<body class="bg-zinc-950 text-zinc-100">
  <header class="sticky top-0 z-40 bg-zinc-900/80 backdrop-blur border-b border-zinc-800">
    <div class="mx-auto max-w-7xl px-4 py-3 flex items-center gap-3">
      <h1 class="text-xl font-semibold">pics.funtools.cloud</h1>
      <span class="text-sm text-zinc-400">— <?= count($all) ?> images</span>
      <div class="ml-auto">
        <button id="copyIndex" class="text-xs px-3 py-1.5 rounded bg-zinc-700 hover:bg-zinc-600">Copy page URL</button>
      </div>
    </div>
  </header>

  <main class="mx-auto max-w-7xl px-4 py-6">
    <div id="grid" class="masonry columns-1 sm:columns-2 md:columns-3 lg:columns-4">
      <?php foreach ($initial as $img): ?>
        <figure class="masonry-item group overflow-hidden rounded-xl bg-zinc-900 border border-zinc-800">
          <img
            src="<?= htmlspecialchars($img['url']) ?>"
            alt="<?= htmlspecialchars($img['name']) ?>"
            loading="lazy" decoding="async"
            class="w-full h-auto transition-transform duration-300 ease-out group-hover:scale-[1.02] cursor-zoom-in"
            onclick="openLightbox('<?= htmlspecialchars($img['url']) ?>','<?= htmlspecialchars($img['name']) ?>')"
          >
          <figcaption class="px-3 py-2 text-xs text-zinc-400 flex items-center justify-between">
            <span class="truncate" title="<?= htmlspecialchars($img['name']) ?>"><?= htmlspecialchars($img['name']) ?></span>
            <button class="shrink-0 ml-2 rounded bg-zinc-800 hover:bg-zinc-700 px-2 py-1"
              onclick="copyToClipboard(location.origin + '<?= htmlspecialchars($img['url']) ?>')">Copy</button>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>

    <?php if (!count($all)): ?>
      <p class="text-zinc-400 text-sm">Aucune image pour le moment. Utilise l’API <code class="px-1 py-0.5 bg-zinc-800 rounded">/upload.php</code>.</p>
    <?php else: ?>
      <div id="sentinel" class="w-full h-12 flex items-center justify-center text-zinc-500 text-sm">Loading…</div>
    <?php endif; ?>
  </main>

  <!-- Lightbox -->
  <div id="lightbox" onclick="closeLightbox()" aria-hidden="true">
    <img id="lightbox-img" src="" alt="">
  </div>

  <script>
    const lb = document.getElementById('lightbox');
    const lbImg = document.getElementById('lightbox-img');
    function openLightbox(src, alt) { lbImg.src = src; lbImg.alt = alt||''; lb.classList.add('open'); }
    function closeLightbox() { lb.classList.remove('open'); lbImg.src=''; }
    window.addEventListener('keydown', e => { if (e.key==='Escape' && lb.classList.contains('open')) closeLightbox(); });

    function copyToClipboard(text) {
      navigator.clipboard?.writeText(text).then(()=>alert('URL copiée')).catch(()=>{});
    }
    document.getElementById('copyIndex')?.addEventListener('click', () => copyToClipboard(location.href));

    // ---- Infinite scroll ----
    let offset = <?= $initialLimit ?>;
    const limit  = 40;
    const total  = <?= count($all) ?>;
    const grid   = document.getElementById('grid');
    const sentinel = document.getElementById('sentinel');
    let loading = false;
    let done = (offset >= total);

    async function loadMore() {
      if (loading || done) return;
      loading = true;
      try {
        const url = `?mode=json&offset=${offset}&limit=${limit}`;
        const res = await fetch(url, {headers:{'Accept':'application/json'}});
        if (!res.ok) throw new Error('HTTP '+res.status);
        const data = await res.json();
        data.items.forEach(addCard);
        offset = (data.next ?? total);
        if (data.next === null) {
          done = true;
          sentinel.textContent = 'No more images.';
        }
      } catch (e) {
        console.error(e);
        sentinel.textContent = 'Error loading.';
      } finally {
        loading = false;
      }
    }

    function addCard(item) {
      const fig = document.createElement('figure');
      fig.className = 'masonry-item group overflow-hidden rounded-xl bg-zinc-900 border border-zinc-800';
      fig.innerHTML = `
        <img src="${item.url}" alt="${item.name}" loading="lazy" decoding="async"
             class="w-full h-auto transition-transform duration-300 ease-out group-hover:scale-[1.02] cursor-zoom-in">
        <figcaption class="px-3 py-2 text-xs text-zinc-400 flex items-center justify-between">
          <span class="truncate" title="${item.name}">${item.name}</span>
          <button class="shrink-0 ml-2 rounded bg-zinc-800 hover:bg-zinc-700 px-2 py-1">Copy</button>
        </figcaption>`;
      fig.querySelector('img').addEventListener('click', () => openLightbox(item.url, item.name));
      fig.querySelector('button').addEventListener('click', () => copyToClipboard(location.origin + item.url));
      grid.appendChild(fig);
    }

    if (sentinel) {
      const io = new IntersectionObserver(entries => {
        if (entries.some(e => e.isIntersecting)) loadMore();
      }, {rootMargin: '800px 0px'});
      io.observe(sentinel);
    }
  </script>
</body>
</html>
