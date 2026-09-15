<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "ডাটাবেস সংযোগ করা যায়নি।"
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/* =========================
   ONLY POST REQUEST
========================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/* =========================
   PROMO HELPERS
========================= */

function jsonResponse(bool $success, string $message, array $extra = [], int $status = 200): void
{
    http_response_code($status);

    echo json_encode(
        array_merge([
            "success" => $success,
            "message" => $message
        ], $extra),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

function getOrCreateCustomer(PDO $pdo, string $name, string $phone): ?int
{
    $stmt = $pdo->prepare(
        "SELECT id FROM users WHERE phone = ? AND role = 'customer' LIMIT 1"
    );
    $stmt->execute([$phone]);

    $userId = $stmt->fetchColumn();

    if ($userId !== false) {
        return (int)$userId;
    }

    // If the phone belongs to a non-customer account, do not attach a promo to it.
    $checkAny = $pdo->prepare(
        "SELECT id FROM users WHERE phone = ? LIMIT 1"
    );
    $checkAny->execute([$phone]);

    if ($checkAny->fetchColumn() !== false) {
        return null;
    }

    $randomPassword = bin2hex(random_bytes(24));
    $passwordHash = password_hash($randomPassword, PASSWORD_DEFAULT);

    $insert = $pdo->prepare(
        "INSERT INTO users (name, phone, password_hash, role, status)
         VALUES (?, ?, ?, 'customer', 'active')"
    );
    $insert->execute([$name, $phone, $passwordHash]);

    return (int)$pdo->lastInsertId();
}

function generateUniquePromoCode(PDO $pdo): string
{
    do {
        $code = 'FD' . strtoupper(bin2hex(random_bytes(3)));

        $stmt = $pdo->prepare(
            "SELECT id FROM promo_codes WHERE code = ? LIMIT 1"
        );
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn() !== false);

    return $code;
}

function normalizePromoCode(string $promoCode): string
{
    $promo = strtoupper(trim($promoCode));
    return preg_replace('/[^A-Z0-9]/', '', $promo) ?? '';
}

function findValidPromo(PDO $pdo, string $phone, string $promoCode, float $subtotal): ?array
{
    if ($promoCode === '' || $subtotal < 500) {
        return null;
    }

    $sql = "
        SELECT
            up.id AS user_promo_id,
            up.user_id,
            up.usage_count AS user_usage_count,
            up.status AS user_promo_status,
            up.starts_at AS user_starts_at,
            up.expires_at AS user_expires_at,
            pc.id AS promo_code_id,
            pc.code,
            pc.discount_type,
            pc.discount_value,
            pc.minimum_order_amount,
            pc.usage_limit,
            pc.used_count,
            pc.status AS promo_status,
            pc.starts_at AS promo_starts_at,
            pc.expires_at AS promo_expires_at
        FROM user_promos up
        INNER JOIN users u ON u.id = up.user_id
        INNER JOIN promo_codes pc ON pc.id = up.promo_code_id
        WHERE u.phone = ?
          AND u.role = 'customer'
          AND UPPER(pc.code) = ?
          AND up.status IN ('active', 'used')
          AND pc.status = 'active'
          AND (up.starts_at IS NULL OR up.starts_at <= NOW())
          AND (up.expires_at IS NULL OR up.expires_at >= NOW())
          AND (pc.starts_at IS NULL OR pc.starts_at <= NOW())
          AND (pc.expires_at IS NULL OR pc.expires_at >= NOW())
          AND (pc.usage_limit IS NULL OR pc.usage_limit = 0 OR pc.used_count >= 0)
          AND subtotal_placeholder >= pc.minimum_order_amount
        LIMIT 1
    ";

    $sql = str_replace('subtotal_placeholder', '?', $sql);

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$phone, strtoupper($promoCode), $subtotal]);

    $promo = $stmt->fetch();

    if (!$promo) {
        return null;
    }

    if (strtolower((string)$promo['discount_type']) !== 'free_delivery') {
        return null;
    }

    return $promo;
}


/* =========================
   READ JSON
========================= */

$rawInput = file_get_contents("php://input");

$data = json_decode($rawInput, true);

if (!is_array($data)) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "অর্ডারের তথ্য সঠিক নয়।"
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/* =========================
   PROMO VALIDATION ACTION
========================= */

$action = trim((string)($data["action"] ?? "order"));

