<?php
require_once __DIR__ . '/config.php';
/*
 * FreeDeliveri.com — Massage Gun Landing Page
 * ------------------------------------------------
 * 1) Put this file in the same folder as order-api.php
 * 2) Put the assets folder beside this file.
 * 3) Change the 4 settings below before publishing.
 */

$PRODUCT_NAME = "Mini Massage Gun SL-720"; // শুধু Product-এর নাম দিন; Product ID লাগবে না
$OFFER_PRICE  = 1290;    // অফার মূল্য
$OLD_PRICE    = 1690;    // আগের/বর্তমান মূল্য
$API_URL      = "order-api.php";

// Main website / database settings for the Free Delivery section
$SITE_URL = "https://freedeliveri.com";
$PRODUCT_ID = null;
$freeProducts = [];
try {
    $pdoLanding = db();

    // Main Landing Page product ID automatically find by product name.
    // তাই এখানে আলাদা করে Product ID বসানোর দরকার নেই।
    $mainProductStmt = $pdoLanding->prepare("
        SELECT id
        FROM products
        WHERE status = 'active' AND name = :name
        ORDER BY id DESC
        LIMIT 1
    ");
    $mainProductStmt->execute([':name' => $PRODUCT_NAME]);
    $PRODUCT_ID = $mainProductStmt->fetchColumn();
    if ($PRODUCT_ID === false) {
        $PRODUCT_ID = null;
    }

    $freeStmt = $pdoLanding->query("
        SELECT
            p.id, p.name, p.price, p.regular_price, p.image_url,
            p.is_free_delivery, p.show_offer_badge, p.offer_badge_text,
            c.name AS category_name,
            COALESCE(ps.star_rating, 0) AS star_rating,
            COALESCE(ps.show_star_rating, 1) AS show_star_rating
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN product_stats ps ON ps.product_id = p.id
        WHERE p.status = 'active' AND p.is_free_delivery = 1
        ORDER BY p.id DESC
        LIMIT 12
    ");
    $freeProducts = $freeStmt->fetchAll();
} catch (Throwable $e) {
    $freeProducts = [];
}

function landingImageUrl(?string $path): string {
    global $SITE_URL;
    $path = trim((string)$path);
    if ($path === '') return 'https://placehold.co/600x600?text=Product';
    if (preg_match('~^https?://~i', $path)) return $path;
    return rtrim($SITE_URL, '/') . '/' . ltrim($path, '/');
}

$images = [
    "assets/product-1.jpg",
    "assets/product-2.jpg",
    "assets/product-3.webp",
    "assets/product-4.jpg",
    "assets/product-5.webp",
    "assets/product-6.jpg"
];
?>
<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#071b16">
<title><?= htmlspecialchars($PRODUCT_NAME) ?> | FreeDeliveri.com</title>
<meta name="description" content="Mini Massage Gun SL-720 — ৬ স্পিড, ৪টি ম্যাসাজ হেড, রিচার্জেবল ও কমপ্যাক্ট ডিজাইন।">
<style>
:root{
  --bg:#f6f8f7; --ink:#12211d; --muted:#60706b; --brand:#087f63;
  --brand2:#0c5b49; --gold:#d89a22; --white:#fff; --danger:#b42318;
  --shadow:0 12px 35px rgba(10,35,28,.10); --radius:22px;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,"Noto Sans Bengali","Noto Sans",Arial,sans-serif;line-height:1.7}
a{text-decoration:none;color:inherit}
.container{width:min(980px,92%);margin:auto}
header{position:sticky;top:0;z-index:50;background:rgba(7,27,22,.96);backdrop-filter:blur(10px);color:#fff;box-shadow:0 4px 18px rgba(0,0,0,.12)}
.header-inner{min-height:62px;display:flex;align-items:center;justify-content:center;font-weight:900;letter-spacing:.3px;font-size:20px}
.hero{background:linear-gradient(145deg,#071b16,#0d604c);color:#fff;padding:26px 0 32px}
.badge{display:inline-flex;background:#fff1c9;color:#6b4600;padding:6px 13px;border-radius:999px;font-weight:800;font-size:13px}
.hero h1{font-size:clamp(28px,6vw,52px);line-height:1.15;margin:14px 0 8px}
.hero p{margin:0;color:#d7eee7;font-size:16px}
.gallery{margin-top:22px}
.main-img{background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 18px 45px rgba(0,0,0,.20)}
.main-img img{display:block;width:100%;aspect-ratio:1/1;object-fit:cover}
.thumbs{display:flex;gap:10px;overflow-x:auto;padding:12px 2px 4px;scrollbar-width:thin}
.thumb{flex:0 0 82px;height:82px;border:2px solid transparent;border-radius:14px;overflow:hidden;background:#fff;cursor:pointer;padding:0}
.thumb.active{border-color:#ffd36b}
.thumb img{width:100%;height:100%;object-fit:cover}
.offer{padding:28px 0}
.offer-card{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:25px;text-align:center;border:1px solid #e8eeeb}
.small{color:var(--muted);font-size:15px}
.old{text-decoration:line-through;color:#89938f;font-size:22px}
.price{font-size:clamp(38px,10vw,60px);line-height:1;color:var(--brand2);font-weight:950;margin:4px 0}
.save{display:inline-block;background:#e9f7f1;color:#08664f;padding:4px 12px;border-radius:999px;font-weight:800}
.cta{display:block;width:100%;border:0;border-radius:16px;padding:17px 20px;background:linear-gradient(135deg,#0a9a77,#056b56);color:#fff;font-size:20px;font-weight:900;cursor:pointer;box-shadow:0 10px 22px rgba(8,127,99,.25);margin-top:18px}
.cta:active{transform:translateY(1px)}
.section{padding:18px 0 35px}
.card{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:24px}
.section h2{font-size:clamp(24px,5vw,34px);line-height:1.25;margin:0 0 14px}
.lead{font-size:17px;color:#344740}
.features{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:18px}
.feature{background:#f1f7f4;border:1px solid #e0ece7;border-radius:16px;padding:14px}
.feature b{display:block;font-size:16px;margin-bottom:3px}
.feature span{font-size:13px;color:var(--muted)}
.photo-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.photo-grid img{width:100%;display:block;border-radius:18px;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.08)}
.order-section{background:#071b16;color:#fff;padding:34px 0}
.order-box{background:#fff;color:var(--ink);border-radius:24px;padding:22px;box-shadow:0 16px 45px rgba(0,0,0,.25)}
.order-box h2{text-align:center;margin-top:0}
.field{margin:13px 0}
.field label{display:block;font-weight:800;margin-bottom:6px}
.field input,.field textarea,.field select{width:100%;padding:14px;border:1px solid #cfdad5;border-radius:12px;font:inherit;outline:none}
.field input:focus,.field textarea:focus,.field select:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(8,127,99,.10)}
.qty-row{display:flex;gap:12px;align-items:center}
.qty-row input{max-width:120px}
.summary{background:#f3f7f5;border-radius:14px;padding:14px;margin-top:14px}
.summary div{display:flex;justify-content:space-between;margin:4px 0}
.total{font-size:20px;font-weight:950;border-top:1px dashed #ccd7d2;padding-top:8px;margin-top:8px}
.notice{font-size:13px;color:#66746f;margin-top:10px}
#result{display:none;margin-top:14px;padding:13px;border-radius:12px;font-weight:800}
.delivery-options{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:13px 0}
.delivery-option{position:relative}
.delivery-option input{position:absolute;opacity:0;pointer-events:none}
.delivery-option label{display:flex;align-items:center;justify-content:center;min-height:54px;border:2px solid #dbe6e2;border-radius:14px;background:#f8fbfa;font-weight:900;cursor:pointer;text-align:center;padding:8px}
.delivery-option input:checked + label{border-color:var(--brand);background:#e8f8f1;color:#075f49;box-shadow:0 0 0 3px rgba(8,127,99,.10)}
#successOverlay{display:none;position:fixed;inset:0;background:rgba(0,20,15,.55);z-index:99999;align-items:flex-start;justify-content:center;padding:18px 14px}
#successOverlay.show{display:flex}
.success-popup{width:min(560px,100%);margin-top:8px;background:#fff;border-radius:22px;padding:26px 20px 22px;text-align:center;box-shadow:0 20px 70px rgba(0,0,0,.35);border:3px solid #0a9a77;animation:successPop .25s ease-out}
.success-icon{font-size:54px;line-height:1;margin-bottom:8px}
.success-popup h2{margin:0 0 8px;font-size:clamp(28px,7vw,42px);color:#075f49}
.success-popup p{margin:5px 0;color:#465953;font-size:16px;font-weight:700}
.success-order-id{display:inline-block;margin:10px 0 14px;background:#e8f8f1;color:#075f49;border-radius:999px;padding:8px 14px;font-size:18px;font-weight:950}
.success-close{border:0;background:#071b16;color:#fff;border-radius:13px;padding:13px 24px;font:inherit;font-weight:900;cursor:pointer}
@keyframes successPop{from{transform:translateY(-20px) scale(.97);opacity:.5}to{transform:translateY(0) scale(1);opacity:1}}
.success{display:block!important;background:#e8f8f0;color:#075f49}
.error{display:block!important;background:#fff0ee;color:var(--danger)}
.free-items{padding:32px 0 60px}
.placeholder{border:2px dashed #cbd8d3;border-radius:20px;padding:28px;text-align:center;color:#65746f;background:#fff}
footer{background:#04110e;color:#a9c2ba;text-align:center;padding:22px;font-size:13px}
.free-heading{text-align:center;margin-bottom:20px}
.free-kicker{display:inline-block;background:#e8f8f1;color:#087f63;border-radius:999px;padding:5px 12px;font-size:12px;font-weight:900;letter-spacing:.5px}
.free-heading h2{margin:8px 0 3px;font-size:clamp(25px,6vw,36px)}
.free-heading p{margin:0;color:var(--muted)}
.free-product-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.free-product-card{background:#fff;border:1px solid #e5ece9;border-radius:20px;overflow:hidden;box-shadow:0 10px 28px rgba(10,35,28,.08)}
.free-product-image{position:relative;background:#f7faf9;aspect-ratio:1/1;overflow:hidden}
.free-product-image img{width:100%;height:100%;object-fit:cover;display:block}
.free-badge{position:absolute;left:9px;top:9px;z-index:2;background:#0a9a77;color:#fff;border-radius:999px;padding:5px 8px;font-size:10px;font-weight:900}
.free-product-info{padding:12px}
.free-product-info small{color:#6b7b75;font-size:11px}
.free-product-info h3{margin:3px 0 7px;font-size:14px;line-height:1.35;min-height:38px}
.free-rating{font-size:12px;margin-bottom:6px}
.free-price{display:flex;align-items:center;gap:8px}
.free-price strong{font-size:20px;color:#075f49}
.free-price del{font-size:13px;color:#929d98}
.free-buy{width:100%;margin-top:10px;border:0;border-radius:12px;padding:11px 8px;background:#071b16;color:#fff;font-weight:900;cursor:pointer;font-size:12px}
@media(min-width:700px){.free-product-grid{grid-template-columns:repeat(4,minmax(0,1fr))}.free-product-info h3{font-size:15px}}
@media(min-width:700px){
 .features{grid-template-columns:repeat(4,1fr)}
 .photo-grid{grid-template-columns:repeat(3,1fr)}
}
</style>
</head>
<body>
<div id="successOverlay" role="dialog" aria-modal="true" aria-labelledby="successTitle">
  <div class="success-popup">
    <div class="success-icon"><i class="fa-solid fa-circle-check"></i></div>
    <h2 id="successTitle">অর্ডার সফল হয়েছে!</h2>
    <p>আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে।</p>
    <div class="success-order-id" id="successOrderId">Order ID: #</div>
    <p>আমাদের টিম শিগগিরই আপনার সাথে যোগাযোগ করবে।</p>
    <button class="success-close" type="button" onclick="closeSuccessPopup()">ঠিক আছে</button>
  </div>
</div>

<header><div class="header-inner">FREEDELIVERI.COM</div></header>

<section class="hero">
<div class="container">
  <span class="badge"><i class="fa-solid fa-fire"></i> বিশেষ অফার — সীমিত সময়ের জন্য</span>
  <h1><?= htmlspecialchars($PRODUCT_NAME) ?></h1>
  <p>কমপ্যাক্ট ডিজাইন • ৬ স্পিড • ৪টি ম্যাসাজ হেড • রিচার্জেবল</p>

  <div class="gallery">
    <div class="main-img"><img id="mainImage" src="<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($PRODUCT_NAME) ?>"></div>
    <div class="thumbs">
      <?php foreach($images as $i=>$img): ?>
      <button class="thumb <?= $i===0?'active':'' ?>" onclick="showImage(<?= $i ?>)" aria-label="ছবি <?= $i+1 ?>">
        <img src="<?= htmlspecialchars($img) ?>" alt="Product photo <?= $i+1 ?>">
      </button>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</section>

<section class="offer">
<div class="container">
 <div class="offer-card">
   <div class="small">অফার মূল্য</div>
   <div class="price">৳<?= number_format($OFFER_PRICE) ?></div>
   <div class="old">৳<?= number_format($OLD_PRICE) ?></div>
   <span class="save">বিশেষ অফার</span>
   <button class="cta" onclick="document.getElementById('order').scrollIntoView({behavior:'smooth'})"><i class="fa-solid fa-bag-shopping"></i> এখনই অর্ডার করুন</button>
 </div>
</div>
</section>

<section class="section">
<div class="container">
 <div class="card">
   <h2>আপনার প্রতিদিনের আরাম এখন হাতের মুঠোয়</h2>
   <p class="lead">
   সারাদিন কাজ, ব্যায়াম বা দীর্ঘ সময় বসে থাকার পর শরীরের পেশিতে অস্বস্তি অনুভব হতে পারে।
   <?= htmlspecialchars($PRODUCT_NAME) ?> একটি ছোট ও ব্যবহারবান্ধব ম্যাসাজার, যা আপনার দৈনন্দিন
   রিল্যাক্সেশন রুটিনে সহজেই ব্যবহার করতে পারবেন।
   </p>

   <div class="features">
    <div class="feature"><b><i class="fa-solid fa-bolt"></i> ৬টি স্পিড লেভেল</b><span>প্রয়োজন অনুযায়ী ইন্টেনসিটি বেছে নিন</span></div>
    <div class="feature"><b><i class="fa-solid fa-battery-full"></i> রিচার্জেবল</b><span>ক্যাবল দিয়ে চার্জ করে ব্যবহার</span></div>
    <div class="feature"><b><i class="fa-solid fa-bullseye"></i> ৪টি ম্যাসাজ হেড</b><span>বিভিন্ন ধরনের ম্যাসাজের জন্য</span></div>
    <div class="feature"><b><i class="fa-solid fa-hand-holding-heart"></i> কমপ্যাক্ট ডিজাইন</b><span>ধরে ব্যবহার ও বহন করা সহজ</span></div>
   </div>

   <h2 style="margin-top:28px">কোথায় ব্যবহার করতে পারেন?</h2>
   <p>কাঁধ, পিঠ, হাত, পা ও অন্যান্য বড় পেশির উপর হালকা ম্যাসাজ/রিল্যাক্সেশনের জন্য ব্যবহার করা যায়। ব্যবহারের সময় আরামদায়ক মাত্রা থেকে শুরু করে ধীরে ধীরে প্রয়োজন অনুযায়ী বাড়ান।</p>

   <h2 style="margin-top:28px">বক্সে যা থাকছে</h2>
   <p>ম্যাসেজ গান • ৪টি ম্যাসাজ হেড • চার্জিং কেবল • প্যাকেজিং বক্স</p>

   <p class="notice">নোট: এটি ব্যক্তিগত রিল্যাক্সেশন/ম্যাসাজের পণ্য; কোনো রোগের চিকিৎসার বিকল্প নয়। ব্যথা, আঘাত বা বিশেষ শারীরিক সমস্যায় চিকিৎসকের পরামর্শ নিন।</p>
 </div>
</div>
</section>

<section class="section">
<div class="container">
 <h2>প্রোডাক্টটি আরও একবার দেখুন</h2>
 <div class="photo-grid">
  <?php foreach($images as $img): ?><img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($PRODUCT_NAME) ?>"><?php endforeach; ?>
 </div>
</div>
</section>

<section class="order-section" id="order">
<div class="container">
 <div class="order-box">
  <h2>অর্ডার করতে নিচের ফর্মটি পূরণ করুন</h2>
  <form id="orderForm">
   <div class="field"><label>আপনার নাম *</label><input id="name" required maxlength="100" placeholder="আপনার নাম"></div>
   <div class="field"><label>মোবাইল নম্বর *</label><input id="phone" required inputmode="tel" maxlength="20" placeholder="01XXXXXXXXX"></div>
   <div class="field"><label>সম্পূর্ণ ঠিকানা *</label><textarea id="address" required maxlength="500" rows="3" placeholder="বাড়ি/রোড, এলাকা, থানা, জেলা"></textarea></div>
   <div class="field">
    <label>ডেলিভারি পদ্ধতি *</label>
    <div class="delivery-options">
      <div class="delivery-option">
        <input type="radio" id="normalDelivery" name="delivery_method" value="normal_delivery" checked>
        <label for="normalDelivery"><i class="fa-solid fa-truck-fast"></i> নরমাল ডেলিভারি</label>
      </div>
      <div class="delivery-option">
        <input type="radio" id="fastDelivery" name="delivery_method" value="fast_delivery">
        <label for="fastDelivery"><i class="fa-solid fa-bolt"></i> দ্রুত ডেলিভারি</label>
      </div>
    </div>
   </div>
   <div class="qty-row">
     <div class="field" style="margin-bottom:0"><label>পরিমাণ</label><input id="qty" type="number" min="1" max="10" value="1"></div>
   </div>
   <div class="summary">
     <div><span>পণ্য</span><b id="selectedProductName"><?= htmlspecialchars($PRODUCT_NAME) ?></b></div>
     <div><span>পণ্যের মূল্য</span><b id="subtotal">৳<?= number_format($OFFER_PRICE) ?></b></div>
     <div><span>ডেলিভারি</span><b>সাইটের নিয়ম অনুযায়ী</b></div>
     <div class="total"><span>পণ্য মূল্য</span><span id="total">৳<?= number_format($OFFER_PRICE) ?></span></div>
   </div>
   <button class="cta" type="submit" id="submitBtn"><i class="fa-solid fa-circle-check"></i> অর্ডার কনফার্ম করুন</button>
   <div id="result"></div>
  </form>
 </div>
</div>
</section>

<section class="free-items" id="freeDeliveryItems">
<div class="container">
  <div class="free-heading">
    <span class="free-kicker">FREE DELIVERY</span>
    <h2><i class="fa-solid fa-bag-shopping"></i> ফ্রি ডেলিভারি আইটেম</h2>
    <p>এই পণ্যগুলো কিনলে ডেলিভারি চার্জ সম্পূর্ণ ফ্রি।</p>
  </div>

  <?php if (!empty($freeProducts)): ?>
  <div class="free-product-grid">
    <?php foreach ($freeProducts as $fp):
      $fpName = (string)($fp['name'] ?? 'Product');
      $fpPrice = (float)($fp['price'] ?? 0);
      $fpRegular = (float)($fp['regular_price'] ?? 0);
      $fpImage = landingImageUrl($fp['image_url'] ?? '');
      $fpBadge = trim((string)($fp['offer_badge_text'] ?? ''));
      $fpDiscount = ($fpRegular > $fpPrice && $fpRegular > 0) ? (int)round((($fpRegular-$fpPrice)/$fpRegular)*100) : null;
    ?>
      <article class="free-product-card">
        <div class="free-product-image">
          <?php if (!empty($fp['show_offer_badge']) && $fpBadge !== ''): ?>
            <span class="free-badge"><?= htmlspecialchars($fpBadge) ?></span>
          <?php elseif ($fpDiscount !== null): ?>
            <span class="free-badge">-<?= $fpDiscount ?>%</span>
          <?php else: ?>
            <span class="free-badge">FREE DELIVERY</span>
          <?php endif; ?>
          <img src="<?= htmlspecialchars($fpImage) ?>" alt="<?= htmlspecialchars($fpName) ?>" loading="lazy">
        </div>
        <div class="free-product-info">
          <small><?= htmlspecialchars((string)($fp['category_name'] ?? '')) ?></small>
          <h3><?= htmlspecialchars($fpName) ?></h3>
          <?php if (!empty($fp['show_star_rating']) && (float)($fp['star_rating'] ?? 0) > 0): ?>
            <div class="free-rating">★ <?= number_format((float)$fp['star_rating'], 1) ?></div>
          <?php endif; ?>
          <div class="free-price">
            <strong>৳<?= number_format($fpPrice, 0) ?></strong>
            <?php if ($fpRegular > $fpPrice): ?><del>৳<?= number_format($fpRegular, 0) ?></del><?php endif; ?>
          </div>
          <button class="free-buy" type="button" onclick='selectFreeProduct(<?= (int)$fp['id'] ?>, <?= json_encode($fpName, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, <?= json_encode($fpPrice) ?>)'>
            <i class="fa-solid fa-bag-shopping"></i> এই পণ্যটি অর্ডার করুন
          </button>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="placeholder">এই মুহূর্তে কোনো Active Free Delivery Item নেই।</div>
  <?php endif; ?>
</div>
</section>

<footer>© <?= date('Y') ?> FreeDeliveri.com — All Rights Reserved</footer>

<script>
const images = <?= json_encode($images, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
let price = <?= (float)$OFFER_PRICE ?>;
const apiUrl = <?= json_encode($API_URL) ?>;
let productId = <?= $PRODUCT_ID !== null ? (int)$PRODUCT_ID : 'null' ?>;
let productName = <?= json_encode($PRODUCT_NAME, JSON_UNESCAPED_UNICODE) ?>;

function selectFreeProduct(id,name,newPrice){
  productId = Number(id);
  productName = String(name);
  price = Number(newPrice);
  document.getElementById('selectedProductName').textContent = productName;
  updateTotal();
  document.getElementById('order').scrollIntoView({behavior:'smooth', block:'start'});
}

function showImage(i){
  document.getElementById('mainImage').src=images[i];
  document.querySelectorAll('.thumb').forEach((x,n)=>x.classList.toggle('active',n===i));
}
function money(n){return '৳'+Math.round(n).toLocaleString('en-US')}
function updateTotal(){
  let q=Math.max(1,Math.min(10,parseInt(document.getElementById('qty').value||1)));
  document.getElementById('qty').value=q;
  const total=price*q;
  document.getElementById('subtotal').textContent=money(total);
  document.getElementById('total').textContent=money(total);
}
document.getElementById('qty').addEventListener('input',updateTotal);

function closeSuccessPopup(){
  document.getElementById('successOverlay').classList.remove('show');
  document.body.style.overflow='';
}

document.getElementById('orderForm').addEventListener('submit',async function(e){
  e.preventDefault();
  const result=document.getElementById('result');
  const btn=document.getElementById('submitBtn');
  result.className=''; result.style.display='none'; result.textContent='';

  const name=document.getElementById('name').value.trim();
  const phone=document.getElementById('phone').value.trim();
  const address=document.getElementById('address').value.trim();
  const qty=Math.max(1,Math.min(10,parseInt(document.getElementById('qty').value||1)));
  const deliveryMethod=document.querySelector('input[name="delivery_method"]:checked')?.value || 'normal_delivery';

  if(!name || !phone || !address){
    result.className='error'; result.textContent='নাম, মোবাইল নম্বর ও সম্পূর্ণ ঠিকানা দিন।'; return;
  }

  if(!productId){
    result.className='error';
    result.textContent='এই Product-টি ওয়েবসাইটের Products তালিকায় পাওয়া যায়নি। Product-এর নামটি মিলিয়ে দেখুন।';
    return;
  }

  btn.disabled=true; btn.textContent='⏳ অর্ডার পাঠানো হচ্ছে...';

  const payload={
    action:'order',
    customer_name:name,
    customer_phone:phone,
    customer_address:address,
    delivery_method:deliveryMethod,
    payment_method:'cod',
    delivery_charge:0,
    discount:0,
    customer_note:'Landing Page থেকে অর্ডার',
    items:[{
      product_id:productId,
      name:productName,
      price:price,
      quantity:qty
    }]
  };

  try{
    const res=await fetch(apiUrl,{
      method:'POST',
      headers:{'Content-Type':'application/json','Accept':'application/json'},
      body:JSON.stringify(payload)
    });
    const raw=await res.text();
    let data;
    try{ data=JSON.parse(raw); }
    catch(err){ throw new Error('সার্ভার থেকে সঠিক JSON response পাওয়া যায়নি।'); }

    if(!res.ok || !data.success){
      throw new Error(data.message || 'অর্ডার করা যায়নি।');
    }

    result.className='success';
    result.textContent='অর্ডার সফল হয়েছে!';
    btn.textContent='অর্ডার সম্পন্ন';
    document.getElementById('successOrderId').textContent='Order ID: #'+(data.order_id || 'N/A');
    document.getElementById('successOverlay').classList.add('show');
    document.body.style.overflow='hidden';
    document.getElementById('orderForm').reset();
    document.getElementById('qty').value=1;
    updateTotal();
    window.scrollTo({top:0,behavior:'smooth'});
  }catch(err){
    result.className='error';
    result.textContent=err.message;
    btn.disabled=false; btn.textContent='✅ অর্ডার কনফার্ম করুন';
  }
});
</script>
</body>
</html>
