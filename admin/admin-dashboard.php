<?php

session_start();
require_once __DIR__ . '/../config.php';

if (
    !isset($_SESSION["admin_id"]) ||
    $_SESSION["admin_role"] !== "admin"
) {
    header("Location: admin-login.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="bn">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Dashboard</title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f6f8;
        }

        .header {
            background: #111;
            color: #fff;
            padding: 18px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h2 {
            margin: 0;
        }

        .logout {
            color: #fff;
            text-decoration: none;
            background: #d00;
            padding: 8px 14px;
            border-radius: 6px;
        }

        .container {
            padding: 25px;
        }

        .welcome {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .menu {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .menu a {
            text-decoration: none;
            color: #111;
            background: #fff;
            padding: 25px 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .menu a:hover {
            transform: translateY(-2px);
        }

        @media (max-width: 700px) {
            .menu {
                grid-template-columns: repeat(2, 1fr);
            }
        }

    </style>

</head>

<body>

<header class="header">

    <h2>Admin Panel</h2>

    <a class="logout" href="admin-logout.php">
        Logout
    </a>

</header>

<div class="container">

    <div class="welcome">

        <h3>
            Welcome,
            <?= htmlspecialchars($_SESSION["admin_name"]) ?>
        </h3>

        <p>
            এখান থেকে আপনার website পরিচালনা করতে পারবেন।
        </p>

    </div>

    <div class="menu">

        <a href="admin-product-add.php">
            <i data-lucide="package"></i><br>
            Products
        </a>
        <a href="hero-banners.php">
            <i data-lucide="image"></i><br>
            Hero Banners
        </a>

        <a href="orders.php">
            <i data-lucide="shopping-bag"></i><br>
            Orders
        </a>

        <a href="customers.php">
            <i data-lucide="users"></i><br>
            Customers
        </a>

        <a href="categories.php">
            <i data-lucide="tags"></i><br>
            Categories
        </a>

        <a href="promo-codes.php">
            <i data-lucide="ticket"></i><br>
            Promo Codes
        </a>

        <a href="delivery.php">
            <i data-lucide="truck"></i><br>
            Delivery
        </a>

        <a href="reports.php">
            <i data-lucide="chart-column"></i><br>
            Reports
        </a>

        <a href="settings.php">
            <i data-lucide="settings"></i><br>
            Settings
        </a>

    </div>

</div>

</body>
<script>if (window.lucide) lucide.createIcons();</script>

</html>
