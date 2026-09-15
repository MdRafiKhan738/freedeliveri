<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cloudinary.php';

/*
|--------------------------------------------------------------------------
| ADMIN SECURITY
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION["admin_id"]) ||
    !isset($_SESSION["admin_role"]) ||
    $_SESSION["admin_role"] !== "admin"
) {
    header("Location: admin-login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/
try {
    $pdo = db();
} catch (PDOException $e) {
    http_response_code(500);
    exit("Database connection failed.");
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function makeSlug(string $text): string
{
    $text = trim($text);
    if ($text === "") {
        return "";
    }

    $slug = strtolower($text);
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    $slug = trim($slug, '-');

    return $slug;
}

function uploadProductImage(array $file): array
{
    if (!isset($file["error"]) || $file["error"] === UPLOAD_ERR_NO_FILE) {
        return [
            "success" => true,
            "path" => "",
            "absolute_path" => ""
        ];
    }

    if ($file["error"] !== UPLOAD_ERR_OK) {
        return [
            "success" => false,
            "message" => "Image upload failed. Please try again."
        ];
    }

    if (!is_uploaded_file($file["tmp_name"])) {
        return [
            "success" => false,
            "message" => "Invalid uploaded image."
        ];
    }

    $maxSize = 5 * 1024 * 1024;

    if ((int)$file["size"] <= 0 || (int)$file["size"] > $maxSize) {
        return [
            "success" => false,
            "message" => "Image size must be between 1 byte and 5MB."
        ];
    }

    $imageInfo = @getimagesize($file["tmp_name"]);

    if ($imageInfo === false || empty($imageInfo["mime"])) {
        return [
            "success" => false,
            "message" => "Please upload a valid image."
        ];
    }

    $allowedMimeTypes = [
        "image/jpeg",
        "image/png",
        "image/jpg",
        "image/.gif",
        "image/webp"
    ];

    if (!in_array($imageInfo["mime"], $allowedMimeTypes, true)) {
        return [
            "success" => false,
            "message" => "Only JPG, JPEG, PNG and WEBP images are allowed."
        ];
    }

    $extensionMap = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp"
    ];

    $extension = $extensionMap[$imageInfo["mime"]] ?? "";

    if ($extension === "") {
        return [
            "success" => false,
            "message" => "Unsupported image format."
        ];
    }

    $uploadDirectory = __DIR__ . "/uploads/products/";
    $relativeDirectory = "uploads/products/";

    if (!is_dir($uploadDirectory)) {
        if (!@mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            return [
                "success" => false,
                "message" => "Could not create the product image upload folder."
            ];
        }
    }

    if (!is_writable($uploadDirectory)) {
        return [
            "success" => false,
            "message" => "The product image upload folder is not writable."
        ];
    }

    try {
        $randomName = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $randomName = uniqid("product_", true);
    }

    $fileName = $randomName . "." . $extension;
    $absolutePath = $uploadDirectory . $fileName;
    $relativePath = $relativeDirectory . $fileName;

    if (!move_uploaded_file($file["tmp_name"], $absolutePath)) {
        return [
            "success" => false,
            "message" => "Could not save the uploaded image."
        ];
    }

    return [
        "success" => true,
        "path" => $relativePath,
        "absolute_path" => $absolutePath
    ];
}

function deleteUploadedProductImage(string $absolutePath): void
{
    if ($absolutePath !== "" && is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

/*
|--------------------------------------------------------------------------
| CATEGORY LOAD
|--------------------------------------------------------------------------
*/
$categories = [];
$categoryError = "";

