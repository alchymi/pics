<?php
header('Content-Type: application/json');

require_once __DIR__ . '/thumb.php';

$offset = intval($_GET['offset'] ?? 0);
$limit  = intval($_GET['limit'] ?? 8);

$dir  = __DIR__.'/pictures';
$base = '/pictures';

$files = [];

foreach (scandir($dir) as $f) {
    if ($f[0] === '.') continue;
    if (!preg_match('~\.(jpe?g|png|webp|gif)$~i', $f)) continue;

    $source = "$dir/$f";
    $info   = @getimagesize($source);
    if (!$info) continue;

    $thumb = get_thumbnail($source);

    $files[] = [
        "name"  => $f,
        "url"   => $base . "/" . $f,
        "thumb" => $thumb,
        "w"     => $info[0],
        "h"     => $info[1],
        "ts"    => filemtime($source),
    ];
}

usort($files, fn($a,$b) => $b["ts"] <=> $a["ts"]);

echo json_encode(array_slice($files, $offset, $limit));
