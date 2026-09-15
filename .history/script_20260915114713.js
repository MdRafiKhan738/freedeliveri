/* ================= MENU ================= */

const sideMenu = document.getElementById("sideMenu");
const menuOverlay = document.getElementById("menuOverlay");

function openMenu(){
sideMenu.classList.add("show");
menuOverlay.classList.add("show");
document.body.style.overflow = "hidden";
}

function closeMenu(){
sideMenu.classList.remove("show");
menuOverlay.classList.remove("show");
document.body.style.overflow = "";
}

/* ================= MODAL ================= */

const modalOverlay =
document.getElementById("modalOverlay");

const modalContent =
document.getElementById("modalContent");

function openModal(content){

modalContent.innerHTML = content;  

modalOverlay.classList.add("show");  

document.body.style.overflow = "hidden";

}

function closeModal(){

modalOverlay.classList.remove("show");  

document.body.style.overflow = "";

}

/* ================= PROMO ================= */

function openPromo(){

    // Promo banner now starts from Sign In.
    openLogin(true);

}

function showPromoOffer(){

    openModal(`

        <div style="text-align:center;">

            <div style="font-size:52px;margin-bottom:10px;">✅</div>

            <h2 style="margin-bottom:12px;">সাইন ইন সফল হয়েছে</h2>

            <p style="line-height:1.9;margin:18px 0;font-size:16px;">
                সারা বছর <strong>ফ্রি ডেলিভারি কোড</strong> পেতে
                <br>
                কমপক্ষে <strong>৫০০ টাকার কেনাকাটা</strong> করুন।
            </p>

            <button
                type="button"
                class="modal-btn"
                style="background:#16a34a;"
                onclick="closeModal()">
                🛍️ কেনাকাটা করুন
            </button>

        </div>

    `);

}

/* ================= PROMO SIGNUP ================= */

function showPromoSignup(){

    openModal(`

        <h2>📝 Sign Up</h2>

        <input
            class="modal-input"
            id="promoName"
            type="text"
            placeholder="আপনার নাম">

        <input
            class="modal-input"
            id="promoPhone"
            type="tel"
            placeholder="মোবাইল নম্বর">

        <button class="modal-btn" onclick="completePromoSignup()">
            Sign Up
        </button>

    `);

}

function completePromoSignup(){

    const name = document.getElementById("promoName")?.value.trim();
    const phone = document.getElementById("promoPhone")?.value.trim();
    if(!name || !phone){
        alert("দয়া করে সব তথ্য পূরণ করুন।");
        return;
    }

    showPromoOffer();

}

/* ================= VISION ================= */

function openVision(){

openModal(`  

    <h2>🎯 আমাদের ভিশন ও মিশন</h2>  

    <br>  

    <p>  
        <strong>আমাদের ভিশন</strong><br>  
        অনলাইন কেনাকাটাকে সহজ,  
        নিরাপদ ও বিশ্বস্ত করে তোলা।  
    </p>  

    <br>  

    <p>  
        <strong>আমাদের মিশন</strong><br>  
        গ্রাহকদের কাছে ভালো মানের  
        পণ্য অফার মূল্যে পৌঁছে দেওয়া  
        এবং ডেলিভারি চার্জের ঝামেলা  
        কমিয়ে আনা।  
    </p>  

    <br>  

    <p>  
        Cash on Delivery এবং  
        customer support-এর মাধ্যমে  
        একটি ভালো shopping experience  
        তৈরি করাই আমাদের লক্ষ্য।  
    </p>  

`);

}
    
/* ================= ACCOUNT ================= */

async function openAccount(){

    const saved = (() => {
        try {
            return JSON.parse(localStorage.getItem("customer_session") || "null");
        } catch(e) {
            return null;
        }
    })();

    const phone = saved && saved.phone ? saved.phone : "";

    if(!phone){
        openLogin(false);
        return;
    }

    openModal(`
        <div style="text-align:center;">
            <div style="
                width:64px;height:64px;margin:0 auto 10px;
                border-radius:50%;background:#f0fdf4;
                display:flex;align-items:center;justify-content:center;
                font-size:30px;
            ">👤</div>

            <h2 style="margin:0;">আমার অ্যাকাউন্ট</h2>
            <p style="margin:6px 0 18px;color:#6b7280;font-size:14px;">
                আপনার কেনাকাটা ও Free Delivery সুবিধা
            </p>

            <div id="customerAccountContent">
                <div style="
                    padding:20px;
                    color:#6b7280;
                ">অ্যাকাউন্টের তথ্য লোড হচ্ছে...</div>
            </div>
        </div>
    `);

    try{

        const response = await fetch(
            "order-api.php?v=" + Date.now(),
            {
                method:"POST",
                headers:{"Content-Type":"application/json"},
                credentials:"same-origin",
                body:JSON.stringify({
                    action:"account",
                    customer_phone:phone
                })
            }
        );

        const data = await response.json();

        if(!data.success){
            document.getElementById("customerAccountContent").innerHTML = `
                <div style="padding:15px;color:#b91c1c;">
                    ${escapeAccountText(data.message || "অ্যাকাউন্ট পাওয়া যায়নি।")}
                </div>
            `;
            return;
        }

        try{
            localStorage.setItem(
                "customer_session",
                JSON.stringify(data.customer)
            );
        }catch(e){}

        showCustomerAccount(data.customer);

    }catch(error){

        const box=document.getElementById("customerAccountContent");

        if(box){
            box.innerHTML=`
                <div style="padding:15px;color:#b91c1c;">
                    সার্ভারের সাথে যোগাযোগ করা যায়নি।
                </div>
            `;
        }
    }
}


