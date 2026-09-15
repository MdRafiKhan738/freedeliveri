<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Order Tracking | FREEDelivery.com</title>
<link rel="stylesheet" href="style.css?v=11">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.tracking-page{max-width:820px;margin:0 auto;padding:70px 18px}.tracking-card{background:#fff;border:1px solid #e5e7eb;border-radius:22px;padding:clamp(22px,5vw,42px);box-shadow:0 14px 40px rgba(17,24,39,.08)}.tracking-card h1{font-size:clamp(28px,5vw,44px);margin:12px 0 4px}.tracking-form{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;margin:28px 0}.tracking-form input{display:block;width:100%;height:56px;border:2px solid #d1d5db;border-radius:12px;padding:0 16px;font:inherit;font-size:18px;outline:0}.tracking-form input:focus{border-color:#16a34a;box-shadow:0 0 0 4px #dcfce7}.tracking-form button{min-height:56px;padding:0 24px}.tracking-result{min-height:80px}.tracking-order{border:1px solid #bbf7d0;background:#f0fdf4;border-radius:14px;padding:16px;margin-top:12px}.tracking-status{color:#15803d;font-weight:800;text-transform:capitalize}@media(max-width:580px){.tracking-page{padding:28px 12px}.tracking-form{grid-template-columns:1fr}.tracking-form button{width:100%}}
</style>
</head>
<body>
<main class="tracking-page">
<a href="index.php" class="modal-btn" style="display:inline-block;width:auto"><i class="fa-solid fa-arrow-left"></i> হোমে ফিরুন</a>
<section class="tracking-card"><h1><i class="fa-solid fa-location-dot"></i> Order Tracking</h1><p>আপনার অর্ডারের সময় ব্যবহৃত মোবাইল নম্বর দিন।</p><form class="tracking-form" id="trackingForm"><input id="trackingPhone" type="tel" inputmode="tel" placeholder="মোবাইল নম্বর" required><button class="modal-btn" type="submit" style="margin:0"><i class="fa-solid fa-magnifying-glass"></i> Track Order</button></form><div id="trackingResult" class="tracking-result"></div></section>
</main>
<script>
const escapeText=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
async function loadTracking(){const phone=document.getElementById('trackingPhone').value.trim();const result=document.getElementById('trackingResult');if(!phone){Swal.fire({icon:'warning',text:'Order tracking-এর জন্য মোবাইল নম্বর দিন।',confirmButtonColor:'#16a34a'});return}result.innerHTML='<p>অর্ডার খোঁজা হচ্ছে...</p>';try{const response=await fetch('order-api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'orders',customer_phone:phone})});const data=await response.json();if(!data.success||!data.orders?.length){result.innerHTML='<p>এই নম্বরে কোনো অর্ডার পাওয়া যায়নি।</p>';Swal.fire({icon:'info',text:'এই মোবাইল নম্বরে কোনো অর্ডার পাওয়া যায়নি।',confirmButtonColor:'#16a34a'});return}result.innerHTML=data.orders.map(order=>`<article class="tracking-order"><strong>Order #${Number(order.id)}</strong><span class="tracking-status" style="float:right">${escapeText(order.status)}</span><p>${escapeText(order.items||'')}</p><small>${escapeText(order.created_at||'')}</small><strong style="display:block;margin-top:8px">মোট: ৳ ${Number(order.total_amount||0).toLocaleString('en-US')}</strong></article>`).join('');Swal.fire({icon:'success',title:'অর্ডার পাওয়া গেছে',text:`${data.orders.length}টি অর্ডারের status দেখানো হয়েছে।`,confirmButtonColor:'#16a34a'})}catch(error){result.innerHTML='<p>Tracking service বর্তমানে পাওয়া যাচ্ছে না।</p>';Swal.fire({icon:'error',text:'Tracking service-এ সংযোগ করা যায়নি।',confirmButtonColor:'#16a34a'})}}
document.getElementById('trackingForm').addEventListener('submit',event=>{event.preventDefault();loadTracking()});if(window.lucide)lucide.createIcons();
</script>
</body>
</html>
