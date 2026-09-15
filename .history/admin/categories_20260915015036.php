<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config.php';
if (($_SESSION['admin_role'] ?? '') !== 'admin') { header('Location: admin-login.php'); exit; }
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') { $message = 'Category name is required.'; }
    else { try { $stmt = db()->prepare('INSERT INTO categories (name) VALUES (?)'); $stmt->execute([$name]); $message = 'Category added successfully.'; } catch (Throwable $e) { $message = 'Category could not be added. It may already exist.'; } }
}
$categories = db()->query('SELECT id,name,created_at FROM categories ORDER BY name ASC')->fetchAll();
function catEscape(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Categories</title><style>body{margin:0;background:#f3f7f5;color:#14201d;font-family:Arial,sans-serif}.bar{background:#10231e;color:#fff;padding:18px 5%;display:flex;justify-content:space-between}.bar a{color:#fff}.page{max-width:900px;margin:30px auto;padding:0 18px}.panel{background:#fff;border-radius:16px;padding:22px;margin-bottom:18px;box-shadow:0 12px 30px #1232  }input,button{height:44px;padding:0 13px;border:1px solid #d4e1dc;border-radius:9px;font:inherit}input{width:70%}button{background:#087f63;color:#fff;font-weight:700;cursor:pointer}li{padding:13px 0;border-bottom:1px solid #edf1ef}</style></head><body><header class="bar"><strong>Categories</strong><a href="admin-dashboard.php">Dashboard</a></header><main class="page"><section class="panel"><h2>Add category</h2><?php if($message): ?><p><?=catEscape($message)?></p><?php endif; ?><form method="post"><input name="name" required placeholder="Category name"><button>Add Category</button></form></section><section class="panel"><h2>All categories</h2><ol><?php foreach($categories as $category): ?><li><strong><?=catEscape($category['name'])?></strong> <small>#<?= (int)$category['id'] ?></small></li><?php endforeach; ?></ol></section></main></body></html>
