<?php
session_start();
require 'db.php';
if (getenv('APP_ENV') !== 'local') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors',1);
    error_reporting(E_ALL);
}

/* دالة slugify */
function slugify($text){
    $text=preg_replace('~[^\pL\d]+~u','-',$text);
    if(function_exists('iconv')) $text=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$text);
    $text=preg_replace('~[^-\w]+~','',$text);
    $text=preg_replace('~-+~','-',$text);
    return strtolower(trim($text,'-'))?:'store';
}

/* عدّاد السلة */
$cart_count=array_sum(array_column($_SESSION['cart']??[],'qty'));

/* متاجر */
$featured=$conn->query("SELECT id,name,logo FROM stores WHERE is_featured=1 ORDER BY id DESC LIMIT 8");
$stores  =$conn->query("SELECT id,name,description,logo FROM stores WHERE is_featured=0 OR is_featured IS NULL ORDER BY id DESC LIMIT 12");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>سوق الناصرية | اكتشف أفضل المتاجر في مكان واحد</title>
<meta name="description" content="سوق الناصرية الإلكتروني – منصة تجمع أفضل المتاجر المحلية في الناصرية. تسوق بسهولة واستمتع بالدفع عند الاستلام.">

<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700;800&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>
/* ========= VARIABLES ========= */
:root{
 --primary:#5A55D2;--secondary:#4440B3;--accent:#FF7B54;--gray:#6A7185;
 --light:#FDFDFF;--border:#D9DCE5;--radius:12px;--shadow:0 4px 6px rgba(0,0,0,.05);
 --shadow-hover:0 8px 25px rgba(0,0,0,.15);--trans:.3s ease;
}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Tajawal',sans-serif}
body{background:var(--light);color:#2D3748;line-height:1.6;overflow-x:hidden}
a{text-decoration:none;color:inherit}

/* ========= HEADER ========= */
header{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;
       padding:1rem 5%;position:fixed;top:0;inset-inline:0;z-index:1000;
       display:flex;justify-content:space-between;align-items:center;transition:var(--trans);
       box-shadow:var(--shadow)}
header.scrolled{padding:.8rem 5%;box-shadow:var(--shadow-hover)}
.logo{display:flex;align-items:center;font-size:1.5rem;font-weight:800;font-family:'Cairo',sans-serif}
.logo i{margin-inline-start:.5rem;color:var(--accent);font-size:1.3rem}
nav ul{display:flex;list-style:none;gap:1rem}
nav a{color:#fff;padding:.6rem 1.2rem;border-radius:25px;font-weight:500;transition:var(--trans);position:relative}
nav a:hover{background:rgba(255,255,255,.2);transform:translateY(-2px)}
.hamburger{display:none;font-size:1.5rem;cursor:pointer;background:none;border:none;color:#fff}
.cart-icon{position:relative;margin-inline-start:1rem;padding:.5rem;border-radius:50%;transition:var(--trans)}
.cart-icon:hover{background:rgba(255,255,255,.1)}
#cart-count{position:absolute;top:-2px;right:-2px;background:var(--accent);color:#fff;
            width:20px;height:20px;font-size:.7rem;font-weight:700;border-radius:50%;
            display:flex;align-items:center;justify-content:center;border:2px solid #fff}

/* ========= HERO ========= */
.hero{margin-top:80px;min-height:70vh;
      background:linear-gradient(135deg,rgba(90,85,210,.9),rgba(68,64,179,.8)),
                 url('https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1920&q=80') center/cover;
      display:flex;align-items:center;padding:0 5%;position:relative}
.hero-content{color:#fff;max-width:600px;animation:fadeInUp .8s}
.hero-content h1{font-size:3rem;margin-bottom:1.5rem;font-weight:800;line-height:1.2;font-family:'Cairo',sans-serif}
.hero-content p{font-size:1.2rem;margin-bottom:2rem;opacity:.95;line-height:1.7}
.hero-buttons{display:flex;gap:1rem;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:.5rem;background:var(--accent);
     color:#fff;padding:.9rem 2rem;border-radius:30px;font-weight:600;
     transition:var(--trans);box-shadow:var(--shadow);border:none;cursor:pointer}
.btn:hover{transform:translateY(-3px);box-shadow:var(--shadow-hover)}
.btn-secondary{background:rgba(255,255,255,.2);backdrop-filter:blur(10px)}
.btn-secondary:hover{background:rgba(255,255,255,.3)}

/* ========= SEARCH BAR ========= */
.search-section{padding:2rem 5%;background:#fff;margin-top:-50px;position:relative;z-index:10}
.search-container{max-width:600px;margin:0 auto;position:relative}
.search-box{width:100%;padding:1rem 1.5rem;border:2px solid var(--border);border-radius:50px;
            font-size:1rem;transition:var(--trans);background:#fff}
.search-box:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(90,85,210,.1)}
.search-btn{position:absolute;left:8px;top:50%;transform:translateY(-50%);
            background:var(--primary);color:#fff;border:none;padding:.7rem 1.2rem;
            border-radius:25px;cursor:pointer;transition:var(--trans)}
.search-btn:hover{background:var(--secondary)}

/* ========= FEATURED ========= */
.featured-section{padding:4rem 5% 3rem;background:var(--light)}
.section-title{text-align:center;margin-bottom:3rem}
.section-title h2{font-size:2.5rem;font-weight:800;color:#1A202C;font-family:'Cairo',sans-serif;
                  position:relative;display:inline-block}
.section-title h2::after{content:'';position:absolute;bottom:-10px;left:50%;transform:translateX(-50%);
                         width:60px;height:4px;background:linear-gradient(90deg,var(--primary),var(--accent));
                         border-radius:2px}
.section-title p{color:var(--gray);font-size:1.1rem;margin-top:1rem}
.featured-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem;margin-top:2rem}
.featured-card{background:#fff;border-radius:var(--radius);padding:1.5rem;text-align:center;
               box-shadow:var(--shadow);transition:var(--trans);border:1px solid var(--border)}
.featured-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-hover)}
.featured-logo{width:80px;height:80px;border-radius:50%;margin:0 auto 1rem;overflow:hidden;
               background:var(--light);display:flex;align-items:center;justify-content:center}
.featured-logo img{width:100%;height:100%;object-fit:cover}
.featured-logo i{font-size:2rem;color:var(--gray)}
.featured-name{font-weight:700;color:#1A202C;margin-bottom:.5rem}
.featured-card .btn{padding:.5rem 1.5rem;font-size:.9rem}

/* ========= STORES GRID ========= */
.stores-section{padding:4rem 5%;background:#fff}
.stores-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:2rem;margin-top:2rem}
.store-card{background:#fff;border-radius:var(--radius);padding:2rem;
            box-shadow:var(--shadow);transition:var(--trans);border:1px solid var(--border);
            display:flex;flex-direction:column;height:100%}
.store-card:hover{transform:translateY(-8px);box-shadow:var(--shadow-hover);border-color:var(--primary)}
.store-header{display:flex;align-items:center;margin-bottom:1.5rem}
.store-logo{width:60px;height:60px;border-radius:50%;overflow:hidden;margin-left:1rem;
            background:var(--light);display:flex;align-items:center;justify-content:center}
.store-logo img{width:100%;height:100%;object-fit:cover}
.store-logo i{font-size:1.5rem;color:var(--gray)}
.store-info h3{font-size:1.3rem;font-weight:700;color:#1A202C;margin-bottom:.3rem}
.store-rating{display:flex;align-items:center;gap:.3rem;color:var(--gray);font-size:.9rem}
.store-rating i{color:#FFD700}
.store-description{color:var(--gray);line-height:1.6;margin-bottom:1.5rem;flex-grow:1}
.store-footer{display:flex;justify-content:space-between;align-items:center}
.store-products{color:var(--gray);font-size:.9rem}
.store-card .btn{padding:.7rem 1.5rem}

/* ========= CATEGORIES ========= */
.categories-section{padding:4rem 5%;background:var(--light)}
.categories-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1.5rem;margin-top:2rem}
.category-card{background:#fff;border-radius:var(--radius);padding:2rem 1rem;text-align:center;
               box-shadow:var(--shadow);transition:var(--trans);border:1px solid var(--border)}
.category-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-hover)}
.category-icon{width:60px;height:60px;border-radius:50%;margin:0 auto 1rem;
               background:linear-gradient(135deg,var(--primary),var(--secondary));
               display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.5rem}
.category-name{font-weight:600;color:#1A202C}

/* ========= STATS ========= */
.stats-section{padding:4rem 5%;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:2rem;text-align:center}
.stat-item h3{font-size:2.5rem;font-weight:800;margin-bottom:.5rem;font-family:'Cairo',sans-serif}
.stat-item p{font-size:1.1rem;opacity:.9}

/* ========= FOOTER ========= */
footer{background:#1A202C;color:#fff;padding:3rem 5% 1rem;text-align:center}
.footer-content{max-width:600px;margin:0 auto}
.footer-content h3{font-size:1.5rem;margin-bottom:1rem;font-family:'Cairo',sans-serif}
.footer-content p{color:#A0AEC0;margin-bottom:2rem}
.social-links{display:flex;justify-content:center;gap:1rem;margin-bottom:2rem}
.social-links a{width:40px;height:40px;border-radius:50%;background:var(--primary);
                display:flex;align-items:center;justify-content:center;transition:var(--trans)}
.social-links a:hover{background:var(--accent);transform:translateY(-2px)}
.footer-bottom{border-top:1px solid #2D3748;padding-top:1rem;color:#A0AEC0;font-size:.9rem}

/* ========= ANIMATIONS ========= */
@keyframes fadeInUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
.featured-card,.store-card,.category-card{animation:fadeInUp .6s ease forwards}
.featured-card:nth-child(1){animation-delay:.1s}
.featured-card:nth-child(2){animation-delay:.2s}
.featured-card:nth-child(3){animation-delay:.3s}
.featured-card:nth-child(4){animation-delay:.4s}

/* ========= RESPONSIVE ========= */
@media(max-width:768px){
 nav ul{display:none;position:absolute;top:100%;right:0;background:#fff;color:#333;
        flex-direction:column;padding:1rem;border-radius:var(--radius);box-shadow:var(--shadow);min-width:200px}
 nav ul.open{display:flex}
 nav ul a{color:#333;padding:.5rem 1rem}
 .hamburger{display:block}
 .hero{margin-top:70px;min-height:60vh;text-align:center}
 .hero-content h1{font-size:2rem}
 .hero-content p{font-size:1rem}
 .hero-buttons{justify-content:center}
 .search-section{margin-top:-30px;padding:1.5rem 5%}
 .featured-grid{grid-template-columns:repeat(2,1fr)}
 .stores-grid{grid-template-columns:1fr}
 .categories-grid{grid-template-columns:repeat(3,1fr)}
 .stats-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:480px){
 .logo span{font-size:1.2rem}
 .hero-content h1{font-size:1.7rem}
 .section-title h2{font-size:2rem}
 .featured-grid{grid-template-columns:1fr}
 .categories-grid{grid-template-columns:repeat(2,1fr)}
 .stats-grid{grid-template-columns:1fr}
}

/* ========= LOADING ========= */
.loading{opacity:0;animation:fadeInUp .6s ease forwards}
</style>
</head>
<body>

<header id="main-header">
  <div class="logo">
    <span>سوق الناصرية</span>
    <i class="fas fa-store"></i>
  </div>

  <nav>
    <button class="hamburger" aria-label="القائمة">
      <i class="fas fa-bars"></i>
    </button>
    <ul>
      <li><a href="/">الرئيسية</a></li>
      <li><a href="/stores">المتاجر</a></li>
      <li><a href="/cart">السلة</a></li>
      <li><a href="/contact">تواصل معنا</a></li>
    </ul>
  </nav>

  <a href="/cart" class="cart-icon" aria-label="السلة">
    <i class="fas fa-shopping-cart"></i>
    <span id="cart-count"><?= $cart_count ?></span>
  </a>
</header>

<section class="hero">
  <div class="hero-content">
    <h1>اكتشف أفضل المتاجر في الناصرية</h1>
    <p>منصة تجمع أفضل المتاجر المحلية في مكان واحد. تسوق بسهولة واستمتع بتجربة تسوق فريدة مع الدفع عند الاستلام.</p>
    <div class="hero-buttons">
      <a href="#stores-section" class="btn">
        <i class="fas fa-store"></i>
        تصفح المتاجر
      </a>
      <a href="#featured-section" class="btn btn-secondary">
        <i class="fas fa-star"></i>
        المتاجر المميزة
      </a>
    </div>
  </div>
</section>

<section class="search-section">
  <div class="search-container">
    <input type="text" class="search-box" placeholder="ابحث عن متجر أو منتج...">
    <button class="search-btn">
      <i class="fas fa-search"></i>
    </button>
  </div>
</section>

<section class="featured-section" id="featured-section">
  <div class="section-title">
    <h2>المتاجر المميزة</h2>
    <p>اكتشف أفضل المتاجر المختارة بعناية لتقديم أجود المنتجات</p>
  </div>
  <div class="featured-grid">
    <?php if(!$featured || $featured->num_rows==0):?>
      <div style="grid-column:1/-1;text-align:center;color:var(--gray);padding:2rem">
        <i class="fas fa-store" style="font-size:3rem;margin-bottom:1rem;opacity:.5"></i>
        <p>لا يوجد متاجر مميزة حالياً</p>
      </div>
    <?php else: while($s=$featured->fetch_assoc()):?>
      <a href="/stores/<?= $s['id'] ?>/<?= slugify($s['name']) ?>" class="featured-card loading">
        <div class="featured-logo">
          <?php if($s['logo']):?>
              <img loading="lazy" src="<?= htmlspecialchars($s['logo']) ?>" alt="<?= htmlspecialchars($s['name']) ?>">
          <?php else:?><i class="fas fa-store"></i><?php endif;?>
        </div>
        <div class="featured-name"><?= htmlspecialchars($s['name']) ?></div>
        <div class="btn">زيارة المتجر</div>
      </a>
    <?php endwhile;endif;?>
  </div>
</section>

<section class="stores-section" id="stores-section">
  <div class="section-title">
    <h2>جميع المتاجر</h2>
    <p>تصفح مجموعة واسعة من المتاجر المحلية المتنوعة</p>
  </div>
  <div class="stores-grid">
    <?php if(!$stores || $stores->num_rows==0):?>
      <div style="grid-column:1/-1;text-align:center;color:var(--gray);padding:3rem">
        <i class="fas fa-shopping-bag" style="font-size:4rem;margin-bottom:1rem;opacity:.3"></i>
        <h3>لا توجد متاجر متاحة حالياً</h3>
        <p>سنقوم بإضافة المزيد من المتاجر قريباً</p>
      </div>
    <?php else: while($row=$stores->fetch_assoc()):?>
      <div class="store-card loading">
        <div class="store-header">
          <div class="store-logo">
            <?php if($row['logo']):?>
              <img loading="lazy" src="<?= htmlspecialchars($row['logo']) ?>" alt="<?= htmlspecialchars($row['name']) ?>">
            <?php else:?>
              <i class="fas fa-store"></i>
            <?php endif;?>
          </div>
          <div class="store-info">
            <h3><?= htmlspecialchars($row['name']) ?></h3>
            <div class="store-rating">
              <i class="fas fa-star"></i>
              <span>4.5</span>
              <span>(125 تقييم)</span>
            </div>
          </div>
        </div>
        <p class="store-description">
          <?= htmlspecialchars(mb_strimwidth($row['description'] ?: 'متجر متميز يقدم منتجات عالية الجودة بأسعار منافسة.',0,120,'...')) ?>
        </p>
        <div class="store-footer">
          <span class="store-products">
            <i class="fas fa-box"></i>
            50+ منتج
          </span>
          <a href="/stores/<?= $row['id'] ?>/<?= slugify($row['name']) ?>" class="btn">
            زيارة المتجر
            <i class="fas fa-arrow-left"></i>
          </a>
        </div>
      </div>
    <?php endwhile;endif;?>
  </div>
</section>

<section class="categories-section">
  <div class="section-title">
    <h2>تسوق حسب الفئة</h2>
    <p>اختر الفئة التي تناسبك واكتشف أفضل المنتجات</p>
  </div>
  <div class="categories-grid">
    <div class="category-card">
      <div class="category-icon">
        <i class="fas fa-tshirt"></i>
      </div>
      <div class="category-name">الملابس</div>
    </div>
    <div class="category-card">
      <div class="category-icon">
        <i class="fas fa-laptop"></i>
      </div>
      <div class="category-name">الإلكترونيات</div>
    </div>
    <div class="category-card">
      <div class="category-icon">
        <i class="fas fa-home"></i>
      </div>
      <div class="category-name">المنزل</div>
    </div>
    <div class="category-card">
      <div class="category-icon">
        <i class="fas fa-gamepad"></i>
      </div>
      <div class="category-name">الألعاب</div>
    </div>
    <div class="category-card">
      <div class="category-icon">
        <i class="fas fa-book"></i>
      </div>
      <div class="category-name">الكتب</div>
    </div>
    <div class="category-card">
      <div class="category-icon">
        <i class="fas fa-utensils"></i>
      </div>
      <div class="category-name">الطعام</div>
    </div>
  </div>
</section>

<section class="stats-section">
  <div class="stats-grid">
    <div class="stat-item">
      <h3>50+</h3>
      <p>متجر محلي</p>
    </div>
    <div class="stat-item">
      <h3>1000+</h3>
      <p>منتج متنوع</p>
    </div>
    <div class="stat-item">
      <h3>500+</h3>
      <p>عميل راضي</p>
    </div>
    <div class="stat-item">
      <h3>24/7</h3>
      <p>خدمة العملاء</p>
    </div>
  </div>
</section>

<footer>
  <div class="footer-content">
    <h3>سوق الناصرية</h3>
    <p>منصتك المفضلة للتسوق من أفضل المتاجر المحلية في الناصرية</p>
    <div class="social-links">
      <a href="#" aria-label="فيسبوك"><i class="fab fa-facebook-f"></i></a>
      <a href="#" aria-label="تويتر"><i class="fab fa-twitter"></i></a>
      <a href="#" aria-label="إنستغرام"><i class="fab fa-instagram"></i></a>
      <a href="#" aria-label="واتساب"><i class="fab fa-whatsapp"></i></a>
    </div>
  </div>
  <div class="footer-bottom">
    <p>&copy; 2024 سوق الناصرية. جميع الحقوق محفوظة.</p>
  </div>
</footer>

<script>
/* Header scroll effect */
const header=document.getElementById('main-header');
window.addEventListener('scroll',()=>{
  header.classList.toggle('scrolled',window.scrollY>50);
});

/* Hamburger menu */
const hamburger=document.querySelector('.hamburger');
const navList=document.querySelector('nav ul');
hamburger.addEventListener('click',()=>{
  navList.classList.toggle('open');
});

/* Smooth scrolling */
document.querySelectorAll('a[href^="#"]').forEach(anchor=>{
  anchor.addEventListener('click',function(e){
    e.preventDefault();
    const target=document.querySelector(this.getAttribute('href'));
    if(target){
      target.scrollIntoView({behavior:'smooth',block:'start'});
    }
  });
});

/* Search functionality */
const searchBox=document.querySelector('.search-box');
const searchBtn=document.querySelector('.search-btn');

searchBtn.addEventListener('click',()=>{
  const query=searchBox.value.trim();
  if(query){
    window.location.href=`/search?q=${encodeURIComponent(query)}`;
  }
});

searchBox.addEventListener('keypress',(e)=>{
  if(e.key==='Enter'){
    searchBtn.click();
  }
});

/* Loading animation */
const observerOptions={
  threshold:0.1,
  rootMargin:'0px 0px -50px 0px'
};

const observer=new IntersectionObserver((entries)=>{
  entries.forEach(entry=>{
    if(entry.isIntersecting){
      entry.target.style.opacity='1';
      entry.target.style.transform='translateY(0)';
    }
  });
},observerOptions);

document.querySelectorAll('.loading').forEach(el=>{
  observer.observe(el);
});
</script>
</body>
</html>
