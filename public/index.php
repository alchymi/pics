<?php
$dir = __DIR__.'/pictures';
$base = '/pictures';
$files = [];

foreach (scandir($dir) as $f) {
    if ($f[0] === '.') continue;
    if (!preg_match('~\.(jpe?g|png|webp|gif)$~i', $f)) continue;

    $source = "$dir/$f";
    $info = @getimagesize($source);
    if (!$info) continue;

    $files[] = [
        'name' => $f,
        'url'  => $base.'/'.$f,
        'w'    => $info[0],
        'h'    => $info[1],
        'ts'   => filemtime($source),
    ];
}

usort($files, fn($a,$b) => $b['ts'] <=> $a['ts']);
$initial = array_slice($files, 0, 8);
$total = count($files);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Gallery v3</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css">

<style>
.grid-auto {
  display: grid;
  grid-template-columns: repeat(auto-fill,minmax(250px,1fr));
  gap: 1rem;
}
.skeleton {
  background: linear-gradient(90deg,#2c2c2c 0%,#3b3b3b 50%,#2c2c2c 100%);
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
}
@keyframes shimmer {
  0% { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}
.item {
  opacity: 0;
  transform: translateY(20px);
  transition: .4s ease;
}
.item.show {
  opacity: 1;
  transform: translateY(0);
}
</style>
</head>

<body class="bg-zinc-950 text-zinc-100">

<header class="sticky top-0 bg-zinc-900/80 backdrop-blur px-4 py-3 border-b border-zinc-800">
  <h1 class="text-lg font-semibold">pics.funtools.cloud — <?= $total ?> images</h1>
</header>

<main class="max-w-7xl mx-auto px-4 py-6">

  <div id="gallery" class="grid-auto">

    <?php foreach ($initial as $img): ?>
      <div class="item rounded-xl overflow-hidden bg-zinc-900 border border-zinc-800">
        <img
          src="<?= $img['url'] ?>"
          loading="lazy"
          class="w-full h-auto cursor-pointer"
          style="aspect-ratio: <?= $img['w'] ?>/<?= $img['h'] ?>"
          data-pswp-width="<?= $img['w'] ?>"
          data-pswp-height="<?= $img['h'] ?>"
        >
      </div>
    <?php endforeach; ?>

  </div>

  <div id="loader" class="mt-10 hidden">
    <div class="grid-auto">
      <?php for ($i=0;$i<8;$i++): ?>
        <div class="h-64 rounded-xl skeleton"></div>
      <?php endfor; ?>
    </div>
  </div>

  <div id="trigger" class="h-10"></div>
</main>

<script type="module">
import PhotoSwipeLightbox from '/vendor/photoswipe/photoswipe.esm.min.js';

let offset = 8;
const LIMIT = 8;
const TOTAL = <?= $total ?>;

const gallery = document.getElementById("gallery");
const loader  = document.getElementById("loader");
const trigger = document.getElementById("trigger");

// Lightbox
const lightbox = new PhotoSwipeLightbox({
  gallery: "#gallery",
  children: "img",
  pswpModule: () => import('/vendor/photoswipe/photoswipe.esm.min.js'),
});
lightbox.init();

// Infinite scroll observer
const observer = new IntersectionObserver(async (entries)=>{
  if (!entries[0].isIntersecting) return;
  if (offset >= TOTAL) return;

  loader.classList.remove("hidden");

  const res = await fetch(`/api.php?offset=${offset}&limit=${LIMIT}`);
  const imgs = await res.json();

  await new Promise(r => setTimeout(r, 400));
  loader.classList.add("hidden");

  imgs.forEach(img => {
    const div = document.createElement("div");
    div.className = "item rounded-xl overflow-hidden bg-zinc-900 border border-zinc-800";

    div.innerHTML = `
      <img
        src="${img.thumb}"
        data-full="${img.url}"
        class="w-full h-auto cursor-pointer"
        style="aspect-ratio:${img.w}/${img.h}"
        data-pswp-width="${img.w}"
        data-pswp-height="${img.h}"
      >
    `;

    gallery.appendChild(div);

    requestAnimationFrame(() => div.classList.add("show"));
  });

  offset += LIMIT;
});

observer.observe(trigger);
</script>

</body>
</html>
