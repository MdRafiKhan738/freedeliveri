<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$type = (string)($_GET['type'] ?? 'product');
$id = (int)($_GET['id'] ?? 0);
$mediaId = (int)($_GET['media_id'] ?? 0);
if ($type === 'profile') {
    $phone = trim((string)($_GET['phone'] ?? ''));
    if ($phone === '') { http_response_code(404); exit; }
} elseif (($type !== 'product_media' && $id < 1) || ($type === 'product_media' && $mediaId < 1) || !in_array($type, ['product', 'product_media', 'hero', 'category'], true)) {
    http_response_code(404);
    exit;
}

try {
    if ($type === 'profile') {
        $stmt = db()->prepare('SELECT profile_image_data AS image_data, profile_image_mime AS image_mime FROM users WHERE phone = ? AND role = \'customer\' LIMIT 1');
        $stmt->execute([$phone]);
    } elseif ($type === 'product_media') {
        $stmt = db()->prepare('SELECT media_data AS image_data, media_mime AS image_mime FROM product_media WHERE id = ? LIMIT 1');
        $stmt->execute([$mediaId]);
    } else {
        $table = match ($type) {
        'hero' => 'hero_banners',
        'category' => 'categories',
        default => 'products',
        };
        $stmt = db()->prepare("SELECT image_data, image_mime FROM {$table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
    }
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