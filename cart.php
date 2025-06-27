<?php
session_start();
require_once 'csrf.php';

/* محتوى السلة من الجلسة */
$cart = $_SESSION['cart'] ?? [];

/* عدّاد السلة للهيدر */
$cart_count = array_sum(array_column($cart, 'qty'));

/* تنسيق السعر */
function fmt($n) { return number_format($n, 0, '.', ',') . ' د.ع'; }

/* تجميع المنتجات حسب المتجر */
$stores = [];
foreach ($cart as $it) {
    $sid = $it['store_id'];
    $stores[$sid]['store_name']  = $it['store_name'];
    $stores[$sid]['products'][] = $it;
}

/* حساب الإجماليات */
$grand = 0;
$store_totals = [];
foreach ($stores as $sid => $st) {
    $t = 0;
    foreach ($st['products'] as $p) $t += $p['price'] * $p['qty'];
    $store_totals[$sid] = $t;
    $grand += $t;
}

/* رسوم التوصيل */
$delivery_fee = count($stores) * 2000; // 2000 د.ع لكل متجر
$final_total = $grand + $delivery_fee;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>سلة المشتريات | سوق الناصرية</title>
<meta name="description" content="راجع منتجاتك في سلة المشتريات واتمم عملية الشراء بسهولة">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700;800&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">

