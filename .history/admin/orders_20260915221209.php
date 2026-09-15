<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: admin-login.php');
    exit;
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function issueDeliveredPromo(PDO $pdo, array $order): void {
    if ((float)$order['subtotal'] < 500) return;
    $userStmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND role = 'customer' LIMIT 1");
    $userStmt->execute([$order['customer_phone']]);
    $userId = $userStmt->fetchColumn();
    if ($userId === false) {
        $insertUser = $pdo->prepare("INSERT INTO users (name, phone, password_hash, role, status) VALUES (?, ?, ?, 'customer', 'active')");
        $insertUser->execute([$order['customer_name'], $order['customer_phone'], password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)]);
        $userId = $pdo->lastInsertId();
    }
    $existing = $pdo->prepare("SELECT up.id FROM user_promos up JOIN promo_codes pc ON pc.id = up.promo_code_id WHERE up.user_id = ? AND pc.status = 'active' AND up.expires_at >= NOW() LIMIT 1");
    $existing->execute([(int)$userId]);
    if ($existing->fetchColumn() !== false) return;
    do {
        $code = 'FD' . strtoupper(bin2hex(random_bytes(3)));
        $check = $pdo->prepare("SELECT id FROM promo_codes WHERE code = ? LIMIT 1");
        $check->execute([$code]);
    } while ($check->fetchColumn() !== false);
    $starts = date('Y-m-d H:i:s');
    $expires = date('Y-m-d H:i:s', strtotime('+1 year'));
    $promo = $pdo->prepare("INSERT INTO promo_codes (code, description, discount_type, discount_value, minimum_order_amount, starts_at, expires_at, usage_limit, used_count, status) VALUES (?, ?, 'free_delivery', 0, 500, ?, ?, 0, 0, 'active')");
    $promo->execute([$code, 'Delivered 500+ order: 1 year unlimited Free Delivery', $starts, $expires]);
    $promoId = (int)$pdo->lastInsertId();
    $link = $pdo->prepare("INSERT INTO user_promos (user_id, promo_code_id, starts_at, expires_at, usage_count, status) VALUES (?, ?, ?, ?, 0, 'active')");
    $link->execute([(int)$userId, $promoId, $starts, $expires]);
}

try {
    $pdo = db();
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed.');
}

if (empty($_SESSION['orders_csrf'])) {
    $_SESSION['orders_csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = (string)($_POST['status'] ?? '');

    $allowedStatuses = [
        'pending', 'confirmed', 'processing',
        'shipped', 'delivered', 'cancelled'
    ];

    if (!hash_equals($_SESSION['orders_csrf'], $csrf)) {
        $message = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। আবার চেষ্টা করুন।';
        $messageType = 'error';
    } elseif ($orderId < 1 || !in_array($newStatus, $allowedStatuses, true)) {
        $message = 'অর্ডার বা স্ট্যাটাস সঠিক নয়।';
        $messageType = 'error';
    } else {
        try {
            $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch();
            if (!$order) throw new RuntimeException('Order not found');
            $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $orderId]);
            if ($newStatus === 'delivered' && ($order['status'] ?? '') !== 'delivered') {
                issueDeliveredPromo($pdo, $order);
            }
            $message = "অর্ডার #{$orderId}-এর স্ট্যাটাস আপডেট হয়েছে।";
        } catch (Throwable $e) {
            error_log('Admin Order Status Error: ' . $e->getMessage());
            $message = 'স্ট্যাটাস আপডেট করা যায়নি।';
            $messageType = 'error';
        }
    }
}

$statusLabels = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled'
];

$statusClasses = [
    'pending' => 'pending',
    'confirmed' => 'confirmed',
    'processing' => 'processing',
    'shipped' => 'shipped',
    'delivered' => 'delivered',
    'cancelled' => 'cancelled'
];

$filterStatus = (string)($_GET['status'] ?? '');
if ($filterStatus !== '' && !isset($statusLabels[$filterStatus])) {
    $filterStatus = '';
}

$search = trim((string)($_GET['search'] ?? ''));

