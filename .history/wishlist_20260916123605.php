<!doctype html>
<html lang="bn">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Wishlist | FREEDelivery.com</title>
	<link rel="stylesheet" href="style.css?v=11">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<main class="about-page">
	<a href="index.php" class="modal-btn" style="display:inline-block;width:auto">
		<i class="fa-solid fa-arrow-left"></i> হোমে ফিরুন
	</a>

	<section class="about-card">
		<h1><i class="fa-solid fa-heart" style="color:#ec4899"></i> আমার Wishlist</h1>
		<div id="wishlistPage"></div>
	</section>
</main>

<div class="modal-overlay" id="modalOverlay" onclick="closeModal()">
	<div class="modal" onclick="event.stopPropagation()">
		<button class="modal-close" type="button" onclick="closeModal()" aria-label="Close">
			<i class="fa-solid fa-xmark"></i>
		</button>
		<div id="modalContent"></div>
	</div>
</div>

<script src="script.js"></script>
<script>
	const box = document.getElementById("wishlistPage");
	let items = [];

	try {
		items = JSON.parse(localStorage.getItem("freedelivery_wishlist") || "[]");
		if (!Array.isArray(items)) items = [];
	} catch (error) {
		items = [];
	}

	const escapeHtml = (value) => String(value ?? "")
		.replace(/&/g, "&amp;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;")
		.replace(/"/g, "&quot;")
		.replace(/'/g, "&#039;");

	const productImage = (item) => item.image || "https://placehold.co/80x80?text=Product";

	function viewWishlistProduct(index) {
		const item = items[index];
		if (!item) return;

		const name = String(item.name || "Product");
		const image = productImage(item);
		const price = Number(item.price || 0);
		const category = String(item.category || "Product");

		openModal(`
			<div class="product-details wishlist-product-details">
				<img class="product-details-image" src="${escapeHtml(image)}" alt="${escapeHtml(name)}">
				<span class="product-details-category">${escapeHtml(category)}</span>
				<h2>${escapeHtml(name)}</h2>
				<div class="product-details-price"><strong>৳ ${price.toLocaleString("en-US")}</strong></div>
				<div class="wishlist-detail-actions">
					<button type="button" class="product-details-cart" onclick="addWishlistToCart(${index})">
						<i class="fa-solid fa-cart-plus"></i> কার্টে যোগ করুন
					</button>
					<button type="button" class="product-details-order" onclick="buyWishlistProduct(${index})">
						<i class="fa-solid fa-bag-shopping"></i> অর্ডার করুন
					</button>
				</div>
			</div>
		`);
	}

	function addWishlistToCart(index) {
		const item = items[index];
		if (!item) return;
		addToCart(Number(item.id), String(item.name || "Product"), Number(item.price || 0), productImage(item));
	}

	function buyWishlistProduct(index) {
		const item = items[index];
		if (!item) return;
		buyNow(Number(item.id), String(item.name || "Product"), Number(item.price || 0), productImage(item));
	}

	box.innerHTML = items.length
		? items.map((item, index) => `
			<article class="wishlist-row">
				<img src="${escapeHtml(productImage(item))}" alt="${escapeHtml(item.name || "Product")}">
				<div class="wishlist-product-copy">
					<strong>${escapeHtml(item.name || "Product")}</strong>
					<small>৳ ${Number(item.price || 0).toLocaleString("en-US")}</small>
				</div>
				<div class="wishlist-actions">
					<button type="button" class="wishlist-view" onclick="viewWishlistProduct(${index})" title="View product details" aria-label="View product details">
						<i class="fa-solid fa-eye"></i><span>View</span>
					</button>
					<button type="button" class="wishlist-cart" onclick="addWishlistToCart(${index})" title="Add to cart" aria-label="Add to cart">
						<i class="fa-solid fa-cart-plus"></i>
					</button>
					<button type="button" class="wishlist-order" onclick="buyWishlistProduct(${index})">
						<i class="fa-solid fa-bag-shopping"></i> Order Now
					</button>
				</div>
			</article>
		`).join("")
		: '<p class="empty-state">Wishlist এখনো খালি।</p>';
</script>
</body>
</html>
