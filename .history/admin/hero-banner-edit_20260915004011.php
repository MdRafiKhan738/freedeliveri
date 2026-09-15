<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cloudinary.php';

if (
    !isset($_SESSION["admin_id"]) ||
    ($_SESSION["admin_role"] ?? '') !== "admin"
) {
    header("Location: admin-login.php");
    exit;
}

/* Database Connection */
try {
    $pdo = db();
} catch (PDOException $e) {
    die("Database connect failed: " . $e->getMessage());
}


/* Get Banner ID */
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($id <= 0) {
    header("Location: hero-banners.php");
    exit;
}


/* Update Banner */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $subtitle = trim($_POST["subtitle"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $image_url = trim($_POST["image_url"] ?? "");
    $image_data = null;
    $image_mime = null;

    if (isset($_FILES["banner_image"]) && is_array($_FILES["banner_image"])) {
        $uploadResult = uploadImageToMysql($_FILES["banner_image"]);
        if (!$uploadResult["success"]) {
            die(htmlspecialchars($uploadResult["message"], ENT_QUOTES, "UTF-8"));
        }
        if (!empty($uploadResult["data"])) {
            $image_data = $uploadResult["data"];
            $image_mime = $uploadResult["mime"];
            $image_url = "image-api.php?type=hero&id=" . $id;
        }
    }
    $button_text = trim($_POST["button_text"] ?? "");
    $button_link = trim($_POST["button_link"] ?? "");
    $badge_text = trim($_POST["badge_text"] ?? "");

    $show_badge = isset($_POST["show_badge"]) ? 1 : 0;

    $start_date = !empty($_POST["start_date"])
        ? $_POST["start_date"]
        : null;

    $end_date = !empty($_POST["end_date"])
        ? $_POST["end_date"]
        : null;

    $position = (int)($_POST["position"] ?? 1);

    $status = ($_POST["status"] ?? "active") === "inactive"
        ? "inactive"
        : "active";


    $sql = "
        UPDATE hero_banners
        SET
            title = ?,
            subtitle = ?,
            description = ?,
            image_url = ?,
            image_data = ?,
            image_mime = ?,
            button_text = ?,
            button_link = ?,
            badge_text = ?,
            show_badge = ?,
            start_date = ?,
            end_date = ?,
            position = ?,
            status = ?
        WHERE id = ?
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $title,
        $subtitle,
        $description,
        $image_url !== "" ? $image_url : null,
        $image_data,
        $image_mime,
        $button_text !== "" ? $button_text : null,
        $button_link !== "" ? $button_link : null,
        $badge_text !== "" ? $badge_text : null,
        $show_badge,
        $start_date,
        $end_date,
        $position,
        $status,
        $id
    ]);

    header("Location: hero-banners.php?success=updated");
    exit;
}


/* Get Existing Banner */
$stmt = $pdo->prepare("
    SELECT *
    FROM hero_banners
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$banner = $stmt->fetch();

if (!$banner) {
    die("Hero Banner not found.");
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Hero Banner</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            padding: 20px;
        }

        .container {
            max-width: 700px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
        }

        h2 {
            margin-top: 0;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 11px;
            border: 1px solid #ddd;
            border-radius: 7px;
            font-size: 15px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 18px;
        }

        .checkbox input {
            width: auto;
        }

        button {
            margin-top: 25px;
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 7px;
            background: #111;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #333;
        }
    </style>
</head>

<body>

<div class="container">

    <a class="back" href="hero-banners.php">
        ← Back to Hero Banners
    </a>

    <h2>✏️ Edit Hero Banner</h2>

    <form method="POST" enctype="multipart/form-data">

        <label>Title</label>
        <input
            type="text"
            name="title"
            value="<?= htmlspecialchars($banner['title'] ?? '') ?>"
        >

        <label>Subtitle</label>
        <input
            type="text"
            name="subtitle"
            value="<?= htmlspecialchars($banner['subtitle'] ?? '') ?>"
        >

        <label>Description</label>
        <textarea name="description"><?= htmlspecialchars($banner['description'] ?? '') ?></textarea>

        <label>Image URL</label>
        <input
            type="file"
            name="banner_image"
            accept="image/jpeg,image/png,image/webp"
        >
        <input
            type="text"
            name="image_url"
            value="<?= htmlspecialchars($banner['image_url'] ?? '') ?>"
            placeholder="https://example.com/banner.jpg"
        >

        <label>Button Text</label>
        <input
            type="text"
            name="button_text"
            value="<?= htmlspecialchars($banner['button_text'] ?? '') ?>"
        >

        <label>Button Link</label>
        <input
            type="text"
            name="button_link"
            value="<?= htmlspecialchars($banner['button_link'] ?? '') ?>"
        >

        <label>Badge Text</label>
        <input
            type="text"
            name="badge_text"
            value="<?= htmlspecialchars($banner['badge_text'] ?? '') ?>"
        >

        <label class="checkbox">
            <input
                type="checkbox"
                name="show_badge"
                value="1"
                <?= !empty($banner['show_badge']) ? 'checked' : '' ?>
            >
            Show Badge
        </label>

        <label>Start Date</label>
        <input
            type="date"
            name="start_date"
            value="<?= htmlspecialchars($banner['start_date'] ?? '') ?>"
        >

        <label>End Date</label>
        <input
            type="date"
            name="end_date"
            value="<?= htmlspecialchars($banner['end_date'] ?? '') ?>"
        >

        <label>Position</label>
        <input
            type="number"
            name="position"
            min="1"
            value="<?= (int)$banner['position'] ?>"
        >

        <label>Status</label>
        <select name="status">
            <option value="active"
                <?= ($banner['status'] ?? '') === 'active' ? 'selected' : '' ?>>
                Active
            </option>

            <option value="inactive"
                <?= ($banner['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>
                Inactive
            </option>
        </select>

        <button type="submit">
            💾 Update Banner
        </button>

    </form>

</div>

</body>
</html>
