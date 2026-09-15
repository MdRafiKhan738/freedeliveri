<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cloudinary.php';

if (($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: admin-login.php');
    exit;
}

$message = '';
$editing = null;
$editId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'create');
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $upload = uploadImageToMysql($_FILES['category_image'] ?? []);

    if ($name === '') {
        $message = 'Category name is required.';
    } elseif (!$upload['success']) {
        $message = $upload['message'];
    } else {
        try {
            if ($action === 'update' && $id > 0) {
                $sql = 'UPDATE categories SET name = ?';
                $params = [$name];
                if (!empty($upload['data'])) {
                    $sql .= ', image_data = ?, image_mime = ?';
                    $params[] = $upload['data'];
                    $params[] = $upload['mime'];
                }
                $sql .= ' WHERE id = ?';
                $params[] = $id;
                db()->prepare($sql)->execute($params);
                $message = 'Category updated successfully.';
            } else {
                db()->prepare('INSERT INTO categories (name, image_data, image_mime) VALUES (?, ?, ?)')
                    ->execute([$name, $upload['data'] ?? null, $upload['mime'] ?? null]);
                $message = 'Category added successfully.';
            }
        } catch (Throwable $exception) {
            $message = 'Category could not be saved. It may already exist.';
        }
    }
}

if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId > 0) {
        db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$deleteId]);
        header('Location: categories.php?deleted=1');
        exit;
    }
}

if ($editId > 0) {
    $stmt = db()->prepare('SELECT id, name FROM categories WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}

$categories = db()->query('SELECT id, name, image_mime, created_at FROM categories ORDER BY name ASC')->fetchAll();
function catEscape(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Categories | Admin</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f3f7f5;color:#14201d;font-family:Arial,"Noto Sans Bengali",sans-serif}.bar{background:#10231e;color:#fff;padding:18px 5%;display:flex;justify-content:space-between;align-items:center}.bar a{color:#fff;text-decoration:none}.page{max-width:1050px;margin:30px auto;padding:0 18px}.panel{background:#fff;border-radius:16px;padding:22px;margin-bottom:18px;box-shadow:0 12px 30px #1232}.form{display:grid;grid-template-columns:1fr 1fr;gap:12px}.form input,.form button{height:45px;padding:0 13px;border:1px solid #d4e1dc;border-radius:9px;font:inherit}.form input[type=file]{padding:10px}.form button{background:#087f63;color:#fff;font-weight:700;cursor:pointer}.notice{padding:12px;background:#e9f8f1;border-radius:10px;margin-bottom:15px}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:13px;text-align:left;border-bottom:1px solid #e5eeea}.thumb{width:48px;height:48px;object-fit:cover;border-radius:12px;background:#edf3f0;vertical-align:middle}.action{display:inline-block;margin-right:8px;color:#087f63;text-decoration:none;font-weight:700}.danger{color:#b42318}@media(max-width:650px){.form{grid-template-columns:1fr}.table{font-size:13px}.table th:nth-child(4),.table td:nth-child(4){display:none}}
</style>
</head>
<body>
<header class="bar"><strong>Categories</strong><a href="admin-dashboard.php">Dashboard</a></header>
<main class="page">
<section class="panel">
<h2><?= $editing ? 'Edit category' : 'Add category' ?></h2>
<?php if ($message): ?><div class="notice"><?= catEscape($message) ?></div><?php endif; ?>
<form class="form" method="post" enctype="multipart/form-data">
<input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
<input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
<input name="name" required placeholder="Category name" value="<?= catEscape($editing['name'] ?? '') ?>">
<input type="file" name="category_image" accept="image/jpeg,image/png,image/webp">
<button type="submit"><?= $editing ? 'Update Category' : 'Add Category' ?></button>
<?php if ($editing): ?><a class="action" href="categories.php">Cancel edit</a><?php endif; ?>
</form>
</section>
<section class="panel"><h2>All categories</h2><table class="table"><thead><tr><th>Image</th><th>Name</th><th>Created</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($categories as $category): ?>
<tr><td><?php if ($category['image_mime']): ?><img class="thumb" src="../image-api.php?type=category&id=<?= (int)$category['id'] ?>" alt="<?= catEscape($category['name']) ?>"><?php else: ?>No image<?php endif; ?></td><td><strong><?= catEscape($category['name']) ?></strong></td><td><?= catEscape($category['created_at']) ?></td><td><a class="action" href="categories.php?edit=<?= (int)$category['id'] ?>">Edit</a><a class="action danger" href="categories.php?delete=<?= (int)$category['id'] ?>" onclick="return confirm('Delete this category?')">Delete</a></td></tr>
<?php endforeach; ?>
</tbody></table></section>
</main>
</body>
</html>
