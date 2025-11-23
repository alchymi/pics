<?php
function get_thumbnail($source_path, $base_url = '', $thumb_dir = __DIR__.'/thumbs') {

    if (!is_dir($thumb_dir)) {
        mkdir($thumb_dir, 0777, true);
    }

    $filename = basename($source_path);
    $thumb_path = "$thumb_dir/$filename";

    if (file_exists($thumb_path)) {
        return '/thumbs/'.$filename;
    }

    $info = @getimagesize($source_path);
    if (!$info) return null;

    [$w, $h, $type] = $info;

    switch ($type) {
        case IMAGETYPE_WEBP: $src = imagecreatefromwebp($source_path); break;
        case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($source_path); break;
        case IMAGETYPE_PNG:  $src = imagecreatefrompng($source_path); break;
        default: return null;
    }

    $max = 200;
    $ratio = $max / max($w, $h);
    $newW = max(1, (int)($w * $ratio));
    $newH = max(1, (int)($h * $ratio));

    $thumb = imagecreatetruecolor($newW, $newH);

    if ($type === IMAGETYPE_PNG) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }

    imagecopyresampled($thumb, $src, 0,0,0,0, $newW, $newH, $w, $h);

    imagewebp($thumb, $thumb_path, 85);

    imagedestroy($thumb);
    imagedestroy($src);

    return '/thumbs/'.$filename;
}
