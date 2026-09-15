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

try {
    $pdo = db();
} catch (PDOException $e) {
    die("Database connection failed.");
}


/* ================= DELETE PRODUCT ================= */

if (
    isset($_GET["delete"]) &&
    ctype_digit($_GET["delete"])
) {
    $productId = (int) $_GET["delete"];

    $stmt = $pdo->prepare(
        "DELETE FROM products WHERE id = ?"
    );

    $stmt->execute([$productId]);

    header("Location: admin-products.php?deleted=1");
    exit;
}


/* ================= CHANGE STATUS ================= */

if (
    isset($_GET["status"]) &&
    isset($_GET["id"]) &&
    ctype_digit($_GET["id"])
) {
    $productId = (int) $_GET["id"];

    $newStatus =
        $_GET["status"] === "active"
        ? "active"
        : "inactive";

    $stmt = $pdo->prepare(
        "UPDATE products
         SET status = ?
         WHERE id = ?"
    );

    $stmt->execute([
        $newStatus,
        $productId
    ]);

    header("Location: admin-products.php?updated=1");
    exit;
}


/* ================= FILTERS ================= */

$search =
    isset($_GET["search"])
    ? trim($_GET["search"])
    : "";

$categoryId =
    isset($_GET["category"])
    ? trim($_GET["category"])
    : "";

$status =
    isset($_GET["status_filter"])
    ? trim($_GET["status_filter"])
    : "";


/* ================= CATEGORIES ================= */

$categoryStmt = $pdo->query(
    "SELECT id, name
     FROM categories
     ORDER BY name ASC"
);

$categories = $categoryStmt->fetchAll();


/* ================= PRODUCTS ================= */

$sql = "
    SELECT
        p.id,
        p.category_id,
        p.name,
        p.slug,
        p.description,
        p.price,
        p.regular_price,
        p.stock,
        p.image_url,
        p.status,
        p.is_popular,
        p.is_free_delivery,
        p.show_offer_badge,
        p.offer_badge_text,
        p.created_at,

        c.name AS category_name,

        COALESCE(ps.sold_count, 0) AS sold_count,
        COALESCE(ps.star_rating, 0.0) AS star_rating

    FROM products p

    LEFT JOIN categories c
        ON c.id = p.category_id

    LEFT JOIN product_stats ps
        ON ps.product_id = p.id

    WHERE 1 = 1
";

$params = [];


/* SEARCH */