if ($action === "validate_promo") {

    $phone = normalizePhone((string)($data["customer_phone"] ?? ""));
    $promo = normalizePromoCode((string)($data["promo_code"] ?? ""));
    $subtotalForPromo = (float)($data["subtotal"] ?? 0);

    if ($phone === "" || $promo === "") {
        jsonResponse(false, "মোবাইল নম্বর এবং প্রোমো কোড দিন।", [], 400);
    }

    if ($subtotalForPromo < 500) {
        jsonResponse(false, "প্রোমো কোড ব্যবহার করতে কমপক্ষে ৫০০ টাকার পণ্য কিনতে হবে।", [], 400);
    }

    try {
        if (strlen($promo) < 8 || strlen($promo) > 32) {
            jsonResponse(false, "প্রোমো কোডটি সঠিক নয়। নতুন কোড ৮ অক্ষরের; পুরনো বৈধ কোড ৮ থেকে ৩২ অক্ষর হতে পারে।", [], 400);
        }

        $validPromo = findValidPromo($pdo, $phone, $promo, $subtotalForPromo);

        if (!$validPromo) {
            jsonResponse(false, "এই প্রোমো কোডটি আপনার জন্য বৈধ নয় বা মেয়াদ শেষ হয়েছে।", [], 400);
        }

        jsonResponse(true, "প্রোমো কোড সফলভাবে প্রয়োগ হয়েছে।", [
            "promo_code" => $validPromo["code"],
            "delivery_charge" => 0,
            "discount_type" => "free_delivery"
        ]);

    } catch (Throwable $e) {
        jsonResponse(false, "প্রোমো কোড যাচাই করা যায়নি।", [], 500);
    }
}


/* =========================
   LOGIN / SIGNUP / ACCOUNT / LOGOUT
========================= */

if ($action === "login") {
    $phone = normalizePhone((string)($data["customer_phone"] ?? ""));
    if ($phone === "") jsonResponse(false, "মোবাইল নম্বর লিখুন।", [], 400);

    try {
        $stmt = $pdo->prepare("SELECT id, name, phone, role, status FROM users WHERE phone = ? AND role = 'customer' LIMIT 1");
        $stmt->execute([$phone]);
        $customer = $stmt->fetch();

        if (!$customer) jsonResponse(false, "এই মোবাইল নম্বর দিয়ে কোনো account পাওয়া যায়নি। আগে Sign Up করুন।", [], 404);
        if (isset($customer['status']) && $customer['status'] !== 'active') jsonResponse(false, "আপনার account বর্তমানে সক্রিয় নয়।", [], 403);

        jsonResponse(true, "Login সফল হয়েছে।", ["customer" => [
            "id" => (int)$customer['id'],
            "name" => $customer['name'] ?? "",
            "phone" => $customer['phone'] ?? $phone
        ]]);
    } catch (Throwable $e) {
        jsonResponse(false, "Login করতে সমস্যা হয়েছে।", [], 500);
    }
}

