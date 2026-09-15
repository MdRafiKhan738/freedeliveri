<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $pdo = db();

    $stmt = $pdo->query(
        "SELECT id, title, subtitle, description, image_url, button_text, button_link,
                badge_text, show_badge, position
         FROM hero_banners
         WHERE status = 'active'
           AND (start_date IS NULL OR start_date <= CURDATE())
           AND (end_date IS NULL OR end_date >= CURDATE())
         ORDER BY position ASC, id DESC"
    );

    echo json_encode([
        'success' => true,
        'banners' => $stmt->fetchAll(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Hero banner data could not be loaded.',
        'banners' => [],
    ], JSON_UNESCAPED_UNICODE);
}
