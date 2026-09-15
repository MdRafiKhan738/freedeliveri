<?php

session_start();
require_once __DIR__ . '/../config.php';

/* =========================
   ADMIN LOGIN CHECK
========================= */

if (
    !isset($_SESSION["admin_id"]) ||
    ($_SESSION["admin_role"] ?? '') !== "admin"
) {
    header("Location: admin-login.php");
    exit;
}


/* =========================
   DATABASE CONNECTION
========================= */

try {
    $pdo = db();

} catch (PDOException $e) {

    die("Database connection failed.");

}


/* =========================
   DELETE BANNER
========================= */

if (isset($_GET["delete"])) {

    $id = (int) $_GET["delete"];

    $stmt = $pdo->prepare(
        "DELETE FROM hero_banners WHERE id = ?"
    );

    $stmt->execute([$id]);

    header("Location: hero-banners.php?deleted=1");
    exit;
}


/* =========================
   TOGGLE STATUS
========================= */

if (isset($_GET["toggle"])) {

    $id = (int) $_GET["toggle"];

    $stmt = $pdo->prepare("
        UPDATE hero_banners
        SET status =
            CASE
                WHEN status = 'active'
                THEN 'inactive'
                ELSE 'active'
            END
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    header("Location: hero-banners.php");
    exit;
}


/* =========================
   GET BANNERS
========================= */

$stmt = $pdo->query("
    SELECT *
    FROM hero_banners
    ORDER BY position ASC, id DESC
");

$banners = $stmt->fetchAll();

?>

<!DOCTYPE html>

<html lang="bn">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Hero Banner Management</title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f6f8;
            color: #111;
        }

        .header {
            background: #111;
            color: white;
            padding: 18px 25px;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h2 {
            margin: 0;
        }

        .back {
            color: white;
            text-decoration: none;
            background: #333;
            padding: 8px 14px;
            border-radius: 6px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            padding: 25px;
        }

        .top {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .top h3 {
            margin: 0;
        }

        .add-btn {
            background: #111;
            color: white;
            text-decoration: none;
            padding: 11px 16px;
            border-radius: 7px;
            font-weight: bold;
        }

        .message {
            background: #dcfce7;
            color: #166534;
            padding: 12px 15px;
            border-radius: 7px;
            margin-bottom: 15px;
        }

        .table-box {
            background: white;
            border-radius: 10px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        th,
        td {
            padding: 13px;
            border-bottom: 1px solid #eee;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #f9fafb;
        }

        .banner-img {
            width: 140px;
            height: 70px;
            object-fit: cover;
            border-radius: 7px;
            background: #eee;
        }

        .no-image {
            width: 140px;
            height: 70px;
            background: #eee;
            border-radius: 7px;

            display: flex;
            justify-content: center;
            align-items: center;

            color: #777;
            font-size: 13px;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .active {
            background: #dcfce7;
            color: #166534;
        }

        .inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .action {
            text-decoration: none;
            padding: 7px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
        }

        .edit {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .toggle {
            background: #fef3c7;
            color: #92400e;
        }

        .delete {
            background: #fee2e2;
            color: #b91c1c;
        }

        .empty {
            text-align: center;
            padding: 50px 20px;
            color: #777;
        }

        @media (max-width: 600px) {

            .container {
                padding: 15px;
            }

            .top {
                flex-direction: column;
                align-items: flex-start;
            }

            .header {
                padding: 15px;
            }

        }

    </style>

</head>


<body>


<header class="header">

    <h2>Hero Banners</h2>

    <a href="admin-dashboard.php" class="back">
        ← Dashboard
    </a>

</header>


<div class="container">


    <div class="top">

        <h3>
            Hero Banner Management
        </h3>

        <a
            href="hero-banner-add.php"
            class="add-btn"
        >
            + Add New Banner
        </a>

    </div>


    <?php if (isset($_GET["deleted"])): ?>

        <div class="message">
            Banner সফলভাবে delete হয়েছে।
        </div>

    <?php endif; ?>


    <div class="table-box">


        <?php if (!empty($banners)): ?>


            <table>

                <thead>

                    <tr>

                        <th>Image</th>

                        <th>Title</th>

                        <th>Badge</th>

                        <th>Position</th>

                        <th>Date</th>

                        <th>Status</th>

                        <th>Action</th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach ($banners as $banner): ?>


                    <tr>


                        <td>

                            <?php if (!empty($banner["image_url"])): ?>

                                <img
                                    src="<?= htmlspecialchars(
                                        preg_match('#^(https?:)?//#i', (string)$banner["image_url"])
                                            ? $banner["image_url"]
                                            : '../' . ltrim((string)$banner["image_url"], '/')
                                    ) ?>"
                                    class="banner-img"
                                    alt="Banner"
                                >

                            <?php else: ?>

                                <div class="no-image">
                                    No Image
                                </div>

                            <?php endif; ?>

                        </td>


                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $banner["title"] ?? ""
                                ) ?>
                            </strong>

                            <?php if (!empty($banner["subtitle"])): ?>

                                <br>

                                <small>
                                    <?= htmlspecialchars(
                                        $banner["subtitle"]
                                    ) ?>
                                </small>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (!empty($banner["show_badge"])): ?>

                                <span class="status active">
                                    <?= htmlspecialchars(
                                        $banner["badge_text"] ?? "Badge"
                                    ) ?>
                                </span>

                            <?php else: ?>

                                <span class="status inactive">
                                    Hidden
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= (int) $banner["position"] ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $banner["start_date"] ?? "-"
                            );

                            echo " → ";

                            echo htmlspecialchars(
                                $banner["end_date"] ?? "-"
                            );

                            ?>

                        </td>


                        <td>

                            <?php if ($banner["status"] === "active"): ?>

                                <span class="status active">
                                    Active
                                </span>

                            <?php else: ?>

                                <span class="status inactive">
                                    Inactive
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <div class="actions">


                                <a
                                    href="hero-banner-edit.php?id=<?= (int) $banner["id"] ?>"
                                    class="action edit"
                                >
                                    Edit
                                </a>


                                <a
                                    href="hero-banners.php?toggle=<?= (int) $banner["id"] ?>"
                                    class="action toggle"
                                    onclick="return confirm('Status পরিবর্তন করতে চান?');"
                                >
                                    Toggle
                                </a>


                                <a
                                    href="hero-banners.php?delete=<?= (int) $banner["id"] ?>"
                                    class="action delete"
                                    onclick="return confirm('এই Banner টি Delete করতে চান?');"
                                >
                                    Delete
                                </a>


                            </div>

                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>

            </table>


        <?php else: ?>


            <div class="empty">

                <h3>
                    এখনো কোনো Hero Banner নেই
                </h3>

                <p>
                    প্রথম Banner তৈরি করতে
                    <strong>+ Add New Banner</strong>
                    এ ক্লিক করুন।
                </p>

            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>