$sql = "
    SELECT
        o.id,
        o.customer_name,
        o.customer_phone,
        o.customer_address,
        o.delivery_method,
        o.payment_method,
        o.subtotal,
        o.delivery_charge,
        o.discount,
        o.total_amount,
        o.promo_code,
        o.customer_note,
        o.admin_note,
        o.status,
        o.created_at,
        o.updated_at
    FROM orders o
";

$where = [];
$params = [];

if ($filterStatus !== '') {
    $where[] = 'o.status = ?';
    $params[] = $filterStatus;
}

if ($search !== '') {
    $where[] = '(o.customer_name LIKE ? OR o.customer_phone LIKE ? OR CAST(o.id AS CHAR) LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY o.id DESC LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$itemStmt = $pdo->prepare('SELECT product_name, product_price, quantity, item_total FROM order_items WHERE order_id = ? ORDER BY id ASC');

$countStmt = $pdo->query("SELECT status, COUNT(*) AS total FROM orders GROUP BY status");
$statusCounts = array_fill_keys(array_keys($statusLabels), 0);
foreach ($countStmt->fetchAll() as $row) {
    if (isset($statusCounts[$row['status']])) {
        $statusCounts[$row['status']] = (int)$row['total'];
    }
}
$totalOrders = array_sum($statusCounts);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orders - Admin</title>
<script src="https://unpkg.com/lucide@latest"></script>
<style>
*{box-sizing:border-box}
body{margin:0;background:#f5f7fb;color:#111827;font-family:"Noto Sans Bengali","Noto Sans",Arial,sans-serif}
.page{max-width:1250px;margin:auto;padding:22px 15px 50px}
.topbar{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:18px}
.topbar h1{margin:0;font-size:27px}
.back{display:inline-block;text-decoration:none;background:#111827;color:#fff;padding:10px 15px;border-radius:9px;font-weight:700}
.message{padding:12px 15px;border-radius:10px;margin-bottom:16px;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534}
.message.error{border-color:#fecaca;background:#fef2f2;color:#991b1b}
.stats{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:10px;margin-bottom:18px}
.stat{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:13px;box-shadow:0 2px 10px rgba(0,0,0,.04)}
.stat span{display:block;color:#6b7280;font-size:12px;margin-bottom:4px}.stat strong{font-size:20px}
.tools{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px;margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap}
.tools input,.tools select{height:42px;border:1px solid #d1d5db;border-radius:8px;padding:0 11px;font:inherit;background:#fff}
.tools input{min-width:230px;flex:1}.tools button,.tools a{height:42px;border:0;border-radius:8px;padding:0 15px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
.tools button{background:#111827;color:#fff}.tools a{background:#e5e7eb;color:#111827}
.order{background:#fff;border:1px solid #e5e7eb;border-radius:14px;margin-bottom:15px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.04)}
.order-head{padding:14px 16px;background:#fafafa;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.order-head strong{font-size:17px}.date{color:#6b7280;font-size:13px}
.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800;background:#e5e7eb;color:#374151}
.badge.pending{background:#fef3c7;color:#92400e}.badge.confirmed{background:#dbeafe;color:#1d4ed8}.badge.processing{background:#ede9fe;color:#6d28d9}.badge.shipped{background:#cffafe;color:#155e75}.badge.delivered{background:#dcfce7;color:#166534}.badge.cancelled{background:#fee2e2;color:#991b1b}
.order-body{padding:16px}.customer{line-height:1.8;margin-bottom:13px}.customer b{display:inline-block;min-width:85px}
.items{width:100%;border-collapse:collapse;margin:10px 0 14px}.items th,.items td{text-align:left;padding:9px 7px;border-bottom:1px solid #eee;font-size:13px}.items th{color:#6b7280;font-weight:700}
.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:10px}.summary div{background:#f8fafc;border-radius:9px;padding:10px}.summary small{display:block;color:#6b7280}.summary strong{display:block;margin-top:3px}
.status-form{display:flex;gap:8px;align-items:center;margin-top:14px;padding-top:14px;border-top:1px solid #eee}.status-form select{height:40px;border:1px solid #d1d5db;border-radius:8px;padding:0 9px;font:inherit}.status-form button{height:40px;border:0;border-radius:8px;background:#16a34a;color:#fff;padding:0 14px;font-weight:800;cursor:pointer}
.empty{text-align:center;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:35px;color:#6b7280}
@media(max-width:900px){.stats{grid-template-columns:repeat(4,1fr)}}
@media(max-width:600px){.page{padding:15px 10px 35px}.topbar h1{font-size:22px}.stats{grid-template-columns:repeat(2,1fr)}.summary{grid-template-columns:repeat(2,1fr)}.order-head{align-items:flex-start}.items{font-size:12px}.items th,.items td{padding:8px 4px}.status-form{flex-direction:column;align-items:stretch}.status-form select,.status-form button{width:100%}.tools input{min-width:100%}}
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <h1><i class="fa-solid fa-bag-shopping"></i> Orders</h1>
        <a class="back" href="admin-dashboard.php">← Dashboard</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="message <?= $messageType === 'error' ? 'error' : '' ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat"><span>All Orders</span><strong><?= $totalOrders ?></strong></div>
        <?php foreach ($statusLabels as $key => $label): ?>
            <div class="stat"><span><?= e($label) ?></span><strong><?= $statusCounts[$key] ?></strong></div>
        <?php endforeach; ?>
    </div>

    <form class="tools" method="get">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="নাম, মোবাইল বা Order ID খুঁজুন">
        <select name="status">
            <option value="">সব Status</option>
            <?php foreach ($statusLabels as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Search</button>
        <a href="orders.php">Reset</a>
    </form>

    <?php if (!$orders): ?>
        <div class="empty">কোনো অর্ডার পাওয়া যায়নি।</div>
    <?php endif; ?>

    <?php foreach ($orders as $order): ?>
        <?php
        $itemStmt->execute([(int)$order['id']]);
        $items = $itemStmt->fetchAll();
        $status = $order['status'];
        ?>
        <article class="order">
            <div class="order-head">
                <div>
                    <strong>Order #<?= (int)$order['id'] ?></strong>
                    <span class="date"> · <?= e($order['created_at']) ?></span>
                </div>
                <span class="badge <?= e($statusClasses[$status] ?? '') ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
            </div>

            <div class="order-body">
                <div class="customer">
                    <div><b>Customer:</b> <?= e($order['customer_name']) ?></div>
                    <div><b>Phone:</b> <?= e($order['customer_phone']) ?></div>
                    <div><b>Address:</b> <?= nl2br(e($order['customer_address'])) ?></div>
                    <?php if ($order['customer_note'] !== null && trim($order['customer_note']) !== ''): ?>
                        <div><b>Note:</b> <?= nl2br(e($order['customer_note'])) ?></div>
                    <?php endif; ?>
                </div>

                <table class="items">
                    <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['product_name']) ?></td>
                            <td>৳ <?= e(number_format((float)$item['product_price'], 2)) ?></td>
                            <td><?= (int)$item['quantity'] ?></td>
                            <td>৳ <?= e(number_format((float)$item['item_total'], 2)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="summary">
                    <div><small>Subtotal</small><strong>৳ <?= e(number_format((float)$order['subtotal'], 2)) ?></strong></div>
                    <div><small>Delivery</small><strong>৳ <?= e(number_format((float)$order['delivery_charge'], 2)) ?></strong></div>
                    <div><small>Discount</small><strong>৳ <?= e(number_format((float)$order['discount'], 2)) ?></strong></div>
                    <div><small>Grand Total</small><strong>৳ <?= e(number_format((float)$order['total_amount'], 2)) ?></strong></div>
                </div>

                <form class="status-form" method="post">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['orders_csrf']) ?>">
                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                    <select name="status" aria-label="Order status">
                        <?php foreach ($statusLabels as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Update Status</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</div>
</body>
<script>if (window.lucide) lucide.createIcons();</script>
</html>