function showCustomerAccount(customer){

    const promos = Array.isArray(customer.promos)
        ? customer.promos
        : [];

    // একজন customer-এর জন্য শুধু প্রথম promo code দেখানো হবে
    const promo = promos.length ? promos[0] : null;

    const promoHtml = promo
        ? `
            <div style="
                margin-top:12px;
                padding:16px;
                border-radius:14px;
                border:1px solid #bbf7d0;
                background:linear-gradient(135deg,#f0fdf4,#ffffff);
                text-align:center;
            ">
                <div style="font-size:12px;color:#15803d;font-weight:700;">
                    আপনার Free Delivery Code
                </div>

                <div style="
                    margin:7px 0;
                    font-size:25px;
                    font-weight:900;
                    letter-spacing:4px;
                    color:#166534;
                ">
                    ${escapeAccountText(promo.code)}
                </div>

                <div style="font-size:13px;color:#374151;line-height:1.6;">
                    এই কোড দিয়ে পরবর্তী সকল অর্ডারে
                    <strong>ডেলিভারি চার্জ সম্পূর্ণ ফ্রি</strong>।
                </div>

                <div style="margin-top:7px;font-size:12px;color:#6b7280;">
                    মেয়াদ: ${escapeAccountText(promo.expires_at || "নির্ধারিত নয়")}
                </div>

                <div style="display:flex;gap:8px;margin-top:13px;">
                    <button type="button" class="modal-btn"
                        style="margin:0;flex:1;padding:10px 6px;"
                        onclick="copyPromoCode('${String(promo.code).replace(/'/g,"\\'")}')">
                        📋 কপি
                    </button>

                    <button type="button" class="modal-btn"
                        style="margin:0;flex:1;padding:10px 6px;"
                        onclick="savePromoCode('${String(promo.code).replace(/'/g,"\\'")}')">
                        💾 সেভ
                    </button>
                </div>
            </div>
        `
        : `
            <div style="
                margin-top:12px;
                padding:18px 12px;
                border-radius:14px;
                background:#f8fafc;
                border:1px solid #e5e7eb;
                color:#6b7280;
                font-size:14px;
            ">
                🎁 এখনো কোনো Free Delivery Code নেই।
                <br>
                <strong style="color:#374151;">
                    ৳500 বা তার বেশি অর্ডার করলে ১ বছরের Free Delivery Code পাবেন।
                </strong>
            </div>
        `;

    const box=document.getElementById("customerAccountContent");

    if(!box) return;

    box.innerHTML=`
        <div style="
            text-align:left;
            padding:15px;
            border-radius:14px;
            background:#f8fafc;
            border:1px solid #e5e7eb;
        ">
            <div style="font-size:12px;color:#6b7280;">কাস্টমার</div>
            <div style="
                margin-top:3px;
                font-size:18px;
                font-weight:800;
                color:#111827;
            ">
                ${escapeAccountText(customer.name || "কাস্টমার")}
            </div>

            <div style="
                margin-top:8px;
                font-size:14px;
                color:#4b5563;
            ">
                📱 ${escapeAccountText(customer.phone || "")}
            </div>

            ${
                customer.address
                ? `<div style="
                    margin-top:6px;
                    font-size:13px;
                    color:#6b7280;
                ">📍 ${escapeAccountText(customer.address)}</div>`
                : ""
            }
        </div>

        <div style="margin-top:17px;text-align:left;">
            <h3 style="
                margin:0;
                font-size:16px;
            ">
                🎁 আমার Free Delivery
            </h3>

            ${promoHtml}
        </div>

        <button
            type="button"
            class="modal-btn"
            style="
                margin-top:18px;
                background:#16a34a;
            "
            onclick="closeModal()">
            🛍️ কেনাকাটা করুন
        </button>

        <button type="button" class="modal-btn" style="margin-top:8px;background:#111827;" onclick="loadCustomerOrders('${String(customer.phone || '').replace(/'/g, "\\'")}')">
            <i class="fa-solid fa-receipt"></i> আপনার অর্ডার দেখুন
        </button>

        <button type="button" class="account-logout-button" onclick="logoutCustomer()">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </button>
    `;
}

async function loadCustomerOrders(phone){
    const box = document.getElementById("customerAccountContent");
    if(!box) return;
    box.innerHTML = '<div style="padding:20px;text-align:center;color:#6b7280;">অর্ডার লোড হচ্ছে...</div>';
    try{
        const response = await fetch("order-api.php?v=" + Date.now(), {
            method:"POST",
            headers:{"Content-Type":"application/json"},
            body:JSON.stringify({action:"orders", customer_phone:phone})
        });
        const data = await response.json();
        if(!data.success || !Array.isArray(data.orders) || !data.orders.length){
            box.innerHTML = '<div style="padding:20px;text-align:center;color:#6b7280;">এখনো কোনো অর্ডার পাওয়া যায়নি।</div>';
            return;
        }
        box.innerHTML = '<h3 style="margin:0 0 12px;text-align:left;">আপনার অর্ডার</h3>' + data.orders.map(order => `
            <article style="text-align:left;border:1px solid #e5e7eb;border-radius:10px;padding:12px;margin-bottom:10px;background:#fff;">
                <strong>Order #${Number(order.id)}</strong>
                <span style="float:right;color:#15803d;font-weight:700;">${escapeAccountText(order.status)}</span>
                <div style="font-size:13px;color:#6b7280;margin-top:6px;">${escapeAccountText(order.created_at || '')}</div>
                <div style="margin-top:7px;">${escapeAccountText(order.items || '')}</div>
                <strong style="display:block;margin-top:7px;">মোট: ৳ ${Number(order.total_amount || 0).toLocaleString("en-US")}</strong>
            </article>
        `).join('');
    }catch(error){
        box.innerHTML = '<div style="padding:20px;text-align:center;color:#b91c1c;">অর্ডার লোড করা যায়নি।</div>';
    }
}


function escapeAccountText(value){
    return String(value ?? "")
        .replace(/&/g,"&amp;")
        .replace(/</g,"&lt;")
        .replace(/>/g,"&gt;")
        .replace(/"/g,"&quot;")
        .replace(/'/g,"&#039;")
        .replace(/\n/g,"<br>");
}

/* ================= LOGIN ================= */

function openLogin(fromPromo = false) {
    openModal(`
        <form id="loginForm">
            <h2>Login / Sign In</h2>

            <input class="modal-input"
                   id="loginPhone"
                   type="tel"
                   inputmode="numeric"
                   autocomplete="tel"
                   placeholder="মোবাইল নম্বর"
                   required>

            <button type="submit"
                    id="loginSubmitBtn"
                    class="modal-btn">
                Login / Sign In
            </button>

            <p style="margin-top:15px;text-align:center;">
                নতুন user?
                <a href="#"
                   onclick="openSignup();return false;"
                   style="color:#16a34a;font-weight:bold;">
                    Sign Up
                </a>
            </p>
        </form>
    `);

    const form = document.getElementById("loginForm");

    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            submitLogin(fromPromo);
        });
    }
}

async function submitLogin(fromPromo = false) {
    const loginInput = document.getElementById("loginPhone");
    const button = document.getElementById("loginSubmitBtn");
    const phone = loginInput ? loginInput.value.trim() : "";

    if (!phone) {
        alert("মোবাইল নম্বর লিখুন।");
        return;
    }

    if (button) {
        button.disabled = true;
        button.innerText = "Login হচ্ছে...";
    }

    try {
        const response = await fetch("order-api.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            credentials: "same-origin",
            body: JSON.stringify({
                action: "login",
                customer_phone: phone
            })
        });

        const responseText = await response.text();

        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            console.error("Login API response:", responseText);
            throw new Error("সার্ভার থেকে সঠিক response পাওয়া যায়নি।");
        }

        if (!data.success) {
            alert(data.message || "লগইন করা যায়নি।");
            return;
        }

        if (data.customer) {
            localStorage.setItem(
                "customer_session",
                JSON.stringify({
                    name: data.customer.name || "",
                    phone: data.customer.phone || phone
                })
            );
        }

        if (fromPromo) {
            // Promo button থেকে Sign In সফল হলে সরাসরি Promo success message দেখাবে।
            showPromoOffer();
        } else {
            showCustomerAccount(data.customer);
        }

    } catch (error) {
        console.error("Login Error:", error);
        alert(error.message || "লগইন সার্ভারের সাথে যোগাযোগ করা যায়নি।");
    } finally {
        if (button) {
            button.disabled = false;
            button.innerText = "Login / Sign In";
        }
    }
}

/* ================= LOGOUT ================= */

async function logoutCustomer() {
    try {
        await fetch("order-api.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            credentials: "same-origin",
            body: JSON.stringify({
                action: "logout"
            })
        });
    } catch (error) {
        console.error("Logout Error:", error);
    }

    try {
        localStorage.removeItem("customer_session");
    } catch (e) {}

    closeModal();
}

/* ================= SIGN UP ================= */

function openSignup() {
    openModal(`
        <form id="signupForm">
            <h2>Sign Up</h2>

            <input class="modal-input"
                   id="signupName"
                   type="text"
                   autocomplete="name"
                   placeholder="আপনার নাম"
                   required>

            <input class="modal-input"
                   id="signupPhone"
                   type="tel"
                   inputmode="numeric"
                   autocomplete="tel"
                   placeholder="মোবাইল নম্বর"
                   required>

            <button type="submit"
                    id="signupSubmitBtn"
                    class="modal-btn">
                Sign Up
            </button>

            <p style="margin-top:15px;text-align:center;">
                আগে থেকেই account আছে?
                <a href="#"
                   onclick="openLogin();return false;"
                   style="color:#16a34a;font-weight:bold;">
                    Login
                </a>
            </p>
        </form>
    `);

    const form = document.getElementById("signupForm");

    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            submitSignup();
        });
    }
}

async function submitSignup() {
    const nameInput = document.getElementById("signupName");
    const phoneInput = document.getElementById("signupPhone");
    const button = document.getElementById("signupSubmitBtn");

    const name = nameInput ? nameInput.value.trim() : "";
    const phone = phoneInput ? phoneInput.value.trim() : "";

    if (!name) {
        alert("আপনার নাম লিখুন।");
        return;
    }

    if (!phone) {
        alert("মোবাইল নম্বর লিখুন।");
        return;
    }

    if (button) {
        button.disabled = true;
        button.innerText = "Account তৈরি হচ্ছে...";
    }

    try {
        const response = await fetch("order-api.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            credentials: "same-origin",
            body: JSON.stringify({
                action: "signup",
                customer_name: name,
                customer_phone: phone
            })
        });

        const responseText = await response.text();

        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            console.error("Signup API response:", responseText);
            throw new Error("সার্ভার থেকে সঠিক response পাওয়া যায়নি।");
        }

        if (!data.success) {
            alert(data.message || "Account তৈরি করা যায়নি।");
            return;
        }

        if (data.customer) {
            localStorage.setItem(
                "customer_session",
                JSON.stringify({
                    name: data.customer.name || name,
                    phone: data.customer.phone || phone
                })
            );
        }

        alert(data.message || "Account সফলভাবে তৈরি হয়েছে।");
        showCustomerAccount(data.customer);

    } catch (error) {
        console.error("Signup Error:", error);
        alert(
            error.message ||
            "Account তৈরি করার সময় সার্ভারের সাথে যোগাযোগ করা যায়নি।"
        );
    } finally {
        if (button) {
            button.disabled = false;
            button.innerText = "Sign Up";
        }
    }
}

/* ================= CART + SELECT + CHECKOUT ================= */

let cart = loadCart();

let checkoutItems = [];
let checkoutDelivery = 80;
let checkoutPromoApplied = false;

function loadCart(){
    try{
        const saved = JSON.parse(localStorage.getItem("freedelivery_cart") || "[]");
        return Array.isArray(saved) ? saved : [];
    }catch(e){
        return [];
    }
}

function saveCart(){
    try{
        localStorage.setItem("freedelivery_cart", JSON.stringify(cart));
    }catch(e){}
}


/* ================= ADD TO CART ================= */

window.addToCart = function addToCart(productId, name, price, image){
    const existing = cart.find(item => Number(item.product_id) === Number(productId) && item.name === name);
    if(existing){
        existing.quantity += 1;
    }else{
        cart.push({product_id: Number(productId) || null, name, price: Number(price), image: image || "", quantity: 1});
    }

    saveCart();
    updateCartCount();

    alert("পণ্যটি Cart-এ যোগ হয়েছে!");
}


/* ================= CART COUNT ================= */

function updateCartCount(){

    const cartCount =
        document.getElementById("cartCount");

    if(cartCount){
        cartCount.innerText = cart.reduce((total, item) => total + Number(item.quantity || 0), 0);
    }
}

updateCartCount();


/* ================= OPEN CART ================= */

function openCart(){

    if(cart.length === 0){

        openModal(`

            <h2>🛒 Your Cart</h2>

            <p style="text-align:center;margin:20px 0;">
                আপনার Cart বর্তমানে খালি।
            </p>

        `);

        return;
    }


    let html = `

        <h2>🛒 Your Cart</h2>

        <div style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin:15px 0;
            padding-bottom:10px;
            border-bottom:1px solid #eee;
        ">

            <strong>পণ্য নির্বাচন করুন</strong>

            <button
                onclick="selectAllCart()"
                style="
                    background:none;
                    color:#16a34a;
                    font-weight:bold;
                    border:none;
                    cursor:pointer;
                ">
                সব নির্বাচন
            </button>

        </div>

    `;


    cart.forEach((item,index)=>{

        html += `

            <div style="
                padding:13px 0;
                border-bottom:1px solid #eee;
                display:flex;
                align-items:center;
                gap:10px;
            ">

                <input
                    type="checkbox"
                    id="cartCheck${index}"
                    onchange="updateCartSelection()"
                    style="
                        width:18px;
                        height:18px;
                    ">


                <div style="
                    flex:1;
                ">

                    <strong>
                        ${item.name}
                    </strong>

                    <br>

                    <small>
                        ৳ ${item.price}
                    </small>

                    <img src="${escapeAccountText(item.image || "https://placehold.co/80x80?text=Product")}" alt="" style="width:58px;height:58px;object-fit:cover;border-radius:8px;background:#f3f4f6;">

                    <div style="
                        display:flex;
                        align-items:center;
                        gap:8px;
                        margin-top:8px;
                    ">

                        <button
                            onclick="changeCartQuantity(${index},-1)"
                            style="
                                width:30px;
                                height:30px;
                                border:1px solid #ddd;
                                border-radius:5px;
                                background:#fff;
                            ">
                            −
                        </button>

                        <strong>
                            ${item.quantity}
                        </strong>

                        <button
                            onclick="changeCartQuantity(${index},1)"
                            style="
                                width:30px;
                                height:30px;
                                border:1px solid #ddd;
                                border-radius:5px;
                                background:#fff;
                            ">
                            +
                        </button>

                    </div>

                </div>


                <button
                    onclick="removeFromCart(${index})"
                    style="
                        color:#ef4444;
                        background:none;
                        border:none;
                        font-size:18px;
                    ">

                    <i class="fa-solid fa-trash"></i>

                </button>

            </div>

        `;
    });


    html += `

        <div
            id="cartSelectedTotal"
            style="
                margin-top:18px;
                font-weight:bold;
                font-size:17px;
            ">
            নির্বাচিত পণ্য: 0টি
        </div>


        <button
            class="modal-btn"
            onclick="checkoutSelectedItems()"
            style="margin-top:15px;">

            Checkout

        </button>

    `;


    openModal(html);
}


/* ================= SELECT ALL ================= */

function selectAllCart(){

    cart.forEach((item,index)=>{

        const checkbox =
            document.getElementById("cartCheck" + index);

        if(checkbox){
            checkbox.checked = true;
        }

    });

    updateCartSelection();
}


/* ================= CART SELECTION ================= */

function updateCartSelection(){

    let selectedCount = 0;
    let selectedTotal = 0;


    cart.forEach((item,index)=>{

        const checkbox =
            document.getElementById("cartCheck" + index);

        if(checkbox && checkbox.checked){

            selectedCount += item.quantity;

            selectedTotal +=
                item.price * item.quantity;
        }

    });


    const totalBox =
        document.getElementById("cartSelectedTotal");

    if(totalBox){

        totalBox.innerText =
            "নির্বাচিত পণ্য: " +
            selectedCount +
            "টি — মোট ৳ " +
            selectedTotal;

    }
}


/* ================= CART QUANTITY ================= */

function changeCartQuantity(index,change){

    if(!cart[index]){
        return;
    }


    cart[index].quantity += change;


    if(cart[index].quantity < 1){

        cart[index].quantity = 1;

    }


    saveCart();
    openCart();

    updateCartCount();
}


/* ================= REMOVE CART ITEM ================= */

function removeFromCart(index){

    cart.splice(index,1);

    saveCart();
    updateCartCount();

    openCart();
}


/* ================= CHECKOUT SELECTED ================= */

function checkoutSelectedItems(){

    checkoutItems = [];


    cart.forEach((item,index)=>{

        const checkbox =
            document.getElementById("cartCheck" + index);


        if(checkbox && checkbox.checked){

            checkoutItems.push({

                product_id: item.product_id || null,
                name: item.name,
                image: item.image || "",

                price: Number(item.price),

                quantity: item.quantity

            });

        }

    });


    if(checkoutItems.length === 0){

        alert("কমপক্ষে একটি পণ্য নির্বাচন করুন।");

        return;
    }


    checkoutDelivery = 80;

    checkoutPromoApplied = false;

    showCartCheckout();
}


/* ================= BUY NOW ================= */

window.buyNow = function buyNow(productId, name, price, image){

    checkoutItems = [

        {
            product_id: Number(productId) || null,
            name: name,
            image: image || "",
            price: Number(price),
            quantity: 1
        }

    ];


    checkoutDelivery = 80;

    checkoutPromoApplied = false;

    showCartCheckout();
}


/* ================= CHECKOUT ================= */

function showCartCheckout(){

    let productTotal = 0;


    checkoutItems.forEach(item=>{

        productTotal +=
            item.price * item.quantity;

    });


    const deliveryTotal =
        checkoutPromoApplied
        ? 0
        : checkoutDelivery;


    const grandTotal =
        productTotal + deliveryTotal;


    let productHTML = "";


    checkoutItems.forEach(item=>{

        productHTML += `

            <div style="
                display:flex;
                align-items:center;
                justify-content:space-between;
                padding:10px 0;
                border-bottom:1px solid #eee;
            ">

                <img src="${escapeAccountText(item.image || "https://placehold.co/80x80?text=Product")}" alt="" style="width:58px;height:58px;object-fit:cover;border-radius:8px;background:#f3f4f6;flex:0 0 auto;">

                <span>
                    ${item.name}
                    × ${item.quantity}
                </span>

                <strong>
                    ৳ ${item.price * item.quantity}
                </strong>

            </div>

        `;

    });


    openModal(`

        <div class="professional-checkout">

            <h2 class="checkout-heading">
                🛍️ অর্ডার করুন
            </h2>


            <!-- SELECTED PRODUCTS -->

            <div style="
                margin-bottom:18px;
            ">

                <div class="checkout-label">
                    নির্বাচিত পণ্য
                </div>

                ${productHTML}

            </div>


            <!-- NAME -->

            <div class="checkout-label">
                নাম
            </div>

            <input
                class="modal-input checkout-input"
                id="checkoutName"
                type="text"
                placeholder="আপনার নাম">


            <!-- PHONE -->

            <div class="checkout-label">
                মোবাইল নম্বর
            </div>

            <input
                class="modal-input checkout-input"
                id="checkoutPhone"
                type="tel"
                placeholder="মোবাইল নম্বর"
                oninput="resetCheckoutPromo()">


            <!-- ADDRESS -->

            <div class="checkout-label">
                সম্পূর্ণ ঠিকানা
            </div>

            <textarea
                class="modal-input checkout-input"
                id="checkoutAddress"
                rows="3"
                placeholder="সম্পূর্ণ ঠিকানা"></textarea>


            <!-- DELIVERY INFO -->

            <div style="
                margin:15px 0;
                padding:12px;
                background:#f8fafc;
                border-radius:8px;
                line-height:1.8;
            ">

                <strong>
                    🚚 ডেলিভারি চার্জ
                </strong>

                <br>

                ঢাকার ভিতর : ৳ ৮০

                <br>

                ঢাকার আশপাশে : ৳ ১০০

                <br>

                ঢাকার বাইরে : ৳ ১৩০

            </div>


            <!-- DELIVERY SELECT -->

            <div class="checkout-label">
                ডেলিভারি এলাকা নির্বাচন করুন
            </div>

            <select
                id="deliverySelect"
                class="checkout-select"
                onchange="selectCheckoutDelivery(this.value)">

                <option value="80">
                    ঢাকার ভিতর — ৳ ৮০
                </option>

                <option value="100">
                    ঢাকার আশপাশে — ৳ ১০০
                </option>

                <option value="130">
                    ঢাকার বাইরে — ৳ ১৩০
                </option>

            </select>


            <!-- PROMO -->

            <div class="checkout-label">
                প্রোমো কোড <span>(যদি থাকে)</span>
            </div>

            <input
                class="modal-input checkout-input"
                id="checkoutPromo"
                type="text"
                maxlength="8"
                autocomplete="off"
                placeholder="FDXXXXXX বা 8 অক্ষর লিখুন">

            <button
                type="button"
                class="modal-btn"
                id="applyPromoButton"
                onclick="applyCheckoutPromo()"
                style="margin-top:8px;">
                প্রোমো কোড প্রয়োগ করুন
            </button>

            <div
                id="promoStatus"
                style="margin-top:8px;text-align:center;font-weight:600;">
            </div>


            <!-- QUANTITY INFO -->

            <div class="checkout-label">
                মোট কোয়ান্টিটি
            </div>

            <div style="
                padding:10px;
                background:#f8fafc;
                border-radius:7px;
                margin-bottom:15px;
            ">

                ${getCheckoutQuantity()} টি পণ্য

            </div>


            <!-- BILL -->

            <div class="checkout-bill">

                <div>

                    <span>
                        পণ্যের মূল্য
                    </span>

                    <strong id="checkoutProductTotal">
                        ৳ ${productTotal}
                    </strong>

                </div>


                <div>

                    <span>
                        ডেলিভারি চার্জ
                    </span>

                    <strong id="checkoutDeliveryTotal">
                        ৳ ${deliveryTotal}
                    </strong>

                </div>


                <div class="checkout-grand-total">

                    <span>
                        সর্বমোট
                    </span>

                    <strong id="checkoutGrandTotal">
                        ৳ ${grandTotal}
                    </strong>

                </div>

            </div>


            <!-- PAYMENT -->

            <div class="checkout-payment">

                <strong>
                    💳 পেমেন্ট
                </strong>

                <label>
                    <input
                        type="radio"
                        checked
                        disabled>

                    Cash on Delivery

                </label>

            </div>


            <!-- ORDER -->

            <button
                class="checkout-order-button"
                onclick="confirmCartOrder()">

                অর্ডার করুন

            </button>

        </div>

    `);
}


/* ================= PROMO COPY / SAVE ================= */

function copyPromoCode(code){

    code = String(code || "").trim().toUpperCase();

    if(!code) return;

    const done = () => {
        alert("Promo Code কপি হয়েছে: " + code);
    };

    if(navigator.clipboard && window.isSecureContext){

        navigator.clipboard
            .writeText(code)
            .then(done)
            .catch(() => fallbackCopyPromoCode(code));

    }else{

        fallbackCopyPromoCode(code);
    }
}


function fallbackCopyPromoCode(code){

    const textarea = document.createElement("textarea");

    textarea.value = code;
    textarea.style.position = "fixed";
    textarea.style.opacity = "0";

    document.body.appendChild(textarea);

    textarea.focus();
    textarea.select();

    try{
        document.execCommand("copy");
        alert("Promo Code কপি হয়েছে: " + code);
    }catch(e){
        alert("কপি করা যায়নি। Code: " + code);
    }

    document.body.removeChild(textarea);
}


function savePromoCode(code){

    code = String(code || "").trim().toUpperCase();

    if(!code) return;

    try{

        localStorage.setItem(
            "freedelivery_saved_promo",
            code
        );

        alert(
            "Promo Code সেভ হয়েছে: " + code
        );

    }catch(e){

        alert(
            "Code সেভ করা যায়নি। Code: " + code
        );
    }
}


function loadSavedPromoCode(){

    try{
        return (
            localStorage.getItem(
                "freedelivery_saved_promo"
            ) || ""
        ).trim().toUpperCase();
    }catch(e){
        return "";
    }
}


/* ================= RESET PROMO ================= */

function resetCheckoutPromo(){

    if(!checkoutPromoApplied){
        return;
    }

    checkoutPromoApplied = false;

    const promoInput =
        document.getElementById("checkoutPromo");

    const button =
        document.getElementById("applyPromoButton");

    const statusBox =
        document.getElementById("promoStatus");

    if(promoInput){
        promoInput.readOnly = false;
    }

    if(button){
        button.disabled = false;
        button.innerText = "প্রোমো কোড প্রয়োগ করুন";
    }

    if(statusBox){
        statusBox.innerText = "";
    }

    updateCartCheckoutTotal();
}


/* ================= APPLY PROMO ================= */

async function applyCheckoutPromo(){

    const phoneInput =
        document.getElementById("checkoutPhone");

    const promoInput =
        document.getElementById("checkoutPromo");

    const button =
        document.getElementById("applyPromoButton");

    const statusBox =
        document.getElementById("promoStatus");

    const phone =
        phoneInput?.value.trim() || "";

    const promoCode =
        promoInput?.value.trim().toUpperCase() || "";

    let productTotal = 0;

    checkoutItems.forEach(item=>{
        productTotal +=
            Number(item.price) * Number(item.quantity);
    });

    if(!phone){
        alert("প্রোমো কোড প্রয়োগ করার আগে মোবাইল নম্বর লিখুন।");
        phoneInput?.focus();
        return;
    }

    if(!promoCode){
        alert("প্রোমো কোড লিখুন।");
        promoInput?.focus();
        return;
    }

    if(promoCode.length !== 8){
        alert("প্রোমো কোড ৮ অক্ষরের হতে হবে। উদাহরণ: FD260915");
        promoInput?.focus();
        return;
    }

    if(button){
        button.disabled = true;
        button.innerText = "যাচাই করা হচ্ছে...";
    }

    if(statusBox){
        statusBox.innerText = "";
    }

    try{

        const response = await fetch(
            "order-api.php?v=" + Date.now(),
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    action: "validate_promo",
                    customer_phone: phone,
                    promo_code: promoCode,
                    subtotal: productTotal
                })
            }
        );

        const data = await response.json();

        if(!response.ok || !data.success){
            throw new Error(
                data.message || "প্রোমো কোডটি বৈধ নয়।"
            );
        }

        checkoutPromoApplied = true;
        promoInput.value = data.promo_code || promoCode;
        promoInput.readOnly = true;

        try{
            localStorage.setItem(
                "freedelivery_saved_promo",
                promoInput.value.trim().toUpperCase()
            );
        }catch(e){}

        if(statusBox){
            statusBox.innerText = "✓ প্রোমো কোড সফলভাবে প্রয়োগ হয়েছে — ডেলিভারি FREE";
        }

        if(button){
            button.innerText = "প্রয়োগ হয়েছে ✓";
        }

        updateCartCheckoutTotal();

    }catch(error){

        checkoutPromoApplied = false;
        updateCartCheckoutTotal();

        if(statusBox){
            statusBox.innerText = error.message || "প্রোমো কোডটি বৈধ নয়।";
        }

        alert(error.message || "প্রোমো কোডটি বৈধ নয়।");

        if(button){
            button.disabled = false;
            button.innerText = "প্রোমো কোড প্রয়োগ করুন";
        }
    }
}


/* ================= DELIVERY ================= */

function selectCheckoutDelivery(value){

    checkoutDelivery =
        Number(value);

    updateCartCheckoutTotal();
}


/* ================= QUANTITY ================= */

function getCheckoutQuantity(){

    let total = 0;


    checkoutItems.forEach(item=>{

        total += item.quantity;

    });


    return total;
}


/* ================= TOTAL ================= */

function updateCartCheckoutTotal(){

    let productTotal = 0;


    checkoutItems.forEach(item=>{

        productTotal +=
            item.price * item.quantity;

    });


    const deliveryTotal =
        checkoutPromoApplied
        ? 0
        : checkoutDelivery;


    const grandTotal =
        productTotal + deliveryTotal;


    const productBox =
        document.getElementById(
            "checkoutProductTotal"
        );


    const deliveryBox =
        document.getElementById(
            "checkoutDeliveryTotal"
        );


    const grandBox =
        document.getElementById(
            "checkoutGrandTotal"
        );


    if(productBox){

        productBox.innerText =
            "৳ " + productTotal;

    }


    if(deliveryBox){

        deliveryBox.innerText =
            deliveryTotal === 0
            ? "FREE"
            : "৳ " + deliveryTotal;

    }


    if(grandBox){

        grandBox.innerText =
            "৳ " + grandTotal;

    }

}


/* ================= CONFIRM ORDER ================= */

async function confirmCartOrder(){

    const name =
        document
        .getElementById("checkoutName")
        ?.value
        .trim();

    const phone =
        document
        .getElementById("checkoutPhone")
        ?.value
        .trim();

    const address =
        document
        .getElementById("checkoutAddress")
        ?.value
        .trim();

    if(!name){
        alert("আপনার নাম লিখুন।");
        return;
    }

    if(!phone){
        alert("মোবাইল নম্বর লিখুন।");
        return;
    }

    if(!address){
        alert("সম্পূর্ণ ঠিকানা লিখুন।");
        return;
    }

    if(!Array.isArray(checkoutItems) || checkoutItems.length === 0){
        alert("কোনো পণ্য নির্বাচন করা হয়নি।");
        return;
    }

    let productTotal = 0;

    checkoutItems.forEach(item=>{
        productTotal += Number(item.price) * Number(item.quantity);
    });

    const deliveryTotal =
        checkoutPromoApplied
        ? 0
        : Number(checkoutDelivery);

    const promoInput =
        document.getElementById("checkoutPromo");

    const promoCode =
        promoInput?.value.trim() || "";

    const orderButton =
        document.querySelector(".checkout-order-button");

    if(orderButton){
        orderButton.disabled = true;
        orderButton.innerText = "অর্ডার সংরক্ষণ হচ্ছে...";
    }

    try{

        const response = await fetch(
            "order-api.php?v=" + Date.now(),
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    customer_name: name,
                    customer_phone: phone,
                    customer_address: address,
                    delivery_method: "home_delivery",
                    payment_method: "cod",
                    delivery_charge: deliveryTotal,
                    discount: 0,
                    promo_code: promoCode,
                    items: checkoutItems.map(item => ({
                        product_id: item.product_id || null,
                        name: item.name,
                        price: Number(item.price),
                        quantity: Number(item.quantity)
                    }))
                })
            }
        );

        const data = await response.json();

        if(!response.ok || !data.success){
            throw new Error(
                data.message || "অর্ডার সংরক্ষণ করা যায়নি।"
            );
        }

        try{
            localStorage.setItem(
                "customer_session",
                JSON.stringify({
                    name: name,
                    phone: phone
                })
            );
            if(data.promo_code){
                localStorage.setItem("freedelivery_saved_promo", String(data.promo_code).toUpperCase());
            }
        }catch(e){}

        openModal(`

            <div style="
                text-align:center;
            ">

                <div style="
                    font-size:45px;
                ">
                    ✅
                </div>

                <h2>
                    অর্ডার সফল হয়েছে
                </h2>

                <p style="
                    margin:15px 0;
                    line-height:1.7;
                ">
                    আপনার অর্ডারটি গ্রহণ করা হয়েছে।
                </p>

                ${data.promo_code ? `
                    <div style="
                        margin:15px 0;
                        padding:14px;
                        background:#f0fdf4;
                        border:2px dashed #16a34a;
                        border-radius:10px;
                        text-align:center;
                    ">
                        <div style="
                            font-weight:700;
                            font-size:17px;
                            line-height:1.6;
                        ">
                            🎁 আপনি ১ বছরের Free Delivery Promo Code পেয়েছেন
                        </div>

                        <div style="
                            margin-top:8px;
                            font-size:24px;
                            font-weight:800;
                            letter-spacing:3px;
                            color:#15803d;
                        ">
                            ${data.promo_code}
                        </div>

                        <div style="
                            margin-top:8px;
                            font-size:14px;
                            line-height:1.7;
                        ">
                            এই Promo Code ব্যবহার করে
                            <strong>পরবর্তী সকল অর্ডারে ডেলিভারি চার্জ সম্পূর্ণ ফ্রি</strong>
                            পাবেন।
                        </div>

                        <div style="
                            display:flex;
                            gap:8px;
                            justify-content:center;
                            margin-top:14px;
                            flex-wrap:wrap;
                        ">
                            <button
                                type="button"
                                class="modal-btn"
                                style="width:auto;padding:10px 16px;"
                                onclick="copyPromoCode('${data.promo_code}')">
                                📋 কোড কপি করুন
                            </button>

                            <button
                                type="button"
                                class="modal-btn"
                                style="width:auto;padding:10px 16px;"
                                onclick="savePromoCode('${data.promo_code}')">
                                💾 কোড সেভ করুন
                            </button>
                        </div>
                    </div>
                ` : ""}

                <p style="margin-top:10px;">
                    সর্বমোট:
                    <strong>৳ ${data.total_amount}</strong>
                </p>

                <p style="margin-top:10px;">
                    পেমেন্ট:
                    <strong>Cash on Delivery</strong>
                </p>

                <button
                    class="modal-btn"
                    onclick="closeModal()">
                    ঠিক আছে
                </button>

            </div>

        `);

    }catch(error){

        console.error("Order Error:", error);

        alert(
            error.message ||
            "অর্ডার সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।"
        );

        if(orderButton){
            orderButton.disabled = false;
            orderButton.innerText = "অর্ডার করুন";
        }
    }

}


/* ================= DATABASE HERO SLIDER ================= */

let currentSlide = 0;
let slides = [];
let dots = [];
let heroTimer = null;

function showSlide(index){

    if(!slides.length){
        return;
    }

    currentSlide = index;

    slides.forEach((slide, i)=>{
        slide.classList.toggle("active", i === index);
    });

    dots.forEach((dot, i)=>{
        dot.classList.toggle("active", i === index);
    });

}

async function loadHeroBanners(){

    const slider = document.getElementById("heroSlider");
    const dotsContainer = document.getElementById("heroDots");

    if(!slider || !dotsContainer){
        return;
    }

    try{

        const response = await fetch(
            "hero-api.php?v=" + Date.now(),
            {cache:"no-store"}
        );

        if(!response.ok){
            throw new Error("Hero API request failed");
        }

        const data = await response.json();

        if(!data.success || !Array.isArray(data.banners)){
            throw new Error("Hero data not found");
        }

        slider.innerHTML = "";
        dotsContainer.innerHTML = "";

        data.banners.forEach((banner, index)=>{

            const slide = document.createElement("div");
            slide.className = "hero-slide" + (index === 0 ? " active" : "");

            if(banner.image_url){
                slide.style.backgroundImage = `url("${String(banner.image_url).replace(/"/g, '%22')}")`;
                slide.style.backgroundSize = "cover";
                slide.style.backgroundPosition = "center";
            }

            const content = document.createElement("div");
            content.className = "hero-content";

            if(banner.subtitle){
                const small = document.createElement("small");
                small.textContent = banner.subtitle;
                content.appendChild(small);
            }

            if(banner.title){
                const title = document.createElement("h1");
                title.textContent = banner.title;
                content.appendChild(title);
            }

            if(banner.description){
                const description = document.createElement("p");
                description.textContent = banner.description;
                content.appendChild(description);
            }

            if(banner.button_text){

                const buttonText = String(banner.button_text);

                if(buttonText.includes("প্রোমো কোড")){

                    const button = document.createElement("button");
                    button.className = "hero-btn";
                    button.type = "button";
                    button.textContent = buttonText;
                    button.addEventListener("click", openPromo);
                    content.appendChild(button);

                }else{

                    const button = document.createElement("a");
                    button.className = "hero-btn";
                    button.href = banner.button_link || "#";
                    button.textContent = buttonText;
                    content.appendChild(button);

                }

            }

            slide.appendChild(content);
            slider.appendChild(slide);

            const dot = document.createElement("span");
            dot.className = "dot" + (index === 0 ? " active" : "");
            dot.addEventListener("click", ()=>showSlide(index));
            dotsContainer.appendChild(dot);

        });

        slides = Array.from(
            document.querySelectorAll("#heroSlider .hero-slide")
        );

        dots = Array.from(
            document.querySelectorAll("#heroDots .dot")
        );

        currentSlide = 0;
        showSlide(0);

        if(heroTimer){
            clearInterval(heroTimer);
            heroTimer = null;
        }

        if(slides.length > 1){
            heroTimer = setInterval(()=>{
                currentSlide++;

                if(currentSlide >= slides.length){
                    currentSlide = 0;
                }

                showSlide(currentSlide);
            }, 5000);
        }

    }catch(error){
        console.error("Hero Banner Error:", error);
    }

}

loadHeroBanners();

/* ================= SEARCH ================= */

function searchProducts(){

const search =  
    document  
    .getElementById("searchInput")  
    .value  
    .toLowerCase()  
    .trim();  

const products =  
    document.querySelectorAll(".product-card");  

products.forEach(product=>{  

    const text =  
        product.innerText.toLowerCase();  

    if(text.includes(search)){  
        product.style.display = "";  
    }else{  
        product.style.display = "none";  
    }  

});

}

/* ================= ESC KEY ================= */

document.addEventListener("keydown",(e)=>{

if(e.key === "Escape"){  

    closeMenu();  
    closeModal();  

}

});

/* ================= START ================= */

updateCartCount();