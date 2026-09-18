<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

session_start();

header('Content-Type: text/html; charset=utf-8');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = normalizePhone((string)($_POST['phone'] ?? ''));

    if ($phone === '') {
        $error = 'মোবাইল নম্বর লিখুন।';
    } else {
        /*
         * Customer login in this project is phone-only.
         * The existing JavaScript sends {action:"login", customer_phone:phone}
         * to order-api.php, so this page is an independent fallback login page.
         *
         * Put the SAME database connection values that are already in your
         * working order-api.php in the four variables below.
         */
        try {
            $pdo = db();

            $stmt = $pdo->prepare(
                "SELECT id, name, phone, role, status
                 FROM users
                 WHERE phone = ? AND role = 'customer'
                 LIMIT 1"
            );
            $stmt->execute([$phone]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'এই মোবাইল নম্বর দিয়ে কোনো Account পাওয়া যায়নি। আগে Sign Up করুন।';
            } elseif (isset($user['status']) && strtolower((string)$user['status']) !== 'active') {
                $error = 'এই Account বর্তমানে সক্রিয় নয়।';
            } else {
                $_SESSION['customer_id'] = (int)$user['id'];
                $_SESSION['customer_phone'] = (string)$user['phone'];
                $_SESSION['customer_name'] = (string)$user['name'];

                $success = 'Login সফল হয়েছে।';

                // If the site has index.php, return there after successful login.
                header('Refresh: 1; url=index.php');
            }
        } catch (Throwable $e) {
            $error = 'সার্ভারের সাথে যোগাযোগ করা যায়নি।';
        }
    }
}
?>
<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login / Sign In</title>
<style>
*{box-sizing:border-box}
body{
    margin:0;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#f5f7fb;
    font-family:Arial,"Noto Sans Bengali",sans-serif;
    padding:20px;
}
.login-box{
    width:100%;
    max-width:420px;
    background:#fff;
    padding:28px;
    border-radius:18px;
    box-shadow:0 10px 35px rgba(0,0,0,.08);
}
h1{
    margin:0 0 8px;
    text-align:center;
    font-size:25px;
}
.sub{
    text-align:center;
    color:#6b7280;
    margin:0 0 22px;
}
label{
    display:block;
    margin-bottom:7px;
    font-weight:700;
}
input{
    width:100%;
    height:50px;
    border:1px solid #d1d5db;
    border-radius:10px;
    padding:0 14px;
    font-size:16px;
    outline:none;
}
input:focus{border-color:#16a34a}
button{
    width:100%;
    height:50px;
    margin-top:16px;
    border:0;
    border-radius:10px;
    background:#16a34a;
    color:#fff;
    font-size:16px;
    font-weight:700;
    cursor:pointer;
}
button:hover{background:#15803d}
.msg{
    padding:12px 14px;
    border-radius:10px;
    margin-bottom:16px;
    font-size:14px;
}
.error{background:#fef2f2;color:#b91c1c}
.success{background:#f0fdf4;color:#15803d}
.back{
    display:block;
    text-align:center;
    margin-top:18px;
    color:#16a34a;
    text-decoration:none;
    font-weight:700;
}
</style>
</head>
<body>

<div class="login-box">
    <h1>Login / Sign In</h1>
    <p class="sub">আপনার মোবাইল নম্বর দিয়ে Login করুন</p>

    <?php if ($error !== ''): ?>
        <div class="msg error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="msg success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="on">
        <label for="phone">মোবাইল নম্বর</label>
        <input
            id="phone"
            name="phone"
            type="tel"
            inputmode="numeric"
            autocomplete="tel"
            placeholder="মোবাইল নম্বর"
            value="<?= htmlspecialchars((string)($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            required
        >
        <button type="submit">Login / Sign In</button>
    </form>

    <a class="back" href="index.php">← Shopping এ ফিরে যান</a>
</div>

</body>
</html>
