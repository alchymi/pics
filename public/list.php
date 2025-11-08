<?php
$dir = __DIR__ . '/pictures';
$files = is_dir($dir) ? array_diff(scandir($dir), ['.', '..']) : [];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Liste des fichiers</title>
</head>
<body>
  <h1>Fichiers dans /pictures</h1>
  <ul>
    <?php foreach ($files as $f): ?>
      <li><?= htmlspecialchars($f) ?></li>
    <?php endforeach; ?>
  </ul>
</body>
</html>