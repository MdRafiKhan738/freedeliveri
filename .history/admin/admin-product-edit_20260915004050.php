<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cloudinary.php';

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


/* PRODUCT ID */
if (
    !isset($_GET["id"]) ||
    !ctype_digit($_GET["id"]) ||
    (int)$_GET["id"] <= 0
) {
    header("Location: admin-products.php");
    exit;
}

$productId = (int)$_GET["id"];


/* HELPER */
function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/* LOAD CATEGORIES */
try {
    $categoryStmt = $pdo->query(
        "SELECT id, name FROM categories ORDER BY name ASC"
    );

    $categories = $categoryStmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}


/* LOAD PRODUCT */
$productStmt = $pdo->prepare("
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

        COALESCE(ps.sold_count, 0) AS sold_count,
        COALESCE(ps.star_rating, 0.0) AS star_rating,
        COALESCE(ps.show_sold_count, 1) AS show_sold_count,
        COALESCE(ps.show_star_rating, 1) AS show_star_rating

    FROM products p

    LEFT JOIN product_stats ps
        ON ps.product_id = p.id

    WHERE p.id = ?

    LIMIT 1
");

$productStmt->execute([$productId]);

$product = $productStmt->fetch();


if (!$product) {
    die("Product not found.");
}


/* VARIABLES */
$error = "";
$success = "";


/* SAVE PRODUCT */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");

    $categoryId = trim(
        $_POST["category_id"] ?? ""
    );

    $description = trim(
        $_POST["description"] ?? ""
    );

    $price = trim(
        $_POST["price"] ?? ""
    );

    $regularPrice = trim(
        $_POST["regular_price"] ?? ""
    );

    $stock = trim(
        $_POST["stock"] ?? ""
    );

    $soldCount = trim(
        $_POST["sold_count"] ?? "0"
    );

    $starRating = trim(
        $_POST["star_rating"] ?? "0"
    );


    /* ON / OFF */
    $showSoldCount = isset(
        $_POST["show_sold_count"]
    ) ? 1 : 0;

    $showStarRating = isset(
        $_POST["show_star_rating"]
    ) ? 1 : 0;


    $showOfferBadge = isset(
        $_POST["show_offer_badge"]
    ) ? 1 : 0;

    $isPopular = isset(
        $_POST["is_popular"]
    ) ? 1 : 0;

    $isFreeDelivery = isset(
        $_POST["is_free_delivery"]
    ) ? 1 : 0;


    $offerBadgeText = trim(
        $_POST["offer_badge_text"] ?? ""
    );

    $status = (
        isset($_POST["status"]) &&
        $_POST["status"] === "inactive"
    )
        ? "inactive"
        : "active";


    /* VALIDATION */

    if ($name === "") {

        $error = "Product name দিন।";

    } elseif (
        $price === "" ||
        !is_numeric($price) ||
        (float)$price < 0
    ) {

        $error = "সঠিক Current/Offer Price দিন।";

    } elseif (
        $regularPrice !== "" &&
        (
            !is_numeric($regularPrice) ||
            (float)$regularPrice < 0
        )
    ) {

        $error = "সঠিক Regular Price দিন।";

    } elseif (
        $stock === "" ||
        !ctype_digit($stock)
    ) {

        $error = "সঠিক Stock সংখ্যা দিন।";

    } elseif (
        $soldCount === "" ||
        !ctype_digit($soldCount)
    ) {

        $error = "Sold Count অবশ্যই পূর্ণ সংখ্যা হতে হবে।";

    } elseif (
        $starRating === "" ||
        !is_numeric($starRating) ||
        (float)$starRating < 0 ||
        (float)$starRating > 5
    ) {

        $error = "Star Rating 0 থেকে 5-এর মধ্যে হতে হবে।";

    }


    /* IMAGE UPLOAD */

    $imageUrl = (string)($product["image_url"] ?? "");
    $imageData = null;
    $imageMime = null;
$newUploadedImage = null;

/* =========================
    MYSQL PRODUCT IMAGE UPLOAD
========================= */

if (isset($_FILES["product_image"]) && is_array($_FILES["product_image"])) {

    $uploadResult = uploadImageToMysql($_FILES["product_image"]);
    if (!$uploadResult["success"]) {
        $error = $uploadResult["message"];
    } else {
        $imageData = $uploadResult["data"] ?? null;
        $imageMime = $uploadResult["mime"] ?? null;
        $imageUrl = $imageData !== null
            ? "image-api.php?type=product&id=" . $productId
            : $imageUrl;
    }
}

/* Legacy local upload code is unreachable; all new images use MySQL above. */
if (false) {

    $upload = $_FILES["product_image"];
    $uploadError = (int)($upload["error"] ?? UPLOAD_ERR_NO_FILE);

    if ($uploadError !== UPLOAD_ERR_NO_FILE) {

        $uploadErrorMessages = [
            UPLOAD_ERR_INI_SIZE   => "ছবির সাইজ server-এর upload_max_filesize সীমা অতিক্রম করেছে।",
            UPLOAD_ERR_FORM_SIZE  => "ছবির সাইজ অনুমোদিত সীমা অতিক্রম করেছে।",
            UPLOAD_ERR_PARTIAL    => "ছবি সম্পূর্ণভাবে upload হয়নি। আবার চেষ্টা করুন।",
            UPLOAD_ERR_NO_FILE    => "কোনো ছবি নির্বাচন করা হয়নি।",
            UPLOAD_ERR_NO_TMP_DIR => "Server-এর temporary upload folder পাওয়া যায়নি।",
            UPLOAD_ERR_CANT_WRITE => "Server ছবিটি disk-এ লিখতে পারছে না।",
            UPLOAD_ERR_EXTENSION  => "একটি server extension upload বন্ধ করে দিয়েছে।",
        ];

        if ($uploadError !== UPLOAD_ERR_OK) {
            $error = $uploadErrorMessages[$uploadError]
                ?? "ছবি upload করার সময় অজানা সমস্যা হয়েছে।";
        } else {

            $tmpName = (string)($upload["tmp_name"] ?? "");
            $fileSize = (int)($upload["size"] ?? 0);
            $originalName = (string)($upload["name"] ?? "");

            if ($tmpName === "" || !is_uploaded_file($tmpName)) {
                $error = "ছবির upload file সঠিকভাবে পাওয়া যায়নি। আবার চেষ্টা করুন।";
            } elseif ($fileSize <= 0) {
                $error = "নির্বাচিত ছবিটি খালি বা invalid।";
            } elseif ($fileSize > 5 * 1024 * 1024) {
                $error = "ছবির সর্বোচ্চ সাইজ 5MB।";
            } else {

                $imageInfo = @getimagesize($tmpName);

                if ($imageInfo === false) {
                    $error = "শুধু বৈধ image file upload করা যাবে।";
                } else {

                    $allowedMimeTypes = [
                        "image/jpeg" => "jpg",
                        "image/png"  => "png",
                        "image/webp" => "webp",
                    ];

                    $detectedMime = "";

                    if (function_exists("finfo_open")) {
                        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                        if ($finfo !== false) {
                            $detectedMime = (string)@finfo_file($finfo, $tmpName);
                            @finfo_close($finfo);
                        }
                    }

                    if ($detectedMime === "") {
                        $detectedMime = (string)($imageInfo["mime"] ?? "");
                    }

                    if (!isset($allowedMimeTypes[$detectedMime])) {
                        $error = "শুধু JPG, PNG অথবা WEBP ছবি upload করা যাবে।";
                    } else {

                        $extension = $allowedMimeTypes[$detectedMime];

                        /*
                         * Use the actual server filesystem path for saving.
                         * This avoids failures caused by relative filesystem paths.
                         */
                         $documentRoot = rtrim((string)($_SERVER["DOCUMENT_ROOT"] ?? ""), DIRECTORY_SEPARATOR);

                         if ($documentRoot !== "" && is_dir($documentRoot)) {
                             $uploadDir = $documentRoot
                                 . DIRECTORY_SEPARATOR . "uploads"
                                 . DIRECTORY_SEPARATOR . "products"
                                 . DIRECTORY_SEPARATOR;
                         } else {
                             $uploadDir = __DIR__
    . DIRECTORY_SEPARATOR . "uploads"
    . DIRECTORY_SEPARATOR . "products"
    . DIRECTORY_SEPARATOR;
                         }
                        

                        if (!is_dir($uploadDir)) {
                            if (!@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                                $error = "uploads/products/ folder তৈরি করা যায়নি। Server permission check করুন।";
                            }
                        }

                        if ($error === "" && !is_writable($uploadDir)) {
                            $error = "uploads/products/ folder-এ write permission নেই। Folder permission/ownership ঠিক করুন।";
                        }

                        if ($error === "") {

                            try {
                                $randomPart = bin2hex(random_bytes(12));
                            } catch (Throwable $e) {
                                $randomPart = str_replace(".", "", uniqid("", true));
                            }

                            $newFileName = "product_"
                                . (int)$product["id"]
                                . "_"
                                . $randomPart
                                . "."
                                . $extension;

                            $destination = $uploadDir . $newFileName;

                            if (!@move_uploaded_file($tmpName, $destination)) {
                                $error = "ছবি server-এ save করা যায়নি। uploads/products/ folder-এর write permission check করুন।";
                            } elseif (!is_file($destination) || filesize($destination) <= 0) {
                                @unlink($destination);
                                $error = "ছবি save করার পর file পাওয়া যায়নি। আবার চেষ্টা করুন।";
                            } else {
                                /*
                                 * Store a web path in DB, not the server filesystem path.
                                 * Existing site structure is preserved.
                                 */
                                $imageUrl = "admin/uploads/products/" . $newFileName;
                                $newUploadedImage = $destination;
                            }
                        }
                    }
                }
            }
        }
    }
}

if ($error !== "") {
    /*
     * Do not continue to DB update when image upload failed.
     * If a new file was somehow created before a later failure, clean it up.
     */
    if ($newUploadedImage !== null && is_file($newUploadedImage)) {
        @unlink($newUploadedImage);
        $newUploadedImage = null;
    }
} else {

try {

            $pdo->beginTransaction();


            /* UPDATE PRODUCTS */

            $updateProduct = $pdo->prepare("
                UPDATE products

                SET
                    category_id = ?,
                    name = ?,
                    description = ?,
                    price = ?,
                    regular_price = ?,
                    stock = ?,
                    image_url = ?,
                    image_data = ?,
                    image_mime = ?,
                    status = ?,
                    is_popular = ?,
                    is_free_delivery = ?,
                    show_offer_badge = ?,
                    offer_badge_text = ?

                WHERE id = ?
            ");


            $regularPriceValue =
                $regularPrice === ""
                    ? null
                    : (float)$regularPrice;


            $updateProduct->execute([
                $categoryId === ""
                    ? null
                    : (int)$categoryId,

                $name,

                $description === ""
                    ? null
                    : $description,

                (float)$price,

                $regularPriceValue,

                (int)$stock,

                $imageUrl === ""
                    ? null
                    : $imageUrl,

                $imageData,
                $imageMime,

                $status,

                $isPopular,

                $isFreeDelivery,

                $showOfferBadge,

                $offerBadgeText === ""
                    ? null
                    : $offerBadgeText,

                $productId
            ]);


            /* PRODUCT STATS */

            $statsCheck = $pdo->prepare("
                SELECT id
                FROM product_stats
                WHERE product_id = ?
                LIMIT 1
            ");

            $statsCheck->execute([
                $productId
            ]);

            $statsExists =
                $statsCheck->fetch();


            if ($statsExists) {

                $updateStats = $pdo->prepare("
                    UPDATE product_stats

                    SET
                        sold_count = ?,
                        star_rating = ?,
                        show_sold_count = ?,
                        show_star_rating = ?

                    WHERE product_id = ?
                ");

                $updateStats->execute([
                    (int)$soldCount,
                    (float)$starRating,
                    $showSoldCount,
                    $showStarRating,
                    $productId
                ]);

            } else {

                $insertStats = $pdo->prepare("
                    INSERT INTO product_stats (
                        product_id,
                        sold_count,
                        star_rating,
                        show_sold_count,
                        show_star_rating
                    )

                    VALUES (?, ?, ?, ?, ?)
                ");

                $insertStats->execute([
                    $productId,
                    (int)$soldCount,
                    (float)$starRating,
                    $showSoldCount,
                    $showStarRating
                ]);
            }


            $pdo->commit();


            header(
                "Location: admin-product-edit.php?id=" .
                $productId .
                "&saved=1"
            );

            exit;


        } catch (PDOException $e) {

            if (
                $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            $error =
                "Product Save করা যায়নি। Database error হয়েছে।";
        }
    }
}


/* RELOAD PRODUCT AFTER SAVE */
if (isset($_GET["saved"])) {

    $productStmt->execute([
        $productId
    ]);

    $product =
        $productStmt->fetch();

    $success =
        "Product সফলভাবে Save হয়েছে।";
}

?>
    }
}

<!DOCTYPE html>
<html lang="bn">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Edit Product
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f5f7fb;
    font-family:
        Arial,
        "Noto Sans Bengali",
        sans-serif;
    color: #1f2937;
}

.topbar {
    background: #111827;
    color: white;
    padding: 16px 20px;
}

.topbar-inner {
    max-width: 1100px;
    margin: auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.topbar h1 {
    margin: 0;
    font-size: 20px;
}

.back-link {
    color: white;
    text-decoration: none;
    background: #374151;
    padding: 9px 14px;
    border-radius: 8px;
}

.container {
    max-width: 1100px;
    margin: 25px auto;
    padding: 0 15px;
}

.card {
    background: white;
    border-radius: 14px;
    padding: 22px;
    box-shadow:
        0 5px 20px rgba(0,0,0,.07);
}

h2 {
    margin-top: 0;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    font-weight: 700;
    margin-bottom: 7px;
}

input,
textarea,
select {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 15px;
}

textarea {
    min-height: 120px;
    resize: vertical;
}

.grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.option-box {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 15px;
    background: #fafafa;
}

.switch-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.switch-row strong {
    font-size: 16px;
}

.switch {
    position: relative;
    width: 52px;
    height: 28px;
}

.switch input {
    display: none;
}

.slider {
    position: absolute;
    inset: 0;
    background: #cbd5e1;
    border-radius: 30px;
    cursor: pointer;
    transition: .2s;
}

.slider:before {
    content: "";
    position: absolute;
    width: 22px;
    height: 22px;
    left: 3px;
    top: 3px;
    background: white;
    border-radius: 50%;
    transition: .2s;
}

.switch input:checked + .slider {
    background: #16a34a;
}

.switch input:checked + .slider:before {
    transform: translateX(24px);
}

.conditional-input {
    margin-top: 14px;
}

.hidden {
    display: none;
}

.image-preview {
    max-width: 220px;
    max-height: 220px;
    object-fit: contain;
    border-radius: 10px;
    border: 1px solid #ddd;
    margin-bottom: 12px;
}

.message {
    padding: 13px 15px;
    border-radius: 8px;
    margin-bottom: 18px;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

.actions {
    display: flex;
    gap: 10px;
    margin-top: 25px;
}

.save-btn {
    border: 0;
    background: #2563eb;
    color: white;
    padding: 13px 22px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
}

.cancel-btn {
    background: #e5e7eb;
    color: #111827;
    padding: 13px 22px;
    border-radius: 8px;
    text-decoration: none;
}

.small-note {
    color: #6b7280;
    font-size: 13px;
    margin-top: 6px;
}

@media (max-width: 700px) {

    .grid {
        grid-template-columns: 1fr;
    }

    .card {
        padding: 16px;
    }

    .topbar-inner {
        flex-direction: column;
        align-items: flex-start;
    }

}

</style>

</head>

<body>

<header class="topbar">

    <div class="topbar-inner">

        <h1>
            ✏️ Product Edit
        </h1>

        <a
            href="admin-products.php"
            class="back-link"
        >
            ← Product List
        </a>

    </div>

</header>

<main class="container">

<div class="card">

<h2>
    Product Edit
</h2>

<?php if ($success): ?>

    <div class="message success">
        <?= e($success) ?>
    </div>

<?php endif; ?>

<?php if ($error): ?>

    <div class="message error">
        <?= e($error) ?>
    </div>

<?php endif; ?>

<form
    method="POST"
    enctype="multipart/form-data"
    id="productEditForm"
>
    
        <div class="grid">

        <div class="form-group">

            <label>
                Product Name *
            </label>

            <input
                type="text"
                name="name"
                value="<?= e($product["name"]) ?>"
                required
            >

        </div>


        <div class="form-group">

            <label>
                Category
            </label>

            <select name="category_id">

                <option value="">
                    -- Category নির্বাচন করুন --
                </option>

                <?php foreach ($categories as $category): ?>

                    <option
                        value="<?= (int)$category["id"] ?>"
                        <?= (
                            (string)$product["category_id"] ===
                            (string)$category["id"]
                        )
                            ? "selected"
                            : ""
                        ?>
                    >

                        <?= e($category["name"]) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>

    </div>


    <div class="form-group">

        <label>
            Description
        </label>

        <textarea
            name="description"
        ><?= e($product["description"]) ?></textarea>

    </div>


    <div class="grid">

        <div class="form-group">

            <label>
                Current / Offer Price *
            </label>

            <input
                type="number"
                name="price"
                step="0.01"
                min="0"
                value="<?= e($product["price"]) ?>"
                required
            >

        </div>


        <div class="form-group">

            <label>
                Regular / Original Price
            </label>

            <input
                type="number"
                name="regular_price"
                step="0.01"
                min="0"
                value="<?= e($product["regular_price"]) ?>"
            >

        </div>

    </div>


    <!-- IMAGE -->

    <div class="form-group">

        <label>
            Product Image
        </label>

        <?php if (!empty($product["image_url"])): ?>

            <img
                src="<?= e((preg_match("~^(?:https?:)?//|^/~", (string)$product["image_url"]) ? $product["image_url"] : "../" . ltrim((string)$product["image_url"], "/"))) ?>"
                class="image-preview"
                id="imagePreview"
                alt="Product Image"
            >

        <?php else: ?>

            <div
                id="noImageText"
                class="small-note"
            >
                বর্তমানে কোনো Image নেই।
            </div>

            <img
                id="imagePreview"
                class="image-preview hidden"
                alt="Preview"
            >

        <?php endif; ?>


        <input
            type="file"
            name="product_image"
            id="productImage"
            accept="image/jpeg,image/png,image/webp"
        >

        <div class="small-note">
            JPG, PNG অথবা WEBP • সর্বোচ্চ 5MB
        </div>

    </div>


    <div class="grid">

        <div class="form-group">

            <label>
                Stock *
            </label>

            <input
                type="number"
                name="stock"
                min="0"
                value="<?= e($product["stock"]) ?>"
                required
            >

        </div>


        <div class="form-group">

            <label>
                Status
            </label>

            <select name="status">

                <option
                    value="active"
                    <?= $product["status"] === "active"
                        ? "selected"
                        : ""
                    ?>
                >
                    Active
                </option>

                <option
                    value="inactive"
                    <?= $product["status"] === "inactive"
                        ? "selected"
                        : ""
                    ?>
                >
                    Inactive
                </option>

            </select>

        </div>

    </div>


    <!-- SOLD COUNT -->

    <div class="option-box">

        <div class="switch-row">

            <strong>
                Sold Count দেখাবেন?
            </strong>

            <label class="switch">

                <input
                    type="checkbox"
                    name="show_sold_count"
                    id="showSoldCount"
                    value="1"
                    <?= (int)$product["show_sold_count"] === 1
                        ? "checked"
                        : ""
                    ?>
                >

                <span class="slider"></span>

            </label>

        </div>


        <div
            id="soldCountBox"
            class="conditional-input
            <?= (int)$product["show_sold_count"] === 1
                ? ""
                : "hidden"
            ?>"
        >

            <label>
                Sold Count
            </label>

            <input
                type="number"
                name="sold_count"
                min="0"
                value="<?= e($product["sold_count"]) ?>"
            >

            <div class="small-note">
                ON করলে Frontend-এ এই সংখ্যা দেখাবে।
            </div>

        </div>

    </div>


    <br>


    <!-- STAR RATING -->

    <div class="option-box">

        <div class="switch-row">

            <strong>
                Star Rating দেখাবেন?
            </strong>

            <label class="switch">

                <input
                    type="checkbox"
                    name="show_star_rating"
                    id="showStarRating"
                    value="1"
                    <?= (int)$product["show_star_rating"] === 1
                        ? "checked"
                        : ""
                    ?>
                >

                <span class="slider"></span>

            </label>

        </div>


        <div
            id="starRatingBox"
            class="conditional-input
            <?= (int)$product["show_star_rating"] === 1
                ? ""
                : "hidden"
            ?>"
        >

            <label>
                Star Rating
            </label>

            <input
                type="number"
                name="star_rating"
                min="0"
                max="5"
                step="0.1"
                value="<?= e($product["star_rating"]) ?>"
            >

            <div class="small-note">
                0 থেকে 5-এর মধ্যে Rating দিন।
            </div>

        </div>

    </div>


    <br>


    <!-- OFFER BADGE -->

    <div class="option-box">

        <div class="switch-row">

            <strong>
                Offer Badge দেখাবেন?
            </strong>

            <label class="switch">

                <input
                    type="checkbox"
                    name="show_offer_badge"
                    id="showOfferBadge"
                    value="1"
                    <?= (int)$product["show_offer_badge"] === 1
                        ? "checked"
                        : ""
                    ?>
                >

                <span class="slider"></span>

            </label>

        </div>


        <div
            id="offerBadgeBox"
            class="conditional-input
            <?= (int)$product["show_offer_badge"] === 1
                ? ""
                : "hidden"
            ?>"
        >

            <label>
                Offer Badge Text
            </label>

            <input
                type="text"
                name="offer_badge_text"
                value="<?= e($product["offer_badge_text"]) ?>"
                placeholder="যেমন: 20% OFF"
            >

        </div>

    </div>


    <br>


    <div class="grid">

        <!-- POPULAR -->

        <div class="option-box">

            <div class="switch-row">

                <strong>
                    Popular Product
                </strong>

                <label class="switch">

                    <input
                        type="checkbox"
                        name="is_popular"
                        value="1"
                        <?= (int)$product["is_popular"] === 1
                            ? "checked"
                            : ""
                        ?>
                    >

                    <span class="slider"></span>

                </label>

            </div>

        </div>


        <!-- FREE DELIVERY -->

        <div class="option-box">

            <div class="switch-row">

                <strong>
                    Free Delivery
                </strong>

                <label class="switch">

                    <input
                        type="checkbox"
                        name="is_free_delivery"
                        value="1"
                        <?= (int)$product["is_free_delivery"] === 1
                            ? "checked"
                            : ""
                        ?>
                    >

                    <span class="slider"></span>

                </label>

            </div>

        </div>

    </div>


    <div class="actions">

        <button
            type="submit"
            class="save-btn"
        >
            💾 Save Changes
        </button>


        <a
            href="admin-products.php"
            class="cancel-btn"
        >
            Cancel
        </a>

    </div>


</form>

</div>

</main>


<script>

const showSoldCount =
    document.getElementById(
        "showSoldCount"
    );

const soldCountBox =
    document.getElementById(
        "soldCountBox"
    );


const showStarRating =
    document.getElementById(
        "showStarRating"
    );

const starRatingBox =
    document.getElementById(
        "starRatingBox"
    );


const showOfferBadge =
    document.getElementById(
        "showOfferBadge"
    );

const offerBadgeBox =
    document.getElementById(
        "offerBadgeBox"
    );


function updateSoldCountBox() {

    if (
        showSoldCount &&
        soldCountBox
    ) {

        soldCountBox.classList.toggle(
            "hidden",
            !showSoldCount.checked
        );

    }

}


function updateStarRatingBox() {

    if (
        showStarRating &&
        starRatingBox
    ) {

        starRatingBox.classList.toggle(
            "hidden",
            !showStarRating.checked
        );

    }

}


function updateOfferBadgeBox() {

    if (
        showOfferBadge &&
        offerBadgeBox
    ) {

        offerBadgeBox.classList.toggle(
            "hidden",
            !showOfferBadge.checked
        );

    }

}


if (showSoldCount) {

    showSoldCount.addEventListener(
        "change",
        updateSoldCountBox
    );

}


if (showStarRating) {

    showStarRating.addEventListener(
        "change",
        updateStarRatingBox
    );

}


if (showOfferBadge) {

    showOfferBadge.addEventListener(
        "change",
        updateOfferBadgeBox
    );

}


/* IMAGE PREVIEW */

const productImage =
    document.getElementById(
        "productImage"
    );

const imagePreview =
    document.getElementById(
        "imagePreview"
    );

const noImageText =
    document.getElementById(
        "noImageText"
    );


if (productImage) {

    productImage.addEventListener(
        "change",
        function () {

            const file =
                this.files &&
                this.files[0];

            if (!file) {
                return;
            }

            const allowedTypes = [
                "image/jpeg",
                "image/png",
                "image/webp"
            ];

            if (
                !allowedTypes.includes(
                    file.type
                )
            ) {

                alert(
                    "শুধু JPG, PNG অথবা WEBP image নির্বাচন করুন।"
                );

                this.value = "";

                return;
            }


            if (
                file.size >
                5 * 1024 * 1024
            ) {

                alert(
                    "Image সর্বোচ্চ 5MB হতে পারবে।"
                );

                this.value = "";

                return;
            }


            const reader =
                new FileReader();


            reader.onload =
                function (event) {

                    if (imagePreview) {

                        imagePreview.src =
                            event.target.result;

                        imagePreview.classList.remove(
                            "hidden"
                        );

                    }

                    if (noImageText) {

                        noImageText.style.display =
                            "none";

                    }

                };


            reader.readAsDataURL(file);

        }
    );

}

</script>

</body>

</html>