try {
    $categoryStmt = $pdo->query("
        SELECT id, name
        FROM categories
        ORDER BY name ASC
    ");
    $categories = $categoryStmt->fetchAll();

    /*
     * আপনার ওয়েবসাইটের homepage-এ থাকা category-গুলো আগে static ছিল।
     * categories table যদি একেবারে empty থাকে, তাহলে product add page
     * যেন blank dropdown না দেখায়, missing category-গুলো একবার তৈরি করবে।
     * Existing category থাকলে duplicate তৈরি করবে না।
     */
    if (count($categories) === 0) {
        $defaultCategories = [
            "ম্যাসেজ গান",
            "কিচেন আইটেম",
            "গ্যাজেট",
            "এয়ারফোন",
            "কসমেটিকস",
            "Woman Fashion",
            "ইস্তারি"
        ];

        $insertCategory = $pdo->prepare("
            INSERT INTO categories (name)
            SELECT ?
            WHERE NOT EXISTS (
                SELECT 1 FROM categories WHERE name = ? LIMIT 1
            )
        ");

        foreach ($defaultCategories as $categoryName) {
            $insertCategory->execute([$categoryName, $categoryName]);
        }

        $categoryStmt = $pdo->query("
            SELECT id, name
            FROM categories
            ORDER BY name ASC
        ");
        $categories = $categoryStmt->fetchAll();
    }
} catch (PDOException $e) {
    $categoryError = "Category data could not be loaded. Please check the categories table.";
}

/*
|--------------------------------------------------------------------------
| PRODUCT STATS
|--------------------------------------------------------------------------
| product_stats already contains:
| sold_count, show_sold_count, star_rating, show_star_rating
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| DEFAULT FORM VALUES
|--------------------------------------------------------------------------
*/
$error = "";

$name = "";
$category_id = "";
$slug = "";
$description = "";

$regular_price = "";
$price = "";
$stock = "0";

$sold_count = "0";
$star_rating = "0.0";

$show_sold_count = 1;
$show_star_rating = 1;

$show_offer_badge = 0;
$offer_badge_text = "";

$is_popular = 0;
$is_free_delivery = 0;

$status = "active";
$image_url = "";
$image_data = null;
$image_mime = null;
$product_media = [];

$uploadedAbsolutePath = "";

/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $category_id = trim($_POST["category_id"] ?? "");
    $slug = trim($_POST["slug"] ?? "");
    $description = trim($_POST["description"] ?? "");

    $regular_price = trim($_POST["regular_price"] ?? "");
    $price = trim($_POST["price"] ?? "");
    $stock = trim($_POST["stock"] ?? "0");

    $sold_count = trim($_POST["sold_count"] ?? "0");
    $star_rating = trim($_POST["star_rating"] ?? "0.0");

    $show_sold_count = isset($_POST["show_sold_count"]) ? 1 : 0;
    $show_star_rating = isset($_POST["show_star_rating"]) ? 1 : 0;

    $show_offer_badge = isset($_POST["show_offer_badge"]) ? 1 : 0;
    $offer_badge_text = trim($_POST["offer_badge_text"] ?? "");

    $is_popular = isset($_POST["is_popular"]) ? 1 : 0;
    $is_free_delivery = isset($_POST["is_free_delivery"]) ? 1 : 0;

    $status = ($_POST["status"] ?? "active") === "inactive"
        ? "inactive"
        : "active";

    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */
    if ($name === "") {
        $error = "Product name is required.";
    } elseif (mb_strlen($name) > 255) {
        $error = "Product name is too long.";
    } elseif ($category_id === "") {
        $error = "Please select a category.";
    } elseif ($price === "" || !is_numeric($price) || (float)$price < 0) {
        $error = "Please enter a valid current price.";
    } elseif (
        $regular_price !== "" &&
        (!is_numeric($regular_price) || (float)$regular_price < 0)
    ) {
        $error = "Please enter a valid regular price.";
    } elseif (
        $regular_price !== "" &&
        (float)$regular_price < (float)$price
    ) {
        $error = "Regular price cannot be lower than current price.";
    } elseif (
        $stock === "" ||
        !ctype_digit($stock)
    ) {
        $error = "Stock must be 0 or greater.";
    } elseif (
        $sold_count === "" ||
        !ctype_digit($sold_count)
    ) {
        $error = "Sold count must be 0 or greater.";
    } elseif (
        $star_rating === "" ||
        !is_numeric($star_rating) ||
        (float)$star_rating < 0 ||
        (float)$star_rating > 5
    ) {
        $error = "Star rating must be between 0 and 5.";
    } else {
        $ratingTimesTwo = round((float)$star_rating * 2);

        if (abs(((float)$star_rating * 2) - $ratingTimesTwo) > 0.000001) {
            $error = "Star rating must use 0.5 steps, for example 4.0 or 4.5.";
        }
    }

    if ($error === "" && $category_id !== "" && !ctype_digit($category_id)) {
        $error = "Invalid category selected.";
    }

    /*
    |--------------------------------------------------------------------------
    | CATEGORY CHECK
    |--------------------------------------------------------------------------
    */
    if ($error === "") {
        try {
            $categoryCheck = $pdo->prepare("
                SELECT id
                FROM categories
                WHERE id = ?
                LIMIT 1
            ");
            $categoryCheck->execute([(int)$category_id]);

            if (!$categoryCheck->fetch()) {
                $error = "Selected category does not exist.";
            }
        } catch (PDOException $e) {
            $error = "Could not verify the selected category.";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | OFFER BADGE
    |--------------------------------------------------------------------------
    */
    if (
        $error === "" &&
        $show_offer_badge === 1 &&
        $offer_badge_text === ""
    ) {
        $error = "Please enter offer badge text when the offer badge is ON.";
    }

    /*
    |--------------------------------------------------------------------------
    | SLUG
    |--------------------------------------------------------------------------
    */
    if ($error === "") {
        if ($slug === "") {
            $slug = makeSlug($name);
        } else {
            $slug = makeSlug($slug);
        }

        if ($slug === "") {
            $slug = "product-" . time();
        }

        /*
         * বাংলা/Unicode name থেকে slug খালি হয়ে গেলে fallback.
         */
        if ($slug === "") {
            $slug = "product-" . bin2hex(random_bytes(4));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DUPLICATE SLUG CHECK
    |--------------------------------------------------------------------------
    */
    if ($error === "") {
        try {
            $slugCheck = $pdo->prepare("
                SELECT id
                FROM products
                WHERE slug = ?
                LIMIT 1
            ");
            $slugCheck->execute([$slug]);

            if ($slugCheck->fetch()) {
                $baseSlug = $slug;
                $counter = 2;

                do {
                    $slug = $baseSlug . "-" . $counter;

                    $slugCheck->execute([$slug]);
                    $exists = $slugCheck->fetch();

                    $counter++;
                } while ($exists && $counter < 10000);
            }
        } catch (PDOException $e) {
            $error = "Could not check product slug.";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | IMAGE UPLOAD
    |--------------------------------------------------------------------------
    */
    if ($error === "") {

        $uploadResult = uploadProductMediaToMysql($_FILES["product_media"] ?? []);

        if (!$uploadResult["success"]) {
            $error = $uploadResult["message"];
        } else {
            $product_media = $uploadResult["items"] ?? [];
            foreach ($product_media as $media) {
                if (($media["media_type"] ?? "") === "image") {
                    $image_data = $media["data"] ?? null;
                    $image_mime = $media["mime"] ?? null;
                    break;
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE PRODUCT + STATS
    |--------------------------------------------------------------------------
    */
    if ($error === "") {

        try {

            $pdo->beginTransaction();

            $productStmt = $pdo->prepare("
                INSERT INTO products (
                    category_id,
                    name,
                    slug,
                    description,
                    price,
                    regular_price,
                    stock,
                    image_url,
                    image_data,
                    image_mime,
                    status,
                    is_popular,
                    is_free_delivery,
                    show_offer_badge,
                    offer_badge_text
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $productStmt->execute([
                (int)$category_id,
                $name,
                $slug,
                $description !== "" ? $description : null,
                (float)$price,
                $regular_price !== "" ? (float)$regular_price : null,
                (int)$stock,
                $image_url !== "" ? $image_url : null,
                $image_data,
                $image_mime,
                $status,
                $is_popular,
                $is_free_delivery,
                $show_offer_badge,
                $show_offer_badge && $offer_badge_text !== ""
                    ? $offer_badge_text
                    : null
            ]);

            $product_id = $pdo->lastInsertId();

            if ($image_data !== null) {
                $imageUrlStmt = $pdo->prepare("UPDATE products SET image_url = ? WHERE id = ?");
                $imageUrlStmt->execute(["image-api.php?type=product&id=" . (int)$product_id, $product_id]);
            }

            if ($product_media !== []) {
                $mediaStmt = $pdo->prepare("INSERT INTO product_media (product_id, media_type, media_data, media_mime, sort_order) VALUES (?, ?, ?, ?, ?)");
                foreach ($product_media as $sortOrder => $media) {
                    $mediaStmt->execute([
                        $product_id,
                        $media["media_type"],
                        $media["data"],
                        $media["mime"],
                        $sortOrder
                    ]);
                }
            }

            /*
             * sold_count এবং star_rating এর value + visibility flag
             */
            $statsStmt = $pdo->prepare("
                INSERT INTO product_stats (
                    product_id,
                    sold_count,
                    star_rating,
                    show_sold_count,
                    show_star_rating
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $statsStmt->execute([
                $product_id,
                (int)$sold_count,
                (float)$star_rating,
                $show_sold_count,
                $show_star_rating
            ]);

            $pdo->commit();

            header(
                "Location: admin-products.php?added=1&id=" .
                urlencode($product_id)
            );
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            deleteUploadedProductImage($uploadedAbsolutePath);

            /*
             * নিরাপত্তার জন্য raw database error visitor-কে দেখানো হচ্ছে না।
             */
            $error = "Product save failed. Please check your database structure and try again.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Product - Admin</title>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>window.alert=function(message){return window.Swal?Swal.fire({icon:'warning',text:String(message||''),confirmButtonColor:'#16a34a'}):console.warn(message)};</script>
<style>
.media-preview-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-top:14px}.media-preview-item{display:flex;flex-direction:column;gap:5px;padding:8px;border:1px solid #d1fae5;border-radius:10px;background:#f0fdf4;overflow:hidden}.media-preview-thumb{width:100%;height:90px;object-fit:cover;border-radius:7px;background:#111}.media-preview-item small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
</style>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f4f7fb;
    color: #1f2937;
    font-family: Arial, "Noto Sans Bengali", sans-serif;
}

.page {
    width: 100%;
    max-width: 1150px;
    margin: 0 auto;
    padding: 22px 15px 55px;
}

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.topbar h1 {
    margin: 0;
    font-size: 27px;
}

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 15px;
    background: #111827;
    color: #fff;
    text-decoration: none;
    border-radius: 9px;
    font-weight: 700;
}

.card {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 8px 30px rgba(15, 23, 42, .07);
    margin-bottom: 18px;
}

.section-title {
    margin: 0 0 5px;
    font-size: 19px;
}

.section-help {
    margin: 0 0 20px;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.6;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.full {
    grid-column: 1 / -1;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

label {
    font-size: 14px;
    font-weight: 700;
}

input,
textarea,
select {
    width: 100%;
    border: 1px solid #d7dde8;
    border-radius: 9px;
    padding: 12px 13px;
    font-size: 15px;
    background: #fff;
    color: #111827;
    outline: none;
}

input:focus,
textarea:focus,
select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .08);
}

textarea {
    min-height: 135px;
    resize: vertical;
}

.help {
    color: #6b7280;
    font-size: 12px;
    line-height: 1.5;
}

.message {
    padding: 14px 16px;
    border-radius: 10px;
    margin-bottom: 18px;
    line-height: 1.6;
}

.error {
    background: #fff0f0;
    border: 1px solid #ffcaca;
    color: #b42318;
}

.warning {
    background: #fff8e6;
    border: 1px solid #f3d38a;
    color: #8a5a00;
}

.image-upload {
    border: 2px dashed #cbd5e1;
    border-radius: 14px;
    padding: 20px;
    background: #f8fafc;
    text-align: center;
}

.image-upload input[type="file"] {
    border: 0;
    padding: 0;
    background: transparent;
}

.upload-icon {
    font-size: 34px;
    margin-bottom: 5px;
}

.image-preview-wrap {
    margin-top: 16px;
    display: none;
}

.image-preview {
    display: block;
    width: min(100%, 300px);
    height: 250px;
    margin: 0 auto;
    border: 1px solid #dbe2ea;
    border-radius: 12px;
    background: #fff;
    object-fit: contain;
}

.switch-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 15px;
    background: #fbfdff;
}

.switch-content strong {
    display: block;
    font-size: 14px;
    margin-bottom: 3px;
}

.switch-content span {
    color: #6b7280;
    font-size: 12px;
    line-height: 1.5;
}

.switch {
    position: relative;
    width: 50px;
    min-width: 50px;
    height: 28px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
}

.slider {
    position: absolute;
    inset: 0;
    background: #cbd5e1;
    border-radius: 999px;
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
    background: #fff;
    border-radius: 50%;
    transition: .2s;
    box-shadow: 0 1px 4px rgba(0,0,0,.2);
}

.switch input:checked + .slider {
    background: #2563eb;
}

.switch input:checked + .slider:before {
    transform: translateX(22px);
}

.toggle-box {
    display: grid;
    gap: 12px;
}

.disabled-field {
    opacity: .55;
}

.actions {
    position: sticky;
    bottom: 10px;
    z-index: 5;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
    padding: 12px;
    background: rgba(255,255,255,.95);
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 5px 25px rgba(0,0,0,.08);
}

.cancel-btn,
.save-btn {
    border: 0;
    border-radius: 9px;
    padding: 12px 18px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
}

.cancel-btn {
    background: #e5e7eb;
    color: #111827;
}

.save-btn {
    background: #2563eb;
    color: #fff;
    min-width: 145px;
}

.save-btn:disabled {
    opacity: .65;
    cursor: not-allowed;
}

.required {
    color: #dc2626;
}

@media (max-width: 760px) {
    .page {
        padding: 15px 10px 45px;
    }

    .card {
        padding: 17px;
        border-radius: 13px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .full {
        grid-column: auto;
    }

    .topbar h1 {
        font-size: 22px;
    }

    .actions {
        position: static;
        flex-direction: column-reverse;
    }

    .cancel-btn,
    .save-btn {
        width: 100%;
        text-align: center;
    }
}
</style>
</head>

<body>

<div class="page">

    <div class="topbar">
        <div>
            <h1>➕ Add New Product</h1>
        </div>

        <a class="back-btn" href="admin-products.php">
            ← Product List
        </a>
    </div>

    <?php if ($error !== ""): ?>
        <div class="message error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($categoryError !== ""): ?>
        <div class="message warning">
            <?= e($categoryError) ?>
        </div>
    <?php endif; ?>


    <form method="post" enctype="multipart/form-data" autocomplete="off">

        <div class="card">
            <h2 class="section-title">Product Information</h2>
            <p class="section-help">
                Product name, category, slug এবং description এখান থেকে সেট করুন।
            </p>

            <div class="form-grid">

                <div class="field full">
                    <label for="name">
                        Product Name <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= e($name) ?>"
                        maxlength="255"
                        required
                        placeholder="Enter product name"
                    >
                </div>

                <div class="field">
                    <label for="category_id">
                        Category <span class="required">*</span>
                    </label>

                    <select id="category_id" name="category_id" required>
                        <option value="">-- Select Category --</option>

                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $category): ?>
                                <option
                                    value="<?= (int)$category["id"] ?>"
                                    <?= (string)$category_id === (string)$category["id"] ? "selected" : "" ?>
                                >
                                    <?= e($category["name"]) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No categories found in database</option>
                        <?php endif; ?>
                    </select>

                    <div class="help">
                        <?= count($categories) ?> category available. Website-এর categories table-এর সব category এখানে দেখাবে।
                    </div>
                </div>

                <div class="field">
                    <label for="slug">Slug</label>
                    <input
                        type="text"
                        id="slug"
                        name="slug"
                        value="<?= e($slug) ?>"
                        placeholder="product-slug"
                    >
                    <div class="help">
                        খালি রাখলে Product Name থেকে তৈরি হবে।
                    </div>
                </div>

                <div class="field full">
                    <label for="description">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        placeholder="Write product description..."
                    ><?= e($description) ?></textarea>
                </div>

            </div>
        </div>

        <div class="card">
            <h2 class="section-title">Pricing & Stock</h2>
            <p class="section-help">
                Regular price, current selling price এবং available stock সেট করুন।
            </p>

            <div class="form-grid">

                <div class="field">
                    <label for="regular_price">Regular Price</label>
                    <input
                        type="number"
                        id="regular_price"
                        name="regular_price"
                        value="<?= e($regular_price) ?>"
                        min="0"
                        step="0.01"
                        placeholder="0.00"
                    >
                    <div class="help">
                        Discount না থাকলে চাইলে Current Price-এর সমান দিতে পারেন।
                    </div>
                </div>

                <div class="field">
                    <label for="price">
                        Current Price <span class="required">*</span>
                    </label>
                    <input
                        type="number"
                        id="price"
                        name="price"
                        value="<?= e($price) ?>"
                        min="0"
                        step="0.01"
                        required
                        placeholder="0.00"
                    >
                </div>

                <div class="field">
                    <label for="stock">Stock</label>
                    <input
                        type="number"
                        id="stock"
                        name="stock"
                        value="<?= e($stock) ?>"
                        min="0"
                        step="1"
                        required
                    >
                </div>

            </div>
        </div>

        <div class="card">
            <h2 class="section-title">Product Photos & Videos</h2>
            <p class="section-help">
                Add as many product images or videos as needed. Images up to 5MB and videos up to 50MB are stored in MySQL.
            </p>

            <div class="image-upload" id="mediaUploadArea">
                <div class="upload-icon"><i class="fa-regular fa-image"></i></div>

                <div style="font-weight:700; margin-bottom:7px;">
                    Add Product Media
                </div>

                <div class="help" style="margin-bottom:13px;">
                    Select an image or video, then use + to add another file.
                </div>

                <input
                    type="file"
                    id="product_media"
                    name="product_media[]"
                    accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/ogg"
                >
                <div id="mediaInputs"></div>
                <div id="mediaPreviewList" class="media-preview-list"></div>
                <button type="button" class="add-media-btn" id="addMediaButton" aria-label="Add another product image or video">+</button>
            </div>

            <div class="help" style="margin-top:10px;">
                The first image is used as the product thumbnail. Every selected file is saved separately.
            </div>
        </div>

        <div class="card">
            <h2 class="section-title">Sold Count & Star Rating</h2>
            <p class="section-help">
                Value এবং visibility আলাদা। অর্থাৎ Sold Count 0 হলেও সেটি ON রাখা
                যাবে, আবার value রেখে OFF করলেও customer-এর সামনে দেখানো হবে না।
                Star Rating-এর ক্ষেত্রেও একই নিয়ম।
            </p>

            <div class="form-grid">

                <div class="field">
                    <label for="sold_count">Sold Count</label>
                    <input
                        type="number"
                        id="sold_count"
                        name="sold_count"
                        value="<?= e($sold_count) ?>"
                        min="0"
                        step="1"
                    >
                    <div class="help">
                        Example: 125 — এটি sales/statistics value।
                    </div>
                </div>

                <div class="field">
                    <label for="star_rating">Star Rating</label>
                    <input
                        type="number"
                        id="star_rating"
                        name="star_rating"
                        value="<?= e($star_rating) ?>"
                        min="0"
                        max="5"
                        step="0.5"
                    >
                    <div class="help">
                        0 থেকে 5; 0.5 step support করে। যেমন 4.0, 4.5, 5.0।
                    </div>
                </div>

                <div class="toggle-box full">

                    <div class="switch-row">
                        <div class="switch-content">
                            <strong>Show Sold Count</strong>
                            <span>
                                ON থাকলে customer-facing product page/card-এ
                                Sold Count দেখানো যাবে।
                            </span>
                        </div>

                        <label class="switch">
                            <input
                                type="checkbox"
                                id="show_sold_count"
                                name="show_sold_count"
                                value="1"
                                <?= $show_sold_count ? "checked" : "" ?>
                            >
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="switch-row">
                        <div class="switch-content">
                            <strong>Show Star Rating</strong>
                            <span>
                                ON থাকলে customer-facing product page/card-এ
                                Star Rating দেখানো যাবে।
                            </span>
                        </div>

                        <label class="switch">
                            <input
                                type="checkbox"
                                id="show_star_rating"
                                name="show_star_rating"
                                value="1"
                                <?= $show_star_rating ? "checked" : "" ?>
                            >
                            <span class="slider"></span>
                        </label>
                    </div>

                </div>

            </div>
        </div>

        <div class="card">
            <h2 class="section-title">Product Options</h2>
            <p class="section-help">
                Popular, Free Delivery এবং Offer Badge-এর মতো অতিরিক্ত option সেট করুন।
            </p>

            <div class="form-grid">

                <div class="toggle-box full">

                    <div class="switch-row">
                        <div class="switch-content">
                            <strong>Popular Product</strong>
                            <span>
                                Product-কে popular হিসেবে mark করবে।
                            </span>
                        </div>

                        <label class="switch">
                            <input
                                type="checkbox"
                                name="is_popular"
                                value="1"
                                <?= $is_popular ? "checked" : "" ?>
                            >
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="switch-row">
                        <div class="switch-content">
                            <strong>Free Delivery</strong>
                            <span>
                                Product-এর জন্য free delivery option ON/OFF।
                            </span>
                        </div>

                        <label class="switch">
                            <input
                                type="checkbox"
                                name="is_free_delivery"
                                value="1"
                                <?= $is_free_delivery ? "checked" : "" ?>
                            >
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="switch-row">
                        <div class="switch-content">
                            <strong>Offer Badge</strong>
                            <span>
                                Offer badge দেখাতে চাইলে ON করুন।
                            </span>
                        </div>

                        <label class="switch">
                            <input
                                type="checkbox"
                                id="show_offer_badge"
                                name="show_offer_badge"
                                value="1"
                                <?= $show_offer_badge ? "checked" : "" ?>
                            >
                            <span class="slider"></span>
                        </label>
                    </div>

                </div>

                <div
                    class="field full"
                    id="offerBadgeTextField"
                >
                    <label for="offer_badge_text">
                        Offer Badge Text
                    </label>

                    <input
                        type="text"
                        id="offer_badge_text"
                        name="offer_badge_text"
                        value="<?= e($offer_badge_text) ?>"
                        maxlength="100"
                        placeholder="Example: 20% OFF"
                    >
                </div>

                <div class="field">
                    <label for="status">Product Status</label>
                    <select id="status" name="status">
                        <option
                            value="active"
                            <?= $status === "active" ? "selected" : "" ?>
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            <?= $status === "inactive" ? "selected" : "" ?>
                        >
                            Inactive
                        </option>
                    </select>
                </div>

            </div>
        </div>

        <div class="actions">
            <a class="cancel-btn" href="admin-products.php">
                Cancel
            </a>

            <button
                type="submit"
                class="save-btn"
                id="saveButton"
            >
                Save Product
            </button>
        </div>

    </form>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    /*
    |--------------------------------------------------------------------------
    | IMAGE PREVIEW
    |--------------------------------------------------------------------------
    */
    const mediaInput = document.getElementById("product_media");
    const mediaInputs = document.getElementById("mediaInputs");
    const mediaPreviewList = document.getElementById("mediaPreviewList");
    const addMediaButton = document.getElementById("addMediaButton");

    if (mediaInput && mediaInputs && mediaPreviewList && addMediaButton) {
        let mediaCount = 0;
        const previewMedia = function (event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;
            const maxSize = file.type.startsWith("video/") ? 50 * 1024 * 1024 : 5 * 1024 * 1024;
            if (file.size > maxSize) {
                alert(file.type.startsWith("video/") ? "Video size must be 50MB or less." : "Image size must be 5MB or less.");
                event.target.value = "";
                return;
            }
            const item = document.createElement("div");
            item.className = "media-preview-item";
            const preview = file.type.startsWith("video/")
                ? document.createElement("video")
                : document.createElement("img");
            preview.src = URL.createObjectURL(file);
            preview.className = "media-preview-thumb";
            if (file.type.startsWith("video/")) {
                preview.muted = true;
                preview.controls = true;
            }
            const label = document.createElement("small");
            label.textContent = file.name;
            item.append(preview, label);
            mediaPreviewList.appendChild(item);
        };
        mediaInput.addEventListener("change", previewMedia);
        addMediaButton.addEventListener("click", function () {
            const input = mediaInput.cloneNode(true);
            input.id = "product_media_" + mediaCount++;
            input.addEventListener("change", previewMedia);
            mediaInputs.appendChild(input);
            input.click();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | AUTO SLUG
    |--------------------------------------------------------------------------
    */
    const nameInput = document.getElementById("name");
    const slugInput = document.getElementById("slug");

    let slugManuallyChanged = slugInput && slugInput.value.trim() !== "";

    if (slugInput) {
        slugInput.addEventListener("input", function () {
            slugManuallyChanged = this.value.trim() !== "";
        });
    }

    if (nameInput && slugInput) {
        nameInput.addEventListener("input", function () {

            if (slugManuallyChanged) {
                return;
            }

            const value = this.value
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, "-")
                .replace(/^-+|-+$/g, "");

            slugInput.value = value;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | PRICE VALIDATION
    |--------------------------------------------------------------------------
    */
    const regularPrice = document.getElementById("regular_price");
    const currentPrice = document.getElementById("price");

    function checkPrice() {

        if (!regularPrice || !currentPrice) {
            return;
        }

        const regular = parseFloat(regularPrice.value);
        const current = parseFloat(currentPrice.value);

        if (
            !isNaN(regular) &&
            !isNaN(current) &&
            regular < current
        ) {
            currentPrice.setCustomValidity(
                "Current price cannot be higher than regular price."
            );
        } else {
            currentPrice.setCustomValidity("");
        }
    }

    if (regularPrice) {
        regularPrice.addEventListener("input", checkPrice);
    }

    if (currentPrice) {
        currentPrice.addEventListener("input", checkPrice);
    }

    /*
    |--------------------------------------------------------------------------
    | OFFER BADGE TOGGLE
    |--------------------------------------------------------------------------
    */
    const offerToggle = document.getElementById("show_offer_badge");
    const offerField = document.getElementById("offerBadgeTextField");
    const offerText = document.getElementById("offer_badge_text");

    function updateOfferBadgeField() {

        if (!offerToggle || !offerField || !offerText) {
            return;
        }

        if (offerToggle.checked) {
            offerField.classList.remove("disabled-field");
            offerText.disabled = false;
        } else {
            offerField.classList.add("disabled-field");
            offerText.disabled = true;
            offerText.value = "";
        }
    }

    if (offerToggle) {
        offerToggle.addEventListener("change", updateOfferBadgeField);
        updateOfferBadgeField();
    }

    /*
    |--------------------------------------------------------------------------
    | FORM SUBMIT PROTECTION
    |--------------------------------------------------------------------------
    */
    const form = document.querySelector("form");
    const saveButton = document.getElementById("saveButton");

    if (form && saveButton) {

        form.addEventListener("submit", function (event) {

            checkPrice();

            if (!form.checkValidity()) {
                return;
            }

            saveButton.disabled = true;
            saveButton.textContent = "Saving Product...";
        });
    }
});
</script>

</body>
<script>if (window.lucide) lucide.createIcons();</script>
</html>

