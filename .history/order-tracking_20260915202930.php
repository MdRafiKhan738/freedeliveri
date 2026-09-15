<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Order Tracking | FREEDelivery.com</title>
<link rel="stylesheet" href="style.css?v=11">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.tracking-page{max-width:760px;margin:0 auto;padding:70px 18px}.tracking-card{background:#fff;border:1px solid #e5e7eb;border-radius:22px;padding:28px;box-shadow:0 14px 40px rgba(17,24,39,.08)}.tracking-form{display:flex;gap:10px;margin:22px 0}.tracking-form input{flex:1;min-width:0;border:1px solid #d1d5db;border-radius:10px;padding:13px;font:inherit}.tracking-result{min-height:70px}.tracking-order{border:1px solid #bbf7d0;background:#f0fdf4;border-radius:14px;padding:16px;margin-top:12px}.tracking-status{color:#15803d;font-weight:800;text-transform:capitalize}@media(max-width:580px){.tracking-page{padding:35px 14px}.tracking-form{flex-direction:column}.tracking-form button{width:100%}}
</style>
</head>
<body>
<main class="tracking-page">
<a href="index.php" class="modal-btn" style="display:inline-block;width:auto"><i class="fa-solid fa-arrow-left"></i> হোমে ফিরুন</a>
<section class="tracking-card"><h1><i class="fa-solid fa-location-dot"></i> Order Tracking</h1><p>আপনার অর্ডারের সময় ব্যবহৃত মোবাইল নম্বর দিন।</p><form class="tracking-form" id="trackingForm"><input id="trackingPhone" type="tel" inputmode="tel" placeholder="মোবাইল নম্বর" required><button class="modal-btn" type="submit" style="margin:0"><i class="fa-solid fa-magnifying-glass"></i> Track Order</button></form><div id="trackingResult" class="tracking-result"></div></section>
</main>
<script>
const escapeText=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
async function loadTracking(){const phone=document.getElementById('trackingPhone').value.trim();const result=document.getElementById('trackingResult');if(!phone)return;result.innerHTML='<p>অর্ডার খোঁজা হচ্ছে...</p>';try{const response=await fetch('order-api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'orders',customer_phone:phone})});const data=await response.json();if(!data.success||!data.orders?.length){result.innerHTML='<p>এই নম্বরে কোনো অর্ডার পাওয়া যায়নি।</p>';return}result.innerHTML=data.orders.map(order=>`<article class="tracking-order"><strong>Order #${Number(order.id)}</strong><span class="tracking-status" style="float:right">${escapeText(order.status)}</span><p>${escapeText(order.items||'')}</p><small>${escapeText(order.created_at||'')}</small><strong style="display:block;margin-top:8px">মোট: ৳ ${Number(order.total_amount||0).toLocaleString('en-US')}</strong></article>`).join('')}catch(error){result.innerHTML='<p>Tracking service বর্তমানে পাওয়া যাচ্ছে না।</p>'}}
document.getElementById('trackingForm').addEventListener('submit',event=>{event.preventDefault();loadTracking()});if(window.lucide)lucide.createIcons();
</script>
</body>
</html>
