<?php
require_once __DIR__ . '/config.php';

$pdo = null;
$dbError = "";
$categories = [];
$latestProducts = [];
$freeProducts = [];
$popularProducts = [];

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function productImage(?string $path): string {
    $path = trim((string)$path);

    if ($path === '') {
        return 'https://placehold.co/600x600?text=Product';
    }

    // Already an absolute URL
    if (preg_match('#^(https?:)?//#i', $path)) {
        return $path;
    }

    // Remove leading slash
    $path = ltrim($path, '/');

    // Keep local paths relative so the site also works from a subdirectory.
    if (strpos($path, 'admin/uploads/products/') === 0) {
        return $path;
    }

    // If database contains uploads/products/filename
    if (strpos($path, 'uploads/products/') === 0) {
        return 'admin/' . $path;
    }

    // If database contains only filename
    if (strpos($path, 'products/') === 0) {
        return 'admin/uploads/' . $path;
    }

    return $path;

}

function productDiscount($price, $regularPrice): ?int {
    $price = (float)$price;
    $regularPrice = (float)$regularPrice;
    if ($regularPrice > 0 && $regularPrice > $price) {
        return (int)round((($regularPrice - $price) / $regularPrice) * 100);
    }
    return null;
}

function renderStars($rating): string {
    $rating = max(0, min(5, (float)$rating));
    $full = (int)floor($rating);
    $half = (($rating - $full) >= 0.5) ? 1 : 0;
    $empty = 5 - $full - $half;
    return str_repeat('★', $full)
        . ($half ? '½' : '')
        . str_repeat('☆', $empty);
}

function loadProductMedia(PDO $pdo, array &$products): void {
    $ids = array_values(array_filter(array_map(
        static fn(array $product): int => (int)($product['id'] ?? 0),
        $products
    )));
    if ($ids === []) return;

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, product_id, media_type, media_mime FROM product_media WHERE product_id IN ($placeholders) ORDER BY product_id, sort_order, id");
    $stmt->execute($ids);
    $mediaByProduct = [];
    foreach ($stmt->fetchAll() as $media) {
        $mediaByProduct[(int)$media['product_id']][] = [
            'type' => $media['media_type'],
            'mime' => $media['media_mime'],
            'url' => 'image-api.php?type=product_media&media_id=' . (int)$media['id']
        ];
    }
    foreach ($products as &$product) {
        $product['media'] = $mediaByProduct[(int)$product['id']] ?? [];
    }
    unset($product);
}

