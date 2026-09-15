<?php
session_start();
require_once __DIR__ . '/../config.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $phone = trim($_POST["phone"] ?? "");
    $phone = normalizePhone($phone);

    if ($phone === "") {
        $error = "Admin phone number দিন।";
    } else {

        $stmt = db()->prepare("
            SELECT id, name, phone, role, status
            FROM users
            WHERE phone = ? AND role = 'admin'
            LIMIT 1
        ");

        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        if ($user) {

            if (
                $user["role"] === "admin" &&
                $user["status"] === "active"
            ) {

                $_SESSION["admin_id"] = $user["id"];
                $_SESSION["admin_name"] = $user["name"];
                $_SESSION["admin_role"] = $user["role"];

                header("Location: admin-dashboard.php");
                exit;

            } else {
                $error = "এই নম্বরের active admin account নেই।";
            }

        } else {
            $error = "এই নম্বরের active admin account নেই।";
        }

    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f5f5f5;
            font-family: Arial, sans-serif;
        }

        .login-box {
            width: 100%;
            max-width: 400px;
            margin: 20px;
            padding: 30px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.10);
        }

        .login-box h2 {
            text-align: center;
            margin-bottom: 25px;
        }

        .login-box input {
            width: 100%;
            padding: 13px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 7px;
            font-size: 15px;
        }

        .login-box button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 7px;
            background: #111;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
        }

        .error {
            background: #ffe5e5;
            color: #c00;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>

<body>

<div class="login-box">

    <h2>Admin Login</h2>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <input
            type="tel"
            name="phone"
            placeholder="Admin Phone Number"
            required
        >

        <button type="submit">
            Phone দিয়ে Login
        </button>

    </form>

</div>

</body>
</html>
