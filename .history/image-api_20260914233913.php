<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$type = (string)($_GET['type'] ?? 'product');
$id = (int)($_GET['id'] ?? 0);
if ($id < 1 || !in_array($type, ['product', 'hero'], true)) {
    http_response_code(404);
    exit;
}

try {
    $table = $type === 'hero' ? 'hero_banners' : 'products';
    $stmt = db()->prepare("SELECT image_data, image_mime FROM {$table} WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $image = $stmt->fetch();
    if (!$image || empty($image['image_data']) || empty($image['image_mime'])) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $image['image_mime']);
    header('Cache-Control: public, max-age=31536000, immutable');
    echo $image['image_data'];
} catch (Throwable $e) {
    http_response_code(500);
}