try {
    $pdo = db();

    // Categories
    $categories = $pdo->query("SELECT id, name, image_data, image_mime FROM categories ORDER BY name ASC")->fetchAll();

    // Latest active products - this makes every newly added product appear on the website.
    $latestStmt = $pdo->query("
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
            c.name AS category_name,
            COALESCE(ps.sold_count, 0) AS sold_count,
            COALESCE(ps.star_rating, 0) AS star_rating,
            COALESCE(ps.show_sold_count, 1) AS show_sold_count,
            COALESCE(ps.show_star_rating, 1) AS show_star_rating
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN product_stats ps ON ps.product_id = p.id
        WHERE p.status = 'active'
        ORDER BY p.id DESC
        LIMIT 24
    ");
    $latestProducts = $latestStmt->fetchAll();

    // Free delivery products
    $freeStmt = $pdo->query("
        SELECT
            p.id, p.category_id, p.name, p.slug, p.description, p.price,
            p.regular_price, p.stock, p.image_url, p.status, p.is_popular,
            p.is_free_delivery, p.show_offer_badge, p.offer_badge_text,
            c.name AS category_name,
            COALESCE(ps.sold_count, 0) AS sold_count,
            COALESCE(ps.star_rating, 0) AS star_rating,
            COALESCE(ps.show_sold_count, 1) AS show_sold_count,
            COALESCE(ps.show_star_rating, 1) AS show_star_rating
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN product_stats ps ON ps.product_id = p.id
        WHERE p.status = 'active' AND p.is_free_delivery = 1
        ORDER BY p.id DESC
        LIMIT 12
    ");
    $freeProducts = $freeStmt->fetchAll();

    // Popular products
    $popularStmt = $pdo->query("
        SELECT
            p.id, p.category_id, p.name, p.slug, p.description, p.price,
            p.regular_price, p.stock, p.image_url, p.status, p.is_popular,
            p.is_free_delivery, p.show_offer_badge, p.offer_badge_text,
            c.name AS category_name,
            COALESCE(ps.sold_count, 0) AS sold_count,
            COALESCE(ps.star_rating, 0) AS star_rating,
            COALESCE(ps.show_sold_count, 1) AS show_sold_count,
            COALESCE(ps.show_star_rating, 1) AS show_star_rating
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN product_stats ps ON ps.product_id = p.id
        WHERE p.status = 'active' AND p.is_popular = 1
        ORDER BY p.id DESC
        LIMIT 12
    ");
    $popularProducts = $popularStmt->fetchAll();
    loadProductMedia($pdo, $latestProducts);
    loadProductMedia($pdo, $freeProducts);
    loadProductMedia($pdo, $popularProducts);

} catch (Throwable $e) {
    $dbError = 'পণ্যের তথ্য লোড করা যাচ্ছে না। Database connection/structure চেক করুন।';
}

function renderProductCard(array $product): void {
    $name = $product['name'] ?? 'Product';
    $price = (float)($product['price'] ?? 0);
    $regular = (float)($product['regular_price'] ?? 0);
    $rating = (float)($product['star_rating'] ?? 0);
    $sold = (int)($product['sold_count'] ?? 0);
    $showRating = !empty($product['show_star_rating']);
    $showSold = !empty($product['show_sold_count']);
    $discount = productDiscount($price, $regular);
    $image = productImage($product['image_url'] ?? '');
    $media = $product['media'] ?? [];
    if ($media === [] && $image !== '') {
        $media = [['type' => 'image', 'mime' => 'image/*', 'url' => $image]];
    }
    $category = $product['category_name'] ?? 'Uncategorized';
    $offerText = trim((string)($product['offer_badge_text'] ?? ''));
    $showOffer = !empty($product['show_offer_badge']) && $offerText !== '';
    ?>
    <?php
    $productDetails = [
        'id' => (int)($product['id'] ?? 0),
        'name' => $name,
        'description' => trim((string)($product['description'] ?? '')),
        'price' => $price,
        'regular_price' => $regular,
        'image' => $image,
        'media' => $media,
        'category' => $category,
        'rating' => $rating,
        'sold' => $sold,
        'show_rating' => $showRating,
        'show_sold' => $showSold,
    ];
    $productDetailsJson = htmlspecialchars(
        json_encode(
            $productDetails,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        ),
        ENT_QUOTES,
        'UTF-8'
    );
    ?>
    <article class="product-card" onclick='openProductDetails(<?= $productDetailsJson ?>)'>
        <div class="product-image">
            <?php if ($showOffer): ?>
                <span class="discount"><?= e($offerText) ?></span>
            <?php elseif ($discount !== null): ?>
                <span class="discount">-<?= $discount ?>%</span>
            <?php endif; ?>

            <button class="wishlist" type="button" aria-label="Wishlist" data-product-id="<?= (int)($product['id'] ?? 0) ?>" onclick="event.stopPropagation(); toggleWishlist(this, <?= $productDetailsJson ?>)">
                <i class="fa-regular fa-heart"></i>
            </button>

            <div class="product-media-stage">
                <?php foreach ($media as $mediaIndex => $mediaItem): ?>
                    <?php if (($mediaItem['type'] ?? '') === 'video'): ?>
                        <video src="<?= e($mediaItem['url']) ?>" muted loop playsinline preload="metadata" data-media-index="<?= $mediaIndex ?>"></video>
                    <?php else: ?>
                        <img src="<?= e($mediaItem['url']) ?>" alt="<?= e($name) ?>" loading="lazy" data-media-index="<?= $mediaIndex ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="product-info">
            <span class="category-name"><?= e($category) ?></span>
            <h3><?= e($name) ?></h3>

            <?php if ($showRating): ?>
                <div class="rating">
                    <?= e(renderStars($rating)) ?>
                    <span>(<?= e(number_format($rating, 1)) ?>)</span>
                </div>
            <?php endif; ?>

            <div class="price">
                <strong>৳ <?= e(number_format($price, 0)) ?></strong>
                <?php if ($regular > $price): ?>
                    <del>৳ <?= e(number_format($regular, 0)) ?></del>
                <?php endif; ?>
            </div>

            <?php if ($showSold): ?>
                <div class="sold">
                    
                Sold <?= e(number_format($sold)) ?>
                </div>
            <?php endif; ?>

                <button class="add-cart" type="button"
                    onclick='event.stopPropagation(); addToCart(<?= (int)($product["id"] ?? 0) ?>, <?= htmlspecialchars(json_encode($name, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, "UTF-8") ?>, <?= json_encode($price) ?>, <?= htmlspecialchars(json_encode($image, JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, "UTF-8") ?>)'>
                <i class="fa-solid fa-cart-plus"></i>
                Add to Cart
            </button>

                <button class="buy-now" type="button"
                    onclick='event.stopPropagation(); buyNow(<?= (int)($product["id"] ?? 0) ?>, <?= htmlspecialchars(json_encode($name, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, "UTF-8") ?>, <?= json_encode($price) ?>, <?= htmlspecialchars(json_encode($image, JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, "UTF-8") ?>)'>
                Buy Now
            </button>
        </div>
    </article>
    <?php
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>FREEDelivery.com</title>

    <link rel="stylesheet" href="./style.css?v=10">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <script src="https://unpkg.com/lucide@latest"></script>

<style>
/* ================= PRODUCT DETAILS MODAL ================= */
.product-details {
    text-align: left;
    padding: 4px;
}

.product-details-image {
    width: 100%;
    max-height: 320px;
    object-fit: contain;
    display: block;
    border-radius: 14px;
    background: #f8fafc;
    margin-bottom: 16px;
}

.product-details-category {
    display: inline-block;
    font-size: 12px;
    color: #16a34a;
    background: #f0fdf4;
    padding: 5px 9px;
    border-radius: 999px;
    margin-bottom: 8px;
}

.product-details h2 {
    margin: 0 0 8px;
    font-size: 22px;
    line-height: 1.35;
    color: #111827;
}

.product-details-rating {
    color: #f59e0b;
    margin-bottom: 8px;
    font-size: 14px;
}

.product-details-rating span {
    color: #6b7280;
    margin-left: 5px;
}

.product-details-price {
    display: flex;
    align-items: center;
    gap: 9px;
    flex-wrap: wrap;
    margin: 8px 0 14px;
}

.product-details-price strong {
    font-size: 24px;
    color: #16a34a;
}

.product-details-price del {
    color: #9ca3af;
    font-size: 15px;
}

.product-details-sold {
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 14px;
}

.product-details-description-title {
    font-size: 17px;
    font-weight: 700;
    margin: 12px 0 7px;
    color: #111827;
}

.product-details-description {
    white-space: pre-wrap;
    line-height: 1.75;
    color: #374151;
    font-size: 14px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 16px;
}

.product-details-order {
    width: 100%;
    border: 0;
    border-radius: 10px;
    padding: 13px 16px;
    background: #111827;
    color: #fff;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
}

.product-details-cart {
    width: 100%;
    border: 0;
    border-radius: 10px;
    padding: 13px 16px;
    background: #16a34a;
    color: #fff;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    margin-top: 9px;
}
</style>

</head>

<body>

<!-- ================= HEADER ================= -->
<header class="header">

    <div class="top-header">

        <button class="menu-btn" onclick="openMenu()">
            <i class="fa-solid fa-ellipsis-vertical"></i>
        </button>

        <div class="logo">
            <span>Freedelivery.com </span>
        </div>

        <div class="header-icons">
            <button onclick="openCart()">
                <i class="fa-solid fa-cart-shopping"></i>
                <b id="cartCount">0</b>
            </button>

            <button onclick="openAccount()">
                <i class="fa-regular fa-user"></i>
            </button>
        </div>

    </div>

    <div class="search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text"
               id="searchInput"
               placeholder="আপনার পছন্দের পণ্য খুঁজুন..."
               onkeyup="searchProducts()">
        <select id="searchCategory" aria-label="ক্যাটাগরি নির্বাচন করুন" onchange="searchProducts()">
            <option value="">সব ক্যাটাগরি</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= e(mb_strtolower((string)$category['name'])) ?>"><?= e($category['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

</header>


<!-- ================= SIDE MENU ================= -->
<div class="menu-overlay" id="menuOverlay" onclick="closeMenu()"></div>

<aside class="side-menu" id="sideMenu">

    <div class="menu-header">
        <h2>Menu</h2>
        <button onclick="closeMenu()">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <div class="menu-list">

        <a href="#" onclick="closeMenu()">
            <i class="fa-solid fa-house"></i>
            Home
        </a>

        <a href="#categories" onclick="closeMenu()">
            <i class="fa-solid fa-layer-group"></i>
            Category
        </a>

        <a href="about.php" onclick="closeMenu()">
            <i class="fa-solid fa-circle-info"></i>
            About
        </a>

        <a href="#" onclick="openCart(); closeMenu();">
            <i class="fa-solid fa-cart-shopping"></i>
            Cart
        </a>

        <a href="order-tracking.php">
            <i class="fa-solid fa-location-dot"></i>
            Order Tracking
        </a>

        <a href="#promo" onclick="openPromo(); closeMenu();">
            <i class="fa-solid fa-gift"></i>
            Get Promo Code
        </a>

        <a href="#" onclick="openLogin(); closeMenu();">
            <i class="fa-solid fa-right-to-bracket"></i>
            Login
        </a>

        <a href="#" onclick="openSignup(); closeMenu();">
            <i class="fa-solid fa-user-plus"></i>
            Sign Up
        </a>

    </div>

</aside>


<!-- ================= HERO ================= -->
<section class="hero-section">

    <div class="hero-slider" id="heroSlider">

        <div class="hero-slide active">
            <div class="hero-content">
                <small>১০ সেপ্টেম্বর — ১৫ সেপ্টেম্বর</small>

                <h1>
                    মাত্র ৫০০ টাকার কেনাকাটায়
                    পাচ্ছেন ১ বছরের
                    <strong>ফ্রি ডেলিভারি!</strong>
                </h1>

                <p>
                    সকল পণ্যে ব্যবহার করুন আপনার
                    ১ বছরের ফ্রি ডেলিভারি প্রোমো কোড।
                </p>

                <button onclick="openPromo()">
                    প্রোমো কোড নিন
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>


        <div class="hero-slide">
            <div class="hero-content">

                <small>FREE Delivery</small>

                <h1>
                    প্রোমো কোড ছাড়াই
                    <strong>ফ্রি ডেলিভারি</strong>
                </h1>

                <p>
                    ফ্রি ডেলিভারি আইটেম থেকে
                    যেকোনো কিছু কিনুন।
                    ডেলিভারি চার্জ সম্পূর্ণ ফ্রি।
                </p>

                <a href="#freeDelivery" class="hero-btn">
                    এখনই কেনাকাটা করুন
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

            </div>
        </div>

    </div>

    <div class="slider-dots" id="heroDots">
        <span class="dot active" onclick="showSlide(0)"></span>
        <span class="dot" onclick="showSlide(1)"></span>
    </div>

</section>


<!-- ================= QUICK BOX ================= -->
<section class="quick-section">

    <div class="quick-box" onclick="openPromo()">
        <div class="quick-icon">
            <i class="fa-solid fa-gift"></i>
        </div>

        <h3>প্রোমো কোড নিন</h3>
        <p>বিশেষ অফার পেতে</p>
    </div>


    <div class="quick-box" onclick="openVision()">
        <div class="quick-icon">
            <i class="fa-solid fa-bullseye"></i>
        </div>

        <h3>ভিশন ও মিশন</h3>
        <p>আমাদের সম্পর্কে জানুন</p>
    </div>


    <a class="quick-box whatsapp"
       href="https://api.whatsapp.com/send?phone=8801617064340"
       target="_blank">

        <div class="quick-icon">
            <i class="fa-brands fa-whatsapp"></i>
        </div>

        <h3>WhatsApp</h3>
        <p>আমাদের সাথে যোগাযোগ করুন</p>

    </a>

</section>


<?php if ($dbError !== ""): ?>
<div style="max-width:1150px;margin:15px auto;padding:12px 15px;background:#fff3cd;color:#664d03;border:1px solid #ffecb5;border-radius:10px;text-align:center;">
    <?= e($dbError) ?>
</div>
<?php endif; ?>

<!-- ================= CATEGORY ================= -->
<section class="section" id="categories">

    <div class="section-title">
        <h2>ক্যাটাগরি</h2>
        <a href="#products">সব দেখুন</a>
    </div>

    <div class="category-grid">
        <?php if (!empty($categories)): ?>
            <?php foreach ($categories as $category): ?>
                <a class="category-card" href="?category=<?= (int)$category['id'] ?>#products">
                    <?php if (!empty($category['image_data']) && !empty($category['image_mime'])): ?>
                        <img class="category-image" src="image-api.php?type=category&id=<?= (int)$category['id'] ?>" alt="<?= e($category['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="category-icon"><i class="fa-solid fa-layer-group"></i></div>
                    <?php endif; ?>
                    <span><?= e($category['name']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="category-card"><span>কোনো ক্যাটাগরি পাওয়া যায়নি</span></div>
        <?php endif; ?>
    </div>


</section>


<!-- ================= LATEST PRODUCTS ================= -->
<section class="section" id="products">
    <div class="section-title">
        <div>
            <span class="section-label">LATEST</span>
            <h2>নতুন প্রোডাক্ট</h2>
        </div>
        <a href="#products">সব দেখুন</a>
    </div>

    <div class="product-grid">
        <?php
        $selectedCategory = isset($_GET['category']) ? (int)$_GET['category'] : 0;
        $displayLatest = $latestProducts;
        if ($selectedCategory > 0 && $pdo) {
            try {
                $catProductStmt = $pdo->prepare("
                    SELECT
                        p.id, p.category_id, p.name, p.slug, p.description, p.price,
                        p.regular_price, p.stock, p.image_url, p.status, p.is_popular,
                        p.is_free_delivery, p.show_offer_badge, p.offer_badge_text,
                        c.name AS category_name,
                        COALESCE(ps.sold_count, 0) AS sold_count,
                        COALESCE(ps.star_rating, 0) AS star_rating,
                        COALESCE(ps.show_sold_count, 1) AS show_sold_count,
                        COALESCE(ps.show_star_rating, 1) AS show_star_rating
                    FROM products p
                    LEFT JOIN categories c ON c.id = p.category_id
                    LEFT JOIN product_stats ps ON ps.product_id = p.id
                    WHERE p.status = 'active' AND p.category_id = ?
                    ORDER BY p.id DESC
                    LIMIT 24
                ");
                $catProductStmt->execute([$selectedCategory]);
                $displayLatest = $catProductStmt->fetchAll();
                loadProductMedia($pdo, $displayLatest);
            } catch (Throwable $e) {
                $displayLatest = [];
            }
        }
        ?>

        <?php if (!empty($displayLatest)): ?>
            <?php foreach ($displayLatest as $product): ?>
                <?php renderProductCard($product); ?>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="grid-column:1/-1;text-align:center;color:#6b7280;padding:25px 10px;">এখনো কোনো Active Product নেই। Admin Panel থেকে Product যোগ করুন।</p>
        <?php endif; ?>
    </div>
</section>

<!-- ================= FREE DELIVERY ================= -->
<section class="section" id="freeDelivery">

    <div class="section-title">
        <div>
            <span class="section-label">FREE DELIVERY</span>
            <h2>ফ্রি ডেলিভারি আইটেম</h2>
        </div>

        <a href="#freeProducts">সব দেখুন</a>
    </div>


    <div class="product-grid" id="freeProducts">
        <?php if (!empty($freeProducts)): ?>
            <?php foreach ($freeProducts as $product): ?>
                <?php renderProductCard($product); ?>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="grid-column:1/-1;text-align:center;color:#6b7280;padding:25px 10px;">এখনো কোনো ফ্রি ডেলিভারি প্রোডাক্ট যোগ করা হয়নি।</p>
        <?php endif; ?>
    </div>

    </div>

</section>


<!-- ================= POPULAR ================= -->
<section class="section" id="popular">

    <div class="section-title">

        <div>
            <span class="section-label">TRENDING</span>
            <h2>জনপ্রিয় প্রোডাক্ট সমূহ</h2>
        </div>

        <a href="#popular">সব দেখুন</a>

    </div>


    <div class="product-grid">
        <?php if (!empty($popularProducts)): ?>
            <?php foreach ($popularProducts as $product): ?>
                <?php renderProductCard($product); ?>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="grid-column:1/-1;text-align:center;color:#6b7280;padding:25px 10px;">কোনো Popular Product নেই।</p>
        <?php endif; ?>
    </div>

    </div>

</section>

<section class="social-visit" aria-label="Social contact">
    <a href="https://www.facebook.com/" target="_blank" rel="noopener">
        <i class="fa-brands fa-facebook"></i>
        আমাদের ফেসবুক পেজ ভিজিট করুন
    </a>
    <div class="contact-actions" aria-label="Contact us">
        <a class="contact-button contact-messenger" href="https://m.me/1299468436578178" target="_blank" rel="noopener">
            <i class="fa-brands fa-facebook-messenger"></i>
            Messenger-এ যোগাযোগ করুন
        </a>
        <a class="contact-button contact-whatsapp" href="https://wa.me/8801617064340" target="_blank" rel="noopener">
            <i class="fa-brands fa-whatsapp"></i>
            WhatsApp-এ যোগাযোগ করুন
        </a>
    </div>
</section>


<!-- ================= TRUST ================= -->
<section class="trust-section">

    <div class="trust-item">
        <i class="fa-solid fa-truck-fast"></i>
        <h3>দ্রুত ডেলিভারি</h3>
        <p>সারা বাংলাদেশে</p>
    </div>

    <div class="trust-item">
        <i class="fa-solid fa-shield-halved"></i>
        <h3>নিরাপদ কেনাকাটা</h3>
        <p>বিশ্বস্ত সার্ভিস</p>
    </div>

    <div class="trust-item">
        <i class="fa-solid fa-money-bill-wave"></i>
        <h3>Cash on Delivery</h3>
        <p>পণ্য হাতে পেয়ে মূল্য পরিশোধ</p>
    </div>

    <div class="trust-item">
        <i class="fa-solid fa-headset"></i>
        <h3>Support Team</h3>
        <p>প্রয়োজনে আমাদের সাথে যোগাযোগ করুন</p>
    </div>

</section>


<!-- ================= FOOTER ================= -->
<footer class="footer" id="about">

    <div class="footer-logo">
        <span>FREE</span>Delivery.com
    </div>

    <p>
        অনলাইন কেনাকাটাকে সহজ ও বিশ্বস্ত করে তোলা।
        ডেলিভারি চার্জের ঝামেলা দূর করুন।
    </p>

    <div class="footer-links">

        <a href="#">About Us</a>
        <a href="#categories">Categories</a>
        <a href="#tracking">Order Tracking</a>
        <a href="#promo">Promo Code</a>

    </div>

    <div class="social">

        <a href="https://m.me/1299468436578178" target="_blank" rel="noopener" aria-label="Messenger"><i class="fa-brands fa-facebook-messenger"></i></a>

        <a href="https://api.whatsapp.com/send?phone=8801617064340" target="_blank">
            <i class="fa-brands fa-whatsapp"></i>
        </a>

    </div>

    <div class="copyright">
        © 2026 FREEDelivery.com — All Rights Reserved.
    </div>

</footer>


<!-- ================= BOTTOM NAV ================= -->
<nav class="bottom-nav">

    <a href="#categories">
        <i class="fa-solid fa-layer-group"></i>
        <span>Category</span>
    </a>

    <a href="#" onclick="openPromo()">
        <i class="fa-solid fa-gift"></i>
        <span>Promo Code</span>
    </a>

    <a href="#" onclick="openAccount()">
        <i class="fa-regular fa-user"></i>
        <span>Account</span>
    </a>

    <a href="#" class="active">
        <i class="fa-solid fa-house"></i>
        <span>Home</span>
    </a>

    <a href="https://wa.me/8801617064340" target="_blank">
        <i class="fa-brands fa-whatsapp"></i>
        <span>WhatsApp</span>
    </a>

</nav>

<div class="floating-contact" aria-label="Contact us">
    <a class="floating-messenger" href="https://m.me/1299468436578178" target="_blank" rel="noopener" aria-label="Messenger">
        <i class="fa-brands fa-facebook-messenger"></i>
    </a>
    <a class="floating-whatsapp" href="https://wa.me/8801617064340" target="_blank" rel="noopener" aria-label="WhatsApp">
        <i class="fa-brands fa-whatsapp"></i>
    </a>
</div>


<!-- ================= MODAL ================= -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal()">

    <div class="modal" onclick="event.stopPropagation()">

        <button class="modal-close" onclick="closeModal()">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div id="modalContent"></div>

    </div>

</div>



<script>
function openProductDetails(product) {
    const modalOverlay = document.getElementById("modalOverlay");
    const modalContent = document.getElementById("modalContent");

    if (!modalOverlay || !modalContent) return;

    const name = String(product.name || "Product");
    const description = String(product.description || "").trim();
    const price = Number(product.price || 0);
    const regular = Number(product.regular_price || 0);
    const media = Array.isArray(product.media) && product.media.length
        ? product.media
        : [{type: "image", url: String(product.image || "https://placehold.co/600x600?text=Product")}];
    const category = String(product.category || "Uncategorized");
    const rating = Number(product.rating || 0);
    const sold = Number(product.sold || 0);

    let stars = "";
    if (product.show_rating) {
        const rounded = Math.max(0, Math.min(5, Math.round(rating)));
        stars = "★".repeat(rounded) + "☆".repeat(5 - rounded);
    }

    const safe = (value) => {
        const div = document.createElement("div");
        div.textContent = value;
        return div.innerHTML;
    };

    const safeName = safe(name);
    const safeCategory = safe(category);
    const safeDescription = safe(description || "এই প্রোডাক্টের কোনো বিস্তারিত বিবরণ দেওয়া হয়নি।");
    window.productMediaIndex = 0;

    modalContent.innerHTML = `
        <div class="product-details">
            <div class="product-details-gallery">
                <button type="button" class="gallery-arrow" aria-label="Previous media" onclick="changeProductMedia(-1)"><i class="fa-solid fa-chevron-left"></i></button>
                <div class="product-details-media-stage" id="productDetailsMediaStage">${media.map((item, index) => item.type === "video"
                    ? `<video class="product-details-media ${index === 0 ? "active" : ""}" src="${safe(item.url)}" controls playsinline preload="metadata"></video>`
                    : `<img class="product-details-media ${index === 0 ? "active" : ""}" src="${safe(item.url)}" alt="${safeName}" loading="eager">`).join("")}</div>
                <button type="button" class="gallery-arrow" aria-label="Next media" onclick="changeProductMedia(1)"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
            <div class="product-media-thumbs">${media.map((item, index) => `<button type="button" class="${index === 0 ? "active" : ""}" onclick="showProductMedia(${index})" aria-label="View media ${index + 1}">${index + 1}</button>`).join("")}</div>

            <span class="product-details-category">${safeCategory}</span>
            <h2>${safeName}</h2>

            ${product.show_rating ? `
                <div class="product-details-rating">
                    ${stars}
                    <span>(${rating.toFixed(1)})</span>
                </div>
            ` : ""}

            <div class="product-details-price">
                <strong>৳ ${price.toLocaleString("en-US", {maximumFractionDigits: 0})}</strong>
                ${regular > price ? `<del>৳ ${regular.toLocaleString("en-US", {maximumFractionDigits: 0})}</del>` : ""}
            </div>

            ${product.show_sold ? `
                <div class="product-details-sold">
                    Sold ${sold.toLocaleString("en-US")}
                </div>
            ` : ""}

            <button type="button" class="product-details-order"
                    onclick="event.stopPropagation(); buyNow(${Number(product.id) || 0}, ${JSON.stringify(name)}, ${JSON.stringify(price)}, ${JSON.stringify(media[0].url || "")})">
                <i class="fa-solid fa-bag-shopping"></i>
                অর্ডার করুন
            </button>

            <button type="button" class="product-details-cart"
                    onclick="event.stopPropagation(); addToCart(${Number(product.id) || 0}, ${JSON.stringify(name)}, ${JSON.stringify(price)}, ${JSON.stringify(media[0].url || "")})">
                <i class="fa-solid fa-cart-plus"></i>
                কার্টে যোগ করুন
            </button>

            <a class="product-details-contact"
               href="https://wa.me/8801617064340?text=${encodeURIComponent('আমি এই পণ্যটি সম্পর্কে জানতে চাই: ' + name)}"
               target="_blank"
               rel="noopener">
                <i class="fa-brands fa-whatsapp"></i>
                WhatsApp-এ যোগাযোগ করুন
            </a>

            <div class="product-details-description-title">পণ্যের বিবরণ</div>
            <div class="product-details-description">${safeDescription}</div>
        </div>
    `;

    modalOverlay.classList.add("show");
    document.body.style.overflow = "hidden";
}

function showProductMedia(index) {
    const items = document.querySelectorAll("#productDetailsMediaStage .product-details-media");
    const thumbs = document.querySelectorAll(".product-media-thumbs button");
    if (!items.length) return;
    window.productMediaIndex = (index + items.length) % items.length;
    items.forEach((item, itemIndex) => item.classList.toggle("active", itemIndex === window.productMediaIndex));
    thumbs.forEach((button, btnIndex) => button.classList.toggle("active", btnIndex === window.productMediaIndex));
}

function changeProductMedia(change) {
    showProductMedia((window.productMediaIndex || 0) + change);
}

/* product card hover slideshow */
document.querySelectorAll(".product-card").forEach((card) => {
    const media = Array.from(card.querySelectorAll(".product-media-stage img, .product-media-stage video"));
    if (media.length < 2) return;
    let index = 0;
    let timer = null;
    media.forEach((item, itemIndex) => item.classList.toggle("active", itemIndex === 0));
    card.addEventListener("mouseenter", () => {
        timer = setInterval(() => {
            media[index].classList.remove("active");
            index = (index + 1) % media.length;
            media[index].classList.add("active");
            if (media[index].tagName === "VIDEO") media[index].play().catch(() => {});
        }, 1200);
    });
    card.addEventListener("mouseleave", () => {
        clearInterval(timer);
        timer = null;
        media.forEach((item, itemIndex) => {
            item.classList.toggle("active", itemIndex === 0);
            if (item.tagName === "VIDEO") item.pause();
        });
        index = 0;
    });
});
</script>

<script src="script.js?v=29" defer></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        if (window.lucide) lucide.createIcons();
    });
</script>
</body>
</html>