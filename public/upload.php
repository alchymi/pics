<?php
declare(strict_types=1);

// ------- Config -------
$SAVE_DIR     = __DIR__ . '/pictures';
$BASE_PUBLIC  = 'https://pics.funtools.cloud'; // <- ton domaine
$PUBLIC_PATH  = '/pictures';                   // sous-chemin public
$MAX_BYTES    = 10 * 1024 * 1024;              // 10 MB
$TIMEOUT      = 20;                            // sec
$ALLOWED_MIME = [
  'image/jpeg' => 'jpg',
  'image/png'  => 'png',
  'image/webp' => 'webp',
  'image/gif'  => 'gif',
];

// Assure dossier
if (!is_dir($SAVE_DIR)) {
  @mkdir($SAVE_DIR, 0755, true);
}

// JSON out
function json_out(int $code, array $payload) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  exit;
}

// Inputs
$prompt   = $_POST['Prompt']     ?? $_POST['prompt']     ?? null;
$urlIn    = $_POST['url']        ?? $_POST['ImageUrl']   ?? null;
$fileUp   = $_FILES['image']     ?? null;

if (!$urlIn && !$fileUp) {
  json_out(400, ['ok'=>false, 'error'=>'Provide url/ImageUrl or multipart file "image"']);
}

$raw = null;
$src = null;

if ($urlIn) {
  $urlIn = trim((string)$urlIn);
  if (!preg_match('~^https?://~i', $urlIn)) {
    json_out(400, ['ok'=>false, 'error'=>'Only http(s) URLs allowed']);
  }
  // (Optionnel) Anti-SSRF light: refuse localhost et IP privées
  $host = parse_url($urlIn, PHP_URL_HOST);
  if ($host) {
    $ip = gethostbyname($host);
    if (
      preg_match('~^(127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)~', $ip)
      || $ip === '::1'
    ) {
      json_out(400, ['ok'=>false, 'error'=>'Private IPs not allowed']);
    }
  }

  $ch = curl_init($urlIn);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 3,
    CURLOPT_TIMEOUT        => $TIMEOUT,
    CURLOPT_USERAGENT      => 'pics-funtools-uploader/1.0',
  ]);
  $raw = curl_exec($ch);
  $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);

  if ($raw === false || $httpCode < 200 || $httpCode >= 300) {
    json_out(400, ['ok'=>false, 'error'=>"Download failed ($httpCode) $err"]);
  }
  if (strlen($raw) > $MAX_BYTES) {
    json_out(413, ['ok'=>false, 'error'=>'Image too large']);
  }
  $src = 'url';
} else {
  if (!isset($fileUp['tmp_name']) || !is_uploaded_file($fileUp['tmp_name'])) {
    json_out(400, ['ok'=>false, 'error'=>'Invalid uploaded file']);
  }
  if (($fileUp['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    json_out(400, ['ok'=>false, 'error'=>'Upload error code '.$fileUp['error']]);
  }
  if (($fileUp['size'] ?? 0) > $MAX_BYTES) {
    json_out(413, ['ok'=>false, 'error'=>'Image too large']);
  }
  $raw = file_get_contents($fileUp['tmp_name']);
  $src = 'upload';
}

// Type réel
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->buffer($raw) ?: 'application/octet-stream';
if (!isset($ALLOWED_MIME[$mime])) {
  json_out(415, ['ok'=>false, 'error'=>'Unsupported image type: '.$mime]);
}
$ext = $ALLOWED_MIME[$mime];

// Nom fichier
$rand = bin2hex(random_bytes(8));
$filename = date('Ymd_His')."_$rand.$ext";
$destPath = $SAVE_DIR . '/' . $filename;

// Sauve
if (file_put_contents($destPath, $raw) === false) {
  json_out(500, ['ok'=>false, 'error'=>'Failed to save image']);
}

// Métadonnées (optionnel)
$meta = [
  'source'   => $src,
  'prompt'   => $prompt,
  'mime'     => $mime,
  'bytes'    => strlen($raw),
  'saved_at' => date('c'),
  'file'     => $filename,
  'url'      => rtrim($BASE_PUBLIC, '/').$PUBLIC_PATH.'/'.$filename,
];
@file_put_contents($destPath.'.json', json_encode($meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));

// Réponse n8n
json_out(200, [
  'ok'       => true,
  'fileName' => $filename,
  'mime'     => $mime,
  'size'     => $meta['bytes'],
  'url'      => $meta['url'],
]);