if ($action === "signup") {
    $name = trim((string)($data["customer_name"] ?? ""));
    $phone = normalizePhone((string)($data["customer_phone"] ?? ""));
    if ($name === "") jsonResponse(false, "আপনার নাম লিখুন।", [], 400);
    if ($phone === "") jsonResponse(false, "মোবাইল নম্বর লিখুন।", [], 400);

    try {
        $stmt = $pdo->prepare("SELECT id, role, status FROM users WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        $existing = $stmt->fetch();
        if ($existing) jsonResponse(false, "এই মোবাইল নম্বর দিয়ে আগে থেকেই account আছে। Login করুন।", [], 409);

        $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO users (name, phone, password_hash, role, status) VALUES (?, ?, ?, 'customer', 'active')");
        $insert->execute([$name, $phone, $hash]);
        $id = (int)$pdo->lastInsertId();

        jsonResponse(true, "Account সফলভাবে তৈরি হয়েছে।", ["customer" => [
            "id" => $id, "name" => $name, "phone" => $phone, "promos" => []
        ]]);
    } catch (Throwable $e) {
        jsonResponse(false, "Account তৈরি করা যায়নি।", [], 500);
    }
}

if ($action === "profile_photo") {
    $phone = normalizePhone((string)($data["customer_phone"] ?? ""));
    $photo = (string)($data["photo_data"] ?? "");
    if ($phone === "" || $photo === "") {
        jsonResponse(false, "প্রোফাইল ছবি পাওয়া যায়নি।", [], 400);
    }
    if (!preg_match('#^data:(image/(?:jpeg|png|webp));base64,(.+)$#', $photo, $matches)) {
        jsonResponse(false, "শুধু JPG, PNG অথবা WebP ছবি আপলোড করুন।", [], 400);
    }
    $binary = base64_decode($matches[2], true);
    if ($binary === false || strlen($binary) > 5 * 1024 * 1024) {
        jsonResponse(false, "প্রোফাইল ছবির আকার ৫MB-এর মধ্যে হতে হবে।", [], 400);
    }
    try {
        $stmt = $pdo->prepare("UPDATE users SET profile_image_data = ?, profile_image_mime = ? WHERE phone = ? AND role = 'customer'");
        $stmt->execute([$binary, $matches[1], $phone]);
        if ($stmt->rowCount() < 1) jsonResponse(false, "প্রোফাইল পাওয়া যায়নি।", [], 404);
        jsonResponse(true, "প্রোফাইল ছবি আপডেট হয়েছে।", ["photo_url" => "image-api.php?type=profile&phone=" . rawurlencode($phone) . "&v=" . time()]);
    } catch (Throwable $e) {
        jsonResponse(false, "প্রোফাইল ছবি সংরক্ষণ করা যায়নি।", [], 500);
    }
}

if ($action === "account") {
    $phone = trim((string)($data["customer_phone"] ?? ""));
    if ($phone === "") jsonResponse(false, "মোবাইল নম্বর পাওয়া যায়নি।", [], 400);

    try {
        $stmt = $pdo->prepare("SELECT id, name, phone, CASE WHEN profile_image_data IS NULL THEN NULL ELSE CONCAT('image-api.php?type=profile&phone=', phone) END AS photo_url FROM users WHERE phone = ? AND role = 'customer' LIMIT 1");
        $stmt->execute([$phone]);
        $customer = $stmt->fetch();
        if (!$customer) jsonResponse(false, "Account পাওয়া যায়নি।", [], 404);

        $customer['promos'] = [];
        $promoStmt = $pdo->prepare("SELECT pc.code, up.expires_at FROM user_promos up INNER JOIN promo_codes pc ON pc.id = up.promo_code_id WHERE up.user_id = ? AND up.status = 'active' AND pc.status = 'active' AND (up.expires_at IS NULL OR up.expires_at >= NOW()) ORDER BY up.id DESC");
        $promoStmt->execute([(int)$customer['id']]);
        $customer['promos'] = $promoStmt->fetchAll();

        jsonResponse(true, "Account তথ্য পাওয়া গেছে।", ["customer" => $customer]);
    } catch (Throwable $e) {
        jsonResponse(false, "Account তথ্য লোড করা যায়নি।", [], 500);
    }
}

if ($action === "orders") {
    $phone = trim((string)($data["customer_phone"] ?? ""));
    if ($phone === "") {
        jsonResponse(false, "মোবাইল নম্বর পাওয়া যায়নি।", [], 400);
    }

    try {
        $stmt = $pdo->prepare("SELECT o.id, o.status, o.total_amount, o.created_at, GROUP_CONCAT(CONCAT(oi.product_name, ' × ', oi.quantity) ORDER BY oi.id SEPARATOR ', ') AS items FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id WHERE o.customer_phone = ? GROUP BY o.id ORDER BY o.id DESC LIMIT 50");
        $stmt->execute([$phone]);
        jsonResponse(true, "অর্ডার তথ্য পাওয়া গেছে।", ["orders" => $stmt->fetchAll()]);
    } catch (Throwable $e) {
        jsonResponse(false, "অর্ডার তথ্য লোড করা যায়নি।", [], 500);
    }
}

if ($action === "logout") {
    jsonResponse(true, "Logout সফল হয়েছে।");
}

/* =========================
   CUSTOMER INFORMATION
========================= */

$customerName =
    trim((string)($data["customer_name"] ?? ""));

$customerPhone =
    normalizePhone((string)($data["customer_phone"] ?? ""));

$customerAddress =
    trim((string)($data["customer_address"] ?? ""));

$deliveryMethod =
    trim((string)($data["delivery_method"] ?? "home_delivery"));

$paymentMethod =
    trim((string)($data["payment_method"] ?? "cod"));

$promoCode =
    normalizePromoCode((string)($data["promo_code"] ?? ""));

$customerNote =
    trim((string)($data["customer_note"] ?? ""));

$deliveryCharge =
    (float)($data["delivery_charge"] ?? 0);

$discount =
    (float)($data["discount"] ?? 0);

$items =
    $data["items"] ?? [];


/* =========================
   VALIDATION
========================= */

if ($customerName === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "আপনার নাম লিখুন।"
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


if ($customerPhone === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "মোবাইল নম্বর লিখুন।"
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


if ($customerAddress === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "সম্পূর্ণ ঠিকানা লিখুন।"
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


if (!is_array($items) || count($items) === 0) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "কোনো পণ্য নির্বাচন করা হয়নি।"
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/* =========================
   SAFE NUMBERS
========================= */

if ($deliveryCharge < 0) {
    $deliveryCharge = 0;
}

if ($discount < 0) {
    $discount = 0;
}


/* =========================
   CALCULATE ORDER
========================= */

$subtotal = 0;

$preparedItems = [];


foreach ($items as $item) {

    if (!is_array($item)) {
        continue;
    }

    $productId =
        isset($item["product_id"]) && $item["product_id"] !== ""
        ? (int)$item["product_id"]
        : null;

    $productName =
        trim((string)($item["name"] ?? ""));

    $productPrice =
        (float)($item["price"] ?? 0);

    $quantity =
        (int)($item["quantity"] ?? 0);


    if ($productName === "") {
        continue;
    }


    if ($productPrice < 0) {
        $productPrice = 0;
    }


    if ($quantity < 1) {
        $quantity = 1;
    }


    if ($quantity > 999) {
        $quantity = 999;
    }


    $itemTotal =
        $productPrice * $quantity;


    $subtotal += $itemTotal;


    $preparedItems[] = [
        "product_id" => $productId,
        "product_name" => $productName,
        "product_price" => $productPrice,
        "quantity" => $quantity,
        "item_total" => $itemTotal
    ];
}


if (count($preparedItems) === 0) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "অর্ডারে কোনো বৈধ পণ্য পাওয়া যায়নি।"
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/* =========================
   DISCOUNT LIMIT
========================= */

if ($discount > $subtotal) {
    $discount = $subtotal;
}


/* =========================
   PROMO + DELIVERY
========================= */

$promoRecord = null;

if ($promoCode !== "") {

    if ($subtotal < 500) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "প্রোমো কোড ব্যবহার করতে কমপক্ষে ৫০০ টাকার পণ্য কিনতে হবে।"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $promoRecord = findValidPromo(
        $pdo,
        $customerPhone,
        $promoCode,
        $subtotal
    );

    if (!$promoRecord) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "এই প্রোমো কোডটি আপনার জন্য বৈধ নয় বা মেয়াদ শেষ হয়েছে।"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Free-delivery promo always overrides the client-supplied delivery charge.
    $deliveryCharge = 0;
    $promoCode = strtoupper($promoCode);
}

/* =========================
   TOTAL
========================= */

$totalAmount =
    $subtotal
    + $deliveryCharge
    - $discount;


if ($totalAmount < 0) {
    $totalAmount = 0;
}


/* =========================
   SAVE ORDER
========================= */

try {

    $pdo->beginTransaction();


    /*
     * INSERT ORDER
     */

    $orderSql = "
        INSERT INTO orders (
            customer_name,
            customer_phone,
            customer_address,
            delivery_method,
            payment_method,
            subtotal,
            delivery_charge,
            discount,
            total_amount,
            promo_code,
            customer_note,
            status
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'pending'
        )
    ";


    $orderStmt =
        $pdo->prepare($orderSql);


    $orderStmt->execute([
        $customerName,
        $customerPhone,
        $customerAddress,
        $deliveryMethod,
        $paymentMethod,
        $subtotal,
        $deliveryCharge,
        $discount,
        $totalAmount,
        $promoCode !== "" ? $promoCode : null,
        $customerNote !== "" ? $customerNote : null
    ]);


    /*
     * GET ORDER ID
     */

    $orderId =
        (int)$pdo->lastInsertId();


    if ($orderId <= 0) {

        throw new Exception(
            "Order ID তৈরি করা যায়নি।"
        );
    }


    /* =========================
       CREATE 1-YEAR PROMO FOR 500+ ORDER
    ========================= */

    $generatedPromoCode = null;

    if (false && $subtotal >= 500) {

        $customerUserId = getOrCreateCustomer(
            $pdo,
            $customerName,
            $customerPhone
        );

        $existingPromoStmt = $customerUserId !== null
            ? $pdo->prepare("SELECT up.id FROM user_promos up WHERE up.user_id = ? AND up.status = 'active' AND (up.expires_at IS NULL OR up.expires_at >= NOW()) LIMIT 1")
            : null;

        if ($customerUserId !== null) {
            $existingPromoStmt->execute([$customerUserId]);
        }

        if ($customerUserId !== null && $existingPromoStmt->fetchColumn() === false) {

            $generatedPromoCode = generateUniquePromoCode($pdo);

            $promoStartsAt = date('Y-m-d H:i:s');
            $promoExpiresAt = date('Y-m-d H:i:s', strtotime('+1 year'));

            $promoInsert = $pdo->prepare("
                INSERT INTO promo_codes (
                    code,
                    description,
                    discount_type,
                    discount_value,
                    minimum_order_amount,
                    starts_at,
                    expires_at,
                    usage_limit,
                    used_count,
                    status
                )
                VALUES (?, ?, 'free_delivery', 0, 500, ?, ?, 1, 0, 'active')
            ");

            $promoInsert->execute([
                $generatedPromoCode,
                '৳500 বা তার বেশি অর্ডারের জন্য ১ বছরের Free Delivery Promo',
                $promoStartsAt,
                $promoExpiresAt
            ]);

            $promoCodeId = (int)$pdo->lastInsertId();

            $userPromoInsert = $pdo->prepare("
                INSERT INTO user_promos (
                    user_id,
                    promo_code_id,
                    starts_at,
                    expires_at,
                    usage_count,
                    status
                )
                VALUES (?, ?, ?, ?, 0, 'active')
            ");

            $userPromoInsert->execute([
                $customerUserId,
                $promoCodeId,
                $promoStartsAt,
                $promoExpiresAt
            ]);
        }
    }


    /*
     * INSERT ORDER ITEMS
     */

    $itemSql = "
        INSERT INTO order_items (
            order_id,
            product_id,
            product_name,
            product_price,
            quantity,
            item_total
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ";


    $itemStmt =
        $pdo->prepare($itemSql);


    foreach ($preparedItems as $item) {

        $itemStmt->execute([

            $orderId,

            $item["product_id"],

            $item["product_name"],

            $item["product_price"],

            $item["quantity"],

            $item["item_total"]

        ]);
    }


    /* =========================
       CONSUME REDEEMED PROMO
    ========================= */

    if (false && $promoRecord !== null) {

        $consumeUserPromo = $pdo->prepare("
            UPDATE user_promos
            SET usage_count = usage_count + 1,
                status = 'used'
            WHERE id = ?
              AND status = 'active'
              AND usage_count = 0
        ");

        $consumeUserPromo->execute([
            (int)$promoRecord['user_promo_id']
        ]);

        if ($consumeUserPromo->rowCount() !== 1) {
            throw new Exception(
                "প্রোমো কোডটি ইতিমধ্যে ব্যবহার করা হয়েছে।"
            );
        }

        $consumePromo = $pdo->prepare("
            UPDATE promo_codes
            SET used_count = used_count + 1
            WHERE id = ?
              AND status = 'active'
              AND (usage_limit IS NULL OR usage_limit = 0 OR used_count < usage_limit)
        ");

        $consumePromo->execute([
            (int)$promoRecord['promo_code_id']
        ]);

        if ($consumePromo->rowCount() !== 1) {
            throw new Exception(
                "প্রোমো কোডটি আর ব্যবহার করা যাবে না।"
            );
        }
    }


    /*
     * COMMIT
     */

    $pdo->commit();


    /* =========================
       SUCCESS RESPONSE
    ========================= */

    echo json_encode([

        "success" => true,

        "message" => "অর্ডার সফলভাবে সংরক্ষণ হয়েছে।",

        "order_id" => $orderId,

        "subtotal" =>
            number_format($subtotal, 2, ".", ""),

        "delivery_charge" =>
            number_format($deliveryCharge, 2, ".", ""),

        "discount" =>
            number_format($discount, 2, ".", ""),

        "total_amount" =>
            number_format($totalAmount, 2, ".", ""),

        "promo_code" => $generatedPromoCode

    ], JSON_UNESCAPED_UNICODE);

    exit;


} catch (Throwable $e) {


    /* =========================
       ROLLBACK
    ========================= */

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    http_response_code(500);


    echo json_encode([

        "success" => false,

        "message" => "DB ERROR: " . $e->getMessage()

    ], JSON_UNESCAPED_UNICODE);

    exit;
}