if ($search !== "") {

    $sql .= "
        AND (
            p.name LIKE ?
            OR p.slug LIKE ?
            OR p.description LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/* CATEGORY */

if (
    $categoryId !== "" &&
    ctype_digit($categoryId)
) {

    $sql .= "
        AND p.category_id = ?
    ";

    $params[] = (int) $categoryId;
}


/* STATUS */

if (
    $status === "active" ||
    $status === "inactive"
) {

    $sql .= "
        AND p.status = ?
    ";

    $params[] = $status;
}


$sql .= "
    ORDER BY p.id DESC
";


$productStmt = $pdo->prepare($sql);

$productStmt->execute($params);

$products = $productStmt->fetchAll();


/* ================= HELPER ================= */

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>
<!DOCTYPE html>

<html lang="bn">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Products - Admin Panel</title>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f6f7f9;
            color: #111827;
            font-family:
                "Noto Sans Bengali",
                "Noto Sans",
                Arial,
                sans-serif;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button,
        input,
        select {
            font-family: inherit;
        }

        .topbar {
            background: #111827;
            color: white;
            padding: 15px 20px;
        }

        .topbar-inner {
            max-width: 1400px;
            margin: auto;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 15px;
        }

        .brand {
            font-size: 20px;
            font-weight: 800;
        }

        .brand span {
            color: #22c55e;
        }

        .back-btn {
            background: rgba(255,255,255,.1);
            color: white;

            padding: 9px 13px;
            border-radius: 8px;

            font-size: 13px;
        }

        .container {
            max-width: 1400px;
            margin: auto;
            padding: 25px 18px 50px;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 15px;
            margin-bottom: 20px;
        }

        .page-header h1 {
            margin: 0 0 5px;
            font-size: 25px;
        }

        .page-header p {
            margin: 0;
            color: #6b7280;
            font-size: 13px;
        }

        .add-btn {
            background: #16a34a;
            color: white;

            border: 0;
            border-radius: 9px;

            padding: 11px 16px;

            font-weight: 700;
            cursor: pointer;
        }

        .message {
            background: #dcfce7;
            color: #166534;

            padding: 12px 14px;
            border-radius: 9px;

            margin-bottom: 18px;

            font-size: 13px;
        }

        .filter-box {
            background: white;

            border: 1px solid #e5e7eb;
            border-radius: 12px;

            padding: 15px;

            margin-bottom: 20px;
        }

        .filter-form {
            display: grid;

            grid-template-columns:
                1.5fr
                1fr
                1fr
                auto;

            gap: 10px;
        }

        .filter-form input,
        .filter-form select {
            width: 100%;

            height: 42px;

            border: 1px solid #d1d5db;
            border-radius: 8px;

            padding: 0 12px;

            outline: none;
            background: white;
        }

        .filter-btn {
            height: 42px;

            border: 0;
            border-radius: 8px;

            background: #111827;
            color: white;

            padding: 0 18px;

            cursor: pointer;
            font-weight: 700;
        }

        .reset-btn {
            display: inline-flex;

            height: 42px;
            align-items: center;

            padding: 0 15px;

            border: 1px solid #d1d5db;
            border-radius: 8px;

            color: #374151;

            margin-left: 7px;
        }

        .table-card {
            background: white;

            border: 1px solid #e5e7eb;
            border-radius: 12px;

            overflow: hidden;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1100px;

            border-collapse: collapse;
        }

        th {
            background: #f9fafb;

            color: #6b7280;

            font-size: 11px;
            font-weight: 700;

            text-align: left;

            padding: 13px 12px;

            border-bottom: 1px solid #e5e7eb;
        }

        td {
            padding: 13px 12px;

            border-bottom: 1px solid #f0f1f3;

            font-size: 13px;

            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .product-image {
            width: 58px;
            height: 58px;

            object-fit: cover;

            border-radius: 9px;

            background: #f3f4f6;

            display: block;
        }

        .no-image {
            width: 58px;
            height: 58px;

            border-radius: 9px;

            background: #f3f4f6;

            display: flex;
            align-items: center;
            justify-content: center;

            color: #9ca3af;

            font-size: 11px;
            text-align: center;
        }

        .product-name {
            font-weight: 700;
            color: #111827;

            max-width: 230px;
        }

        .category {
            color: #6b7280;
            font-size: 12px;
        }

        .price-current {
            font-weight: 800;
            color: #111827;
        }

        .price-regular {
            display: block;

            color: #9ca3af;

            text-decoration: line-through;

            font-size: 11px;

            margin-top: 2px;
        }

        .badge {
            display: inline-block;

            padding: 4px 7px;

            border-radius: 5px;

            font-size: 10px;
            font-weight: 700;
        }

        .badge-green {
            background: #dcfce7;
            color: #166534;
        }

        .badge-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-gray {
            background: #f3f4f6;
            color: #4b5563;
        }

        .badge-blue {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .rating {
            color: #f59e0b;
            font-weight: 700;
        }

        .actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .action {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            padding: 7px 9px;

            border-radius: 7px;

            font-size: 11px;
            font-weight: 700;
        }

        .edit {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .activate {
            background: #dcfce7;
            color: #166534;
        }

        .deactivate {
            background: #fff7ed;
            color: #c2410c;
        }

        .delete {
            background: #fee2e2;
            color: #b91c1c;
        }

        .empty {
            padding: 45px 20px;

            text-align: center;

            color: #6b7280;
        }

        .empty strong {
            display: block;

            color: #374151;

            margin-bottom: 5px;
        }

        @media (max-width: 800px) {

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .filter-btn,
            .reset-btn {
                width: 100%;
                justify-content: center;
                margin-left: 0;
            }

            .container {
                padding: 20px 12px 45px;
            }

        }

    </style>

</head>


<body>


<header class="topbar">

    <div class="topbar-inner">

        <div class="brand">
            Free<span>Delivery</span> Admin
        </div>

        <a
            class="back-btn"
            href="admin-dashboard.php">

            ← Dashboard

        </a>

    </div>

</header>


<main class="container">


    <div class="page-header">

        <div>

            <h1>পণ্যসমূহ</h1>

            <p>
                আপনার সব Product এখান থেকে পরিচালনা করুন।
            </p>

        </div>


        <a
            class="add-btn"
            href="admin-product-add.php">

            + নতুন Product যোগ করুন

        </a>

    </div>


    <?php if (isset($_GET["deleted"])): ?>

        <div class="message">
            Product সফলভাবে মুছে ফেলা হয়েছে।
        </div>

    <?php endif; ?>


    <?php if (isset($_GET["updated"])): ?>

        <div class="message">
            Product status সফলভাবে পরিবর্তন হয়েছে।
        </div>

    <?php endif; ?>


    <section class="filter-box">

        <form
            class="filter-form"
            method="GET">

            <input
                type="text"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Product name দিয়ে খুঁজুন...">


            <select name="category">

                <option value="">
                    সব Category
                </option>

                <?php foreach ($categories as $category): ?>

                    <option
                        value="<?= (int) $category["id"] ?>"
                        <?= (
                            (string)$categoryId ===
                            (string)$category["id"]
                        ) ? "selected" : "" ?>>

                        <?= e($category["name"]) ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <select name="status_filter">

                <option value="">
                    সব Status
                </option>

                <option
                    value="active"
                    <?= $status === "active"
                        ? "selected"
                        : "" ?>>

                    Active

                </option>

                <option
                    value="inactive"
                    <?= $status === "inactive"
                        ? "selected"
                        : "" ?>>

                    Inactive

                </option>

            </select>


            <div>

                <button
                    class="filter-btn"
                    type="submit">

                    Search

                </button>


                <a
                    class="reset-btn"
                    href="admin-products.php">

                    Reset

                </a>

            </div>

        </form>

    </section>
    <section class="table-card">

        <?php if (!$products): ?>

            <div class="empty">

                <strong>
                    কোনো Product পাওয়া যায়নি।
                </strong>

                Search বা Filter পরিবর্তন করে আবার চেষ্টা করুন।

            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>Product</th>

                            <th>Category</th>

                            <th>Price</th>

                            <th>Stock</th>

                            <th>Sold</th>

                            <th>Rating</th>

                            <th>Features</th>

                            <th>Status</th>

                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($products as $product): ?>

                        <tr>


                            <td>

                                <div
                                    style="
                                        display:flex;
                                        gap:10px;
                                        align-items:center;
                                    ">

                                    <?php if (
                                        !empty($product["image_url"])
                                    ): ?>

                                        <img
                                            class="product-image"
                                            src="<?= e(preg_match("~^(?:https?:)?//|^/~", (string)$product["image_url"]) ? $product["image_url"] : "../" . ltrim((string)$product["image_url"], "/")) ?>"
                                            alt="<?= e($product["name"]) ?>">

                                    <?php else: ?>

                                        <div class="no-image">
                                            No Image
                                        </div>

                                    <?php endif; ?>


                                    <div>

                                        <div class="product-name">

                                            <?= e($product["name"]) ?>

                                        </div>

                                        <div
                                            style="
                                                color:#9ca3af;
                                                font-size:10px;
                                                margin-top:3px;
                                            ">

                                            ID:
                                            <?= (int) $product["id"] ?>

                                        </div>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <span class="category">

                                    <?= e(
                                        $product["category_name"]
                                        ?: "কোনো Category নেই"
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span class="price-current">

                                    ৳
                                    <?= number_format(
                                        (float)$product["price"],
                                        2
                                    ) ?>

                                </span>


                                <?php if (
                                    $product["regular_price"] !== null &&
                                    (float)$product["regular_price"] >
                                    (float)$product["price"]
                                ): ?>

                                    <span class="price-regular">

                                        ৳
                                        <?= number_format(
                                            (float)$product["regular_price"],
                                            2
                                        ) ?>

                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if (
                                    (int)$product["stock"] <= 0
                                ): ?>

                                    <span class="badge badge-red">
                                        Out of Stock
                                    </span>

                                <?php elseif (
                                    (int)$product["stock"] <= 5
                                ): ?>

                                    <span class="badge badge-gray">

                                        <?= (int)$product["stock"] ?>
                                        left

                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-green">

                                        <?= (int)$product["stock"] ?>

                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= (int)$product["sold_count"] ?>

                            </td>


                            <td>

                                <span class="rating">

                                    ★
                                    <?= number_format(
                                        (float)$product["star_rating"],
                                        1
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <div
                                    style="
                                        display:flex;
                                        flex-wrap:wrap;
                                        gap:4px;
                                    ">


                                    <?php if (
                                        (int)$product["show_offer_badge"] === 1
                                    ): ?>

                                        <span class="badge badge-red">

                                            <?= e(
                                                $product["offer_badge_text"]
                                                ?: "Offer"
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        (int)$product["is_popular"] === 1
                                    ): ?>

                                        <span class="badge badge-blue">
                                            Popular
                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        (int)$product["is_free_delivery"] === 1
                                    ): ?>

                                        <span class="badge badge-green">
                                            Free Delivery
                                        </span>

                                    <?php endif; ?>


                                </div>

                            </td>


                            <td>

                                <?php if (
                                    $product["status"] === "active"
                                ): ?>

                                    <span class="badge badge-green">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-gray">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="actions">


                                    <a
                                        class="action edit"
                                        href="admin-product-edit.php?id=<?= (int)$product["id"] ?>">

                                        Edit

                                    </a>


                                    <?php if (
                                        $product["status"] === "active"
                                    ): ?>

                                        <a
                                            class="action deactivate"
                                            href="admin-products.php?id=<?= (int)$product["id"] ?>&status=inactive"
                                            onclick="
                                                return confirm(
                                                    'এই Product-টি Inactive করতে চান?'
                                                );
                                            ">

                                            Inactive

                                        </a>

                                    <?php else: ?>

                                        <a
                                            class="action activate"
                                            href="admin-products.php?id=<?= (int)$product["id"] ?>&status=active">

                                            Active

                                        </a>

                                    <?php endif; ?>


                                    <a
                                        class="action delete"
                                        href="admin-products.php?delete=<?= (int)$product["id"] ?>"
                                        onclick="
                                            return confirm(
                                                'সতর্কতা! এই Product স্থায়ীভাবে Delete করতে চান?'
                                            );
                                        ">

                                        Delete

                                    </a>


                                </div>

                            </td>


                        </tr>

                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>


</main>


</body>
<script>if (window.lucide) lucide.createIcons();</script>

</html>
    