<style>
:root{
  --primary:#5A55D2;--secondary:#4440B3;--accent:#FF7B54;--success:#10B981;
  --warning:#F59E0B;--danger:#EF4444;--gray:#6A7185;--light:#FDFDFF;
  --border:#D9DCE5;--radius:12px;--shadow:0 4px 6px rgba(0,0,0,.05);
  --shadow-hover:0 8px 25px rgba(0,0,0,.15);--trans:.3s ease;
}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Tajawal',sans-serif}
body{background:var(--light);color:#2D3748;line-height:1.6}

/* ========= HEADER ========= */
header{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;
       padding:1rem 5%;position:fixed;top:0;inset-inline:0;z-index:1000;
       display:flex;justify-content:space-between;align-items:center;transition:var(--trans);
       box-shadow:var(--shadow)}
.logo{display:flex;align-items:center;font-size:1.5rem;font-weight:800;font-family:'Cairo',sans-serif}
.logo i{margin-inline-start:.5rem;color:var(--accent);font-size:1.3rem}
nav ul{display:flex;list-style:none;gap:1rem}
nav a{color:#fff;padding:.6rem 1.2rem;border-radius:25px;font-weight:500;transition:var(--trans)}
nav a:hover{background:rgba(255,255,255,.2);transform:translateY(-2px)}
.hamburger{display:none;font-size:1.5rem;cursor:pointer;background:none;border:none;color:#fff}
.cart-icon{position:relative;margin-inline-start:1rem;padding:.5rem;border-radius:50%;transition:var(--trans)}
.cart-icon:hover{background:rgba(255,255,255,.1)}
#cart-count{position:absolute;top:-2px;right:-2px;background:var(--accent);color:#fff;
            width:20px;height:20px;font-size:.7rem;font-weight:700;border-radius:50%;
            display:flex;align-items:center;justify-content:center;border:2px solid #fff}

/* ========= MAIN CONTENT ========= */
.main-content{margin-top:100px;padding:2rem 5%;min-height:calc(100vh - 100px)}
.container{max-width:1200px;margin:0 auto}

/* ========= PAGE HEADER ========= */
.page-header{text-align:center;margin-bottom:3rem}
.page-header h1{font-size:2.5rem;font-weight:800;color:#1A202C;margin-bottom:.5rem;font-family:'Cairo',sans-serif}
.page-header p{color:var(--gray);font-size:1.1rem}
.breadcrumb{display:flex;justify-content:center;gap:.5rem;margin-top:1rem;color:var(--gray);font-size:.9rem}
.breadcrumb a{color:var(--primary);text-decoration:none}
.breadcrumb a:hover{text-decoration:underline}

/* ========= CART LAYOUT ========= */
.cart-layout{display:grid;grid-template-columns:1fr 350px;gap:2rem;align-items:start}

/* ========= CART ITEMS ========= */
.cart-section{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden}
.section-header{padding:1.5rem;border-bottom:1px solid var(--border);background:linear-gradient(135deg,#f8f9fa,#fff)}
.section-header h2{font-size:1.3rem;font-weight:700;color:#1A202C;display:flex;align-items:center;gap:.5rem}
.section-header h2 i{color:var(--primary)}

.store-group{border-bottom:1px solid var(--border)}
.store-group:last-child{border-bottom:none}
.store-header{padding:1rem 1.5rem;background:#f8f9fa;border-bottom:1px solid var(--border);
              display:flex;align-items:center;justify-content:space-between}
.store-name{font-weight:600;color:#1A202C;display:flex;align-items:center;gap:.5rem}
.store-name i{color:var(--primary)}
.store-total{font-weight:700;color:var(--secondary)}

.cart-item{padding:1.5rem;border-bottom:1px solid var(--border);display:flex;gap:1rem;
           transition:var(--trans);position:relative}
.cart-item:last-child{border-bottom:none}
.cart-item:hover{background:#f8f9fa}

.item-image{width:100px;height:100px;border-radius:var(--radius);overflow:hidden;
            background:#f0f0f0;display:flex;align-items:center;justify-content:center;
            box-shadow:var(--shadow)}
.item-image img{width:100%;height:100%;object-fit:cover}
.item-image i{font-size:2rem;color:var(--gray)}

.item-details{flex:1;display:flex;flex-direction:column;gap:.5rem}
.item-name{font-size:1.1rem;font-weight:600;color:#1A202C;line-height:1.4}
.item-options{display:flex;gap:1rem;flex-wrap:wrap}
.item-option{display:flex;align-items:center;gap:.3rem;color:var(--gray);font-size:.9rem}
.color-dot{width:16px;height:16px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 0 1px var(--border)}
.item-price{font-size:1.2rem;font-weight:700;color:var(--secondary)}

.item-actions{display:flex;flex-direction:column;gap:1rem;align-items:flex-end}
.quantity-controls{display:flex;align-items:center;gap:.5rem;background:#f8f9fa;
                   border-radius:25px;padding:.3rem;border:1px solid var(--border)}
.qty-btn{width:32px;height:32px;border:none;background:var(--primary);color:#fff;
         border-radius:50%;cursor:pointer;display:flex;align-items:center;
         justify-content:center;transition:var(--trans);font-size:.9rem}
.qty-btn:hover{background:var(--secondary);transform:scale(1.1)}
.qty-btn:disabled{opacity:.5;cursor:not-allowed;transform:none}
.qty-display{min-width:40px;text-align:center;font-weight:600;color:#1A202C}

.remove-btn{background:none;border:none;color:var(--danger);cursor:pointer;
            padding:.5rem;border-radius:50%;transition:var(--trans);
            display:flex;align-items:center;justify-content:center}
.remove-btn:hover{background:rgba(239,68,68,.1);transform:scale(1.1)}

/* ========= ORDER SUMMARY ========= */
.order-summary{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);
               position:sticky;top:120px;height:fit-content}
.summary-content{padding:1.5rem}
.summary-row{display:flex;justify-content:space-between;align-items:center;
             padding:.8rem 0;border-bottom:1px solid var(--border)}
.summary-row:last-child{border-bottom:none;font-weight:700;font-size:1.1rem;color:#1A202C}
.summary-label{color:var(--gray)}
.summary-value{font-weight:600;color:#1A202C}
.delivery-info{background:#f8f9fa;padding:1rem;border-radius:8px;margin:1rem 0;
               border-left:4px solid var(--primary)}
.delivery-info h4{color:#1A202C;margin-bottom:.5rem;font-size:.9rem}
.delivery-info p{color:var(--gray);font-size:.8rem;line-height:1.5}

.checkout-section{padding:1.5rem;border-top:1px solid var(--border);background:#f8f9fa}
.checkout-btn{width:100%;padding:1rem;background:linear-gradient(135deg,var(--success),#059669);
              color:#fff;border:none;border-radius:var(--radius);font-size:1.1rem;
              font-weight:600;cursor:pointer;transition:var(--trans);
              display:flex;align-items:center;justify-content:center;gap:.5rem}
.checkout-btn:hover{transform:translateY(-2px);box-shadow:var(--shadow-hover)}
.checkout-btn:disabled{opacity:.6;cursor:not-allowed;transform:none}

.continue-shopping{text-align:center;margin-top:1rem}
.continue-shopping a{color:var(--primary);text-decoration:none;font-weight:500;
                     display:inline-flex;align-items:center;gap:.5rem;transition:var(--trans)}
.continue-shopping a:hover{color:var(--secondary);transform:translateX(5px)}

/* ========= EMPTY CART ========= */
.empty-cart{text-align:center;padding:4rem 2rem;background:#fff;border-radius:var(--radius);
            box-shadow:var(--shadow)}
.empty-cart i{font-size:4rem;color:var(--gray);opacity:.3;margin-bottom:1.5rem}
.empty-cart h2{font-size:1.8rem;color:#1A202C;margin-bottom:1rem;font-family:'Cairo',sans-serif}
.empty-cart p{color:var(--gray);margin-bottom:2rem;font-size:1.1rem}
.empty-cart .btn{background:var(--primary);color:#fff;padding:1rem 2rem;
                 border-radius:25px;text-decoration:none;font-weight:600;
                 display:inline-flex;align-items:center;gap:.5rem;transition:var(--trans)}
.empty-cart .btn:hover{background:var(--secondary);transform:translateY(-2px)}

/* ========= PROMO CODE ========= */
.promo-section{margin-top:1.5rem;padding:1.5rem;background:#f8f9fa;border-radius:var(--radius)}
.promo-section h4{margin-bottom:1rem;color:#1A202C;font-size:1rem}
.promo-form{display:flex;gap:.5rem}
.promo-input{flex:1;padding:.8rem;border:2px solid var(--border);border-radius:8px;
             font-size:.9rem;transition:var(--trans)}
.promo-input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(90,85,210,.1)}
.promo-btn{padding:.8rem 1.5rem;background:var(--primary);color:#fff;border:none;
           border-radius:8px;font-weight:600;cursor:pointer;transition:var(--trans)}
.promo-btn:hover{background:var(--secondary)}

/* ========= TOAST ========= */
#toast{position:fixed;bottom:25px;left:50%;transform:translateX(-50%);
       background:#2D3748;color:#fff;padding:1rem 1.5rem;border-radius:8px;
       opacity:0;pointer-events:none;transition:var(--trans);z-index:1001;
       box-shadow:var(--shadow-hover)}
#toast.show{opacity:1;pointer-events:auto}
#toast.success{background:var(--success)}
#toast.error{background:var(--danger)}

/* ========= RESPONSIVE ========= */
@media(max-width:768px){
 nav ul{display:none;position:absolute;top:100%;right:0;background:#fff;color:#333;
        flex-direction:column;padding:1rem;border-radius:var(--radius);box-shadow:var(--shadow);min-width:200px}
 nav ul.open{display:flex}
 nav ul a{color:#333;padding:.5rem 1rem}
 .hamburger{display:block}
 .cart-layout{grid-template-columns:1fr;gap:1.5rem}
 .order-summary{position:static}
 .cart-item{flex-direction:column;text-align:center}
 .item-image{width:80px;height:80px;margin:0 auto}
 .item-actions{flex-direction:row;justify-content:space-between;align-items:center}
 .page-header h1{font-size:2rem}
}
@media(max-width:480px){
 .main-content{padding:1rem 3%}
 .cart-item{padding:1rem}
 .item-image{width:60px;height:60px}
 .item-name{font-size:1rem}
 .checkout-btn{font-size:1rem;padding:.9rem}
}

/* ========= LOADING ========= */
.loading{opacity:0;animation:fadeInUp .6s ease forwards}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.cart-item:nth-child(1){animation-delay:.1s}
.cart-item:nth-child(2){animation-delay:.2s}
.cart-item:nth-child(3){animation-delay:.3s}
</style>
</head>
<body>

<header>
  <div class="logo">
    <span>سوق الناصرية</span>
    <i class="fas fa-store"></i>
  </div>
  
  <nav>
    <button class="hamburger" aria-label="القائمة">
      <i class="fas fa-bars"></i>
    </button>
    <ul>
      <li><a href="/">الرئيسية</a></li> <li><a href="/stores">المتاجر</a></li> <li><a href="/cart" class="active">السلة</a></li> </ul>
  </nav>

  <a href="/cart" class="cart-icon" aria-label="السلة"> <i class="fas fa-shopping-cart"></i>
    <span id="cart-count"><?= $cart_count ?></span>
  </a>
</header>

<main class="main-content">
  <div class="container">
    
    <div class="page-header">
      <h1>سلة المشتريات</h1>
      <p>راجع منتجاتك المختارة واتمم عملية الشراء</p>
      <div class="breadcrumb">
        <a href="/">الرئيسية</a> <i class="fas fa-chevron-left"></i>
        <span>سلة المشتريات</span>
      </div>
    </div>

    <?php if(empty($cart)): ?>
      <div class="empty-cart">
        <i class="fas fa-shopping-cart"></i>
        <h2>سلة المشتريات فارغة</h2>
        <p>لم تقم بإضافة أي منتجات لسلة المشتريات بعد</p>
        <a href="/stores" class="btn"> <i class="fas fa-store"></i>
          تصفح المتاجر
        </a>
      </div>
    <?php else: ?>
      
      <div class="cart-layout">
        
        <div class="cart-section">
          <div class="section-header">
            <h2>
              <i class="fas fa-shopping-bag"></i>
              منتجاتك (<?= $cart_count ?> منتج)
            </h2>
          </div>
          
          <?php foreach($stores as $sid => $store): ?>
            <div class="store-group">
              <div class="store-header">
                <div class="store-name">
                  <i class="fas fa-store"></i>
                  <?= htmlspecialchars($store['store_name']) ?>
                </div>
                <div class="store-total"><?= fmt($store_totals[$sid]) ?></div>
              </div>
              
              <?php foreach($store['products'] as $item): ?>
                <div class="cart-item loading" data-product-id="<?= $item['product_id'] ?>">
                  
                  <div class="item-image">
                    <?php if(!empty($item['image'])): ?>
                      <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
                    <?php else: ?>
                      <i class="fas fa-image"></i>
                    <?php endif; ?>
                  </div>
                  
                  <div class="item-details">
                    <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                    
                    <div class="item-options">
                      <?php if(!empty($item['color'])): ?>
                        <div class="item-option">
                          <span>اللون:</span>
                          <div class="color-dot" style="background:<?= htmlspecialchars($item['color']) ?>"></div>
                          <span><?= htmlspecialchars($item['color_name'] ?? 'غير محدد') ?></span>
                        </div>
                      <?php endif; ?>
                      
                      <?php if(!empty($item['size'])): ?>
                        <div class="item-option">
                          <i class="fas fa-ruler"></i>
                          <span>المقاس: <?= htmlspecialchars($item['size']) ?></span>
                        </div>
                      <?php endif; ?>
                    </div>
                    
                    <div class="item-price"><?= fmt($item['price']) ?></div>
                  </div>
                  
                  <div class="item-actions">
                    <div class="quantity-controls">
                      <button class="qty-btn" onclick="updateQuantity(<?= $item['product_id'] ?>, -1)" 
                              <?= $item['qty'] <= 1 ? 'disabled' : '' ?>>
                        <i class="fas fa-minus"></i>
                      </button>
                      <span class="qty-display"><?= $item['qty'] ?></span>
                      <button class="qty-btn" onclick="updateQuantity(<?= $item['product_id'] ?>, 1)">
                        <i class="fas fa-plus"></i>
                      </button>
                    </div>
                    
                    <button class="remove-btn" onclick="removeItem(<?= $item['product_id'] ?>)" 
                            title="إزالة من السلة">
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                  
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
        
        <div class="order-summary">
          <div class="section-header">
            <h2>
              <i class="fas fa-receipt"></i>
              ملخص الطلب
            </h2>
          </div>
          
          <div class="summary-content">
            <div class="summary-row">
              <span class="summary-label">المجموع الفرعي:</span>
              <span class="summary-value" id="subtotal"><?= fmt($grand) ?></span>
            </div>
            
            <div class="summary-row">
              <span class="summary-label">رسوم التوصيل:</span>
              <span class="summary-value" id="delivery-fee"><?= fmt($delivery_fee) ?></span>
            </div>
            
            <div class="delivery-info">
              <h4>معلومات التوصيل</h4>
              <p>رسوم التوصيل 2,000 د.ع لكل متجر. التوصيل خلال 24-48 ساعة داخل الناصرية.</p>
            </div>
            
            <div class="summary-row">
              <span class="summary-label">المجموع الكلي:</span>
              <span class="summary-value" id="total"><?= fmt($final_total) ?></span>
            </div>
          </div>
          
          <div class="promo-section">
            <h4>كود الخصم</h4>
            <form class="promo-form" onsubmit="applyPromo(event)">
              <input type="text" class="promo-input" placeholder="أدخل كود الخصم" id="promo-code">
              <button type="submit" class="promo-btn">تطبيق</button>
            </form>
          </div>
          
          <div class="checkout-section">
            <button class="checkout-btn" onclick="proceedToCheckout()">
              <i class="fas fa-credit-card"></i>
              إتمام الطلب
            </button>
            
            <div class="continue-shopping">
              <a href="/"> <i class="fas fa-arrow-right"></i>
                متابعة التسوق
              </a>
            </div>
          </div>
        </div>
        
      </div>
    <?php endif; ?>
  </div>
</main>

<div id="toast"></div>

<script>
/* Hamburger menu */
const hamburger=document.querySelector('.hamburger');
const navList=document.querySelector('nav ul');
hamburger.addEventListener('click',()=>{
  navList.classList.toggle('open');
});

/* Update quantity */
function updateQuantity(productId, change) {
  const data = new FormData();
  data.append('action', 'update');
  data.append('product_id', productId);
  data.append('change', change);
  data.append('csrf_token', '<?= csrf_token() ?>');
  
  fetch('/cart_actions.php', {
    method: 'POST',
    body: data
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      location.reload(); // Reload to update all totals
    } else {
      showToast(d.msg || 'حدث خطأ أثناء تحديث الكمية', 'error');
    }
  })
  .catch(e => {
    console.error(e);
    showToast('حدث خطأ في الاتصال', 'error');
  });
}

/* Remove item */
function removeItem(productId) {
  if (!confirm('هل أنت متأكد من إزالة هذا المنتج من السلة؟')) return;
  
  const data = new FormData();
  data.append('action', 'remove');
  data.append('product_id', productId);
  data.append('csrf_token', '<?= csrf_token() ?>');
  
  fetch('/cart_actions.php', {
    method: 'POST',
    body: data
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      location.reload();
    } else {
      showToast(d.msg || 'حدث خطأ أثناء إزالة المنتج', 'error');
    }
  })
  .catch(e => {
    console.error(e);
    showToast('حدث خطأ في الاتصال', 'error');
  });
}

/* Apply promo code */
function applyPromo(event) {
  event.preventDefault();
  const code = document.getElementById('promo-code').value.trim();
  
  if (!code) {
    showToast('يرجى إدخال كود الخصم', 'error');
    return;
  }
  
  const data = new FormData();
  data.append('action', 'promo');
  data.append('code', code);
  data.append('csrf_token', '<?= csrf_token() ?>');
  
  fetch('/cart_actions.php', {
    method: 'POST',
    body: data
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      showToast('تم تطبيق كود الخصم بنجاح!', 'success');
      location.reload();
    } else {
      showToast(d.msg || 'كود الخصم غير صحيح', 'error');
    }
  })
  .catch(e => {
    console.error(e);
    showToast('حدث خطأ في الاتصال', 'error');
  });
}

/* Proceed to checkout */
function proceedToCheckout() {
  <?php if(empty($cart)): ?>
    showToast('السلة فارغة', 'error');
    return;
  <?php endif; ?>
  
  // Redirect to checkout page
  window.location.href = '/checkout'; // تم التحديث
}

/* Toast notification */
function showToast(message, type = 'info') {
  const toast = document.getElementById('toast');
  toast.textContent = message;
  toast.className = type === 'success' ? 'show success' : 
                   type === 'error' ? 'show error' : 'show';
  
  setTimeout(() => {
    toast.classList.remove('show', 'success', 'error');
  }, 3000);
}

/* Loading animations */
window.addEventListener('DOMContentLoaded', () => {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.loading').forEach(el => {
    observer.observe(el);
  });
});

/* Auto-save cart state */
window.addEventListener('beforeunload', () => {
  // Save any pending changes
  localStorage.setItem('cart_timestamp', Date.now());
});
</script>
</body>
</html>
