<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: admin-login.php');
    exit;
}

$search = trim((string)($_GET['search'] ?? ''));
$role = trim((string)($_GET['role'] ?? 'customer'));
$status = trim((string)($_GET['status'] ?? ''));
$users = [];
$error = '';

try {
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(name LIKE ? OR phone LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    if (in_array($role, ['customer', 'admin'], true)) {
        $where[] = 'role = ?';
        $params[] = $role;
    }
    if (in_array($status, ['active', 'inactive'], true)) {
        $where[] = 'status = ?';
        $params[] = $status;
    }

    $sql = 'SELECT id, name, phone, role, status, created_at FROM users';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY id DESC LIMIT 200';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (Throwable $exception) {
    $error = 'Customer data could not be loaded.';
}

function customerEscape(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Customers | Admin</title>
<style>
:root{--ink:#14201d;--muted:#6b7a75;--line:#e3ebe7;--brand:#087f63;--brand-dark:#075b49;--surface:#fff;--canvas:#f4f8f6;--danger:#b42318;--shadow:0 16px 40px rgba(12,50,39,.08)}
*{box-sizing:border-box}body{margin:0;background:var(--canvas);color:var(--ink);font-family:Inter,"Noto Sans Bengali",Arial,sans-serif}.topbar{background:#10231e;color:#fff;padding:18px clamp(18px,4vw,54px);display:flex;align-items:center;justify-content:space-between;gap:16px}.topbar h1{margin:0;font-size:clamp(21px,3vw,30px)}.topbar a{color:#fff;text-decoration:none;background:#1f4037;border:1px solid #3b6257;border-radius:10px;padding:10px 15px;font-weight:700}.page{max-width:1260px;margin:auto;padding:28px clamp(16px,4vw,54px) 60px}.intro{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:22px;animation:rise .5s ease both}.intro h2{margin:0 0 6px;font-size:28px}.intro p{margin:0;color:var(--muted)}.count{font-size:28px;font-weight:900;color:var(--brand)}.filters{display:flex;gap:10px;flex-wrap:wrap;background:var(--surface);padding:14px;border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);margin-bottom:20px;animation:rise .6s ease both}.filters input,.filters select,.filters button,.filters a{height:44px;border:1px solid #d3e0db;border-radius:10px;padding:0 13px;font:inherit}.filters input{min-width:240px;flex:1}.filters button{background:var(--brand);border-color:var(--brand);color:#fff;font-weight:800;cursor:pointer}.filters a{display:inline-flex;align-items:center;color:var(--ink);text-decoration:none;background:#f1f5f3}.alert{padding:14px 16px;border-radius:12px;background:#fff1f0;color:var(--danger);margin-bottom:16px}.table-wrap{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:auto;box-shadow:var(--shadow);animation:rise .7s ease both}.users{width:100%;border-collapse:collapse;min-width:720px}.users th,.users td{text-align:left;padding:15px 17px;border-bottom:1px solid #edf2ef}.users th{background:#f7faf8;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.04em}.users tr:last-child td{border-bottom:0}.users tbody tr{transition:background .2s,transform .2s}.users tbody tr:hover{background:#f7fbf9;transform:translateX(2px)}.person{display:flex;align-items:center;gap:11px}.avatar{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;background:#dff4ec;color:var(--brand-dark);font-weight:900}.person strong{display:block}.person small{color:var(--muted)}.pill{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800}.pill.active{background:#dcfce7;color:#166534}.pill.inactive{background:#fee2e2;color:#991b1b}.pill.admin{background:#e0e7ff;color:#3730a3}.pill.customer{background:#e0f2fe;color:#075985}.actions{display:flex;gap:7px}.actions a{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:9px;text-decoration:none;background:#eff8f4;color:var(--brand-dark);transition:transform .2s}.actions a:hover{transform:translateY(-2px)}.empty{text-align:center;padding:50px;color:var(--muted)}@keyframes rise{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}@media(max-width:680px){.intro{align-items:start;flex-direction:column}.filters input{min-width:100%}.page{padding-top:20px}}
</style>
</head>
<body>
<header class="topbar"><h1>Customer Directory</h1><a href="admin-dashboard.php">&#8592; Dashboard</a></header>
<main class="page">
<section class="intro"><div><h2>Registered users</h2><p>All accounts created on your platform appear here.</p></div><div><div class="count"><?= count($users) ?></div><small>matching accounts</small></div></section>
<form class="filters" method="get"><input name="search" value="<?= customerEscape($search) ?>" placeholder="Search name or phone..."><select name="role"><option value="">All roles</option><option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Customers</option><option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admins</option></select><select name="status"><option value="">All status</option><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option></select><button type="submit">Filter</button><a href="customers.php">Reset</a></form>
<?php if ($error): ?><div class="alert"><?= customerEscape($error) ?></div><?php endif; ?>
<section class="table-wrap"><table class="users"><thead><tr><th>User</th><th>Phone</th><th>Role</th><th>Status</th><th>Joined</th><th>Contact</th></tr></thead><tbody><?php if (!$users): ?><tr><td colspan="6" class="empty">No registered users found.</td></tr><?php else: ?><?php foreach ($users as $user): ?><tr><td><div class="person"><span class="avatar"><?= customerEscape(strtoupper(substr((string)($user['name'] ?? 'U'), 0, 1))) ?></span><span><strong><?= customerEscape($user['name']) ?></strong><small>#<?= (int)$user['id'] ?></small></span></div></td><td><?= customerEscape($user['phone']) ?></td><td><span class="pill <?= customerEscape($user['role']) ?>"><?= customerEscape(ucfirst((string)$user['role'])) ?></span></td><td><span class="pill <?= customerEscape($user['status']) ?>"><?= customerEscape(ucfirst((string)$user['status'])) ?></span></td><td><?= customerEscape($user['created_at']) ?></td><td><div class="actions"><a href="tel:<?= customerEscape($user['phone']) ?>" title="Call">&#9742;</a><a href="https://wa.me/<?= customerEscape(preg_replace('/^0/', '880', (string)$user['phone'])) ?>" target="_blank" rel="noopener" title="WhatsApp">&#9993;</a></div></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></section>
</main>
</body></html>
