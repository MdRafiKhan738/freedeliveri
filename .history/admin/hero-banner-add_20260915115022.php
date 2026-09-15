<?php

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cloudinary.php';

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

    die("Database connect failed: " . $e->getMessage());

}


/* =========================
   ADD BANNER
========================= */

$message = "";
$error = "";

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
            $error = $uploadResult["message"];
        } else {
            $image_data = $uploadResult["data"] ?? null;
            $image_mime = $uploadResult["mime"] ?? null;
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

    $status = $_POST["status"] ?? "active";


    /* Validation */

    if ($error === "" && $title === "" && $image_url === "" && $image_data === null) {

        $error = "কমপক্ষে Banner Title অথবা Image URL দিতে হবে।";

    } else {

        try {

            $sql = "
                INSERT INTO hero_banners
                (
                    title,
                    subtitle,
                    description,
                    image_url,
                    image_data,
                    image_mime,
                    button_text,
                    button_link,
                    badge_text,
                    show_badge,
                    start_date,
                    end_date,
                    position,
                    status
                )
                VALUES
                (
                    :title,
                    :subtitle,
                    :description,
                    :image_url,
                    :image_data,
                    :image_mime,
                    :button_text,
                    :button_link,
                    :badge_text,
                    :show_badge,
                    :start_date,
                    :end_date,
                    :position,
                    :status
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([

                ":title" => $title,
                ":subtitle" => $subtitle,
                ":description" => $description,

                ":image_url" => $image_url,
                ":image_data" => $image_data,
                ":image_mime" => $image_mime,

                ":button_text" => $button_text,
                ":button_link" => $button_link,

                ":badge_text" => $badge_text,
                ":show_badge" => $show_badge,

                ":start_date" => $start_date,
                ":end_date" => $end_date,

                ":position" => $position,
                ":status" => $status

            ]);


            $message = "Banner সফলভাবে যোগ হয়েছে।";

            if ($image_data !== null) {
                $bannerId = (int)$pdo->lastInsertId();
                $imageUrlStmt = $pdo->prepare("UPDATE hero_banners SET image_url = ? WHERE id = ?");
                $imageUrlStmt->execute(["image-api.php?type=hero&id=" . $bannerId, $bannerId]);
            }

            /* Form reset */

            $_POST = [];

        } catch (PDOException $e) {

            $error = "Banner save করা যায়নি: " . $e->getMessage();

        }

    }

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

    <title>Add New Hero Banner</title>

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

        .back {
            color: #fff;
            text-decoration: none;
            background: #333;
            padding: 9px 15px;
            border-radius: 6px;
        }

        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .card {
            background: #fff;
            padding: 25px;
            border-radius: 12px;

            box-shadow:
                0 2px 12px rgba(0,0,0,0.06);
        }

        .card h3 {
            margin-top: 0;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 12px;

            border: 1px solid #ddd;
            border-radius: 7px;

            font-size: 15px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox input {
            width: auto;
        }

        .success {
            background: #e8f7e8;
            color: #187329;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .error {
            background: #ffe8e8;
            color: #b00000;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .save-btn {
            border: none;
            background: #111;
            color: #fff;

            padding: 12px 22px;

            border-radius: 7px;

            cursor: pointer;

            font-size: 15px;
        }

        .cancel-btn {
            text-decoration: none;

            background: #ddd;
            color: #111;

            padding: 12px 22px;

            border-radius: 7px;
        }

        @media (max-width: 700px) {

            .row {
                grid-template-columns: 1fr;
            }

            .container {
                margin-top: 20px;
            }

        }

    </style>

</head>


<body>


<header class="header">

    <h2>Add New Hero Banner</h2>

    <a
        class="back"
        href="hero-banners.php"
    >
        ← Back
    </a>

</header>


<div class="container">

    <div class="card">

        <h3><i class="fa-regular fa-image"></i> নতুন Hero Banner যোগ করুন</h3>


        <?php if ($message): ?>

            <div class="success">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <form method="POST" enctype="multipart/form-data">


            <!-- TITLE -->

            <div class="form-group">

                <label>
                    Banner Title
                </label>

                <input
                    type="text"
                    name="title"
                    placeholder="যেমন: 1 Year Free Delivery"
                    value="<?= htmlspecialchars($_POST["title"] ?? "") ?>"
                >

            </div>


            <!-- SUBTITLE -->

            <div class="form-group">

                <label>
                    Subtitle
                </label>

                <input
                    type="text"
                    name="subtitle"
                    placeholder="যেমন: Special Offer"
                    value="<?= htmlspecialchars($_POST["subtitle"] ?? "") ?>"
                >

            </div>


            <!-- DESCRIPTION -->

            <div class="form-group">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                    placeholder="Banner-এর বিস্তারিত লেখা"
                ><?= htmlspecialchars($_POST["description"] ?? "") ?></textarea>

            </div>


            <!-- IMAGE -->

            <div class="form-group">

                <label>
                    Banner Image
                </label>

                <input
                    name="banner_image"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                >

                <input
                    type="hidden"
                    name="image_url"
                    value="<?= htmlspecialchars($_POST["image_url"] ?? "") ?>"
                >

                <small>
                    JPG, PNG অথবা WEBP image দিন। Image সরাসরি MySQL-এ save হবে।
                </small>

            </div>


            <!-- BUTTON -->

            <div class="row">

                <div class="form-group">

                    <label>
                        Button Text
                    </label>

                    <input
                        type="text"
                        name="button_text"
                        placeholder="যেমন: Shop Now"
                        value="<?= htmlspecialchars($_POST["button_text"] ?? "") ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Button Link
                    </label>

                    <input
                        type="text"
                        name="button_link"
                        placeholder="যেমন: index.html"
                        value="<?= htmlspecialchars($_POST["button_link"] ?? "") ?>"
                    >

                </div>

            </div>


            <!-- BADGE -->

            <div class="row">

                <div class="form-group">

                    <label>
                        Badge Text
                    </label>

                    <input
                        type="text"
                        name="badge_text"
                        placeholder="যেমন: LIMITED OFFER"
                        value="<?= htmlspecialchars($_POST["badge_text"] ?? "") ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Badge
                    </label>

                    <label class="checkbox">

                        <input
                            type="checkbox"
                            name="show_badge"
                            value="1"
                            <?= isset($_POST["show_badge"]) ? "checked" : "" ?>
                        >

                        Badge দেখাবেন

                    </label>

                </div>

            </div>


            <!-- DATE -->

            <div class="row">

                <div class="form-group">

                    <label>
                        Start Date
                    </label>

                    <input
                        type="date"
                        name="start_date"
                        value="<?= htmlspecialchars($_POST["start_date"] ?? "") ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        End Date
                    </label>

                    <input
                        type="date"
                        name="end_date"
                        value="<?= htmlspecialchars($_POST["end_date"] ?? "") ?>"
                    >

                </div>

            </div>


            <!-- POSITION + STATUS -->

            <div class="row">

                <div class="form-group">

                    <label>
                        Position
                    </label>

                    <input
                        type="number"
                        name="position"
                        min="1"
                        value="<?= htmlspecialchars($_POST["position"] ?? "1") ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <option
                            value="active"
                            <?= ($_POST["status"] ?? "active") === "active"
                                ? "selected"
                                : "" ?>
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            <?= ($_POST["status"] ?? "") === "inactive"
                                ? "selected"
                                : "" ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>

            </div>


            <!-- BUTTONS -->

            <div class="buttons">

                <button
                    type="submit"
                    class="save-btn"
                >
                    <i class="fa-solid fa-floppy-disk"></i> Save Banner
                </button>


                <a
                    href="hero-banners.php"
                    class="cancel-btn"
                >
                    Cancel
                </a>

            </div>


        </form>

    </div>

</div>


</body>

</html>
