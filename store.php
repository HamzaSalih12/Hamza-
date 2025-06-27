<?php
ini_set("display_errors", 0);
error_reporting(E_ALL);
session_start();
require "db.php";
require_once "csrf.php";

// دالة slugify (إذا لم تكن موجودة في هذا الملف، يجب أن تكون متاحة عبر require أو autoload)
// سنضيفها هنا لضمان توفرها إذا لم تكن موجودة في مكان آخر مشترك
if (!function_exists('slugify')) {
    function slugify($text){
        $text=preg_replace('~[^\pL\d]+~u','-',$text);
        if(function_exists('iconv')) $text=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$text);
        $text=preg_replace('~[^-\w]+~','',$text);
        $text=preg_replace('~-+~','-',$text);
        return strtolower(trim($text,'-'))?:'item'; // Changed default from 'store' to 'item' for product slugs
    }
}


function fix_url(string $u): string{
    if(!$u) return "";
    return (preg_match("#^(https?://|/)#i",$u)) ? $u : "/".ltrim($u,"/");
}

function handle_error_and_exit($msg) {
    error_log("Store Page Error: " . $msg);
    die("<h2 style=\"margin:80px;text-align:center\">حدث خطأ غير متوقع. يرجى المحاولة لاحقًا.</h2>");
}

/* بيانات المتجر */
$store_id = intval($_GET["id"] ?? 0);
$user_id  = $_SESSION["user_id"] ?? 0;

$st = $conn->prepare("SELECT * FROM stores WHERE id=?");
if (!$st) {
    handle_error_and_exit("Failed to prepare store data statement: " . $conn->error);
}
$st->bind_param("i",$store_id);
if (!$st->execute()) {
    handle_error_and_exit("Failed to execute store data statement: " . $st->error);
}
$store_result = $st->get_result();
if (!$store_result) {
    handle_error_and_exit("Failed to get store data result: " . $conn->error);
}
$store = $store_result->fetch_assoc();
if (!$store) {
    die("<h2 style=\"margin:80px;text-align:center\">المتجر غير موجود</h2>");
}

/* الأصناف والماركات */
$categories = [];
$cat_query = $conn->query("SELECT DISTINCT category FROM products WHERE store_id=$store_id AND category!=''");
if ($cat_query) {
    $categories = array_column($cat_query->fetch_all(MYSQLI_ASSOC), 'category');
} else {
    error_log("Store Page Error: Failed to get categories: " . $conn->error);
}

$brands = [];
$brand_query = $conn->query("SELECT DISTINCT brand FROM products WHERE store_id=$store_id AND brand!=''");
if ($brand_query) {
    $brands = array_column($brand_query->fetch_all(MYSQLI_ASSOC), 'brand');
} else {
    error_log("Store Page Error: Failed to get brands: " . $conn->error);
}

/* المنتجات */
$products = [];
$products_query = $conn->query("SELECT * FROM products WHERE store_id=$store_id ORDER BY id DESC");
if ($products_query) {
    $products = $products_query->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("Store Page Error: Failed to get products: " . $conn->error);
}

/* ألوان/مقاسات/صور */
$product_colors=$product_sizes=$product_images=[];
if($products){
    $ids = array_column($products,'id');
    $place = implode(',',array_fill(0,count($ids),'?'));
    $types = str_repeat('i',count($ids));

    $stmt_imgs=$conn->prepare("SELECT product_id,img_url FROM product_images WHERE product_id IN ($place)");
    if (!$stmt_imgs) {
        error_log("Store Page Error: Failed to prepare product images statement: " . $conn->error);
    } else {
        $stmt_imgs->bind_param($types,...$ids);
        if (!$stmt_imgs->execute()) {
            error_log("Store Page Error: Failed to execute product images statement: " . $stmt_imgs->error);
        } else {
            $imgs_result = $stmt_imgs->get_result();
            if ($imgs_result) {
                foreach($imgs_result as $r) $product_images[$r['product_id']][]=$r['img_url'];
            } else {
                error_log("Store Page Error: Failed to get product images result: " . $conn->error);
            }
        }
    }

    foreach(['product_colors','product_sizes'] as $tbl){
        $stmt_opts=$conn->prepare("SELECT * FROM $tbl WHERE product_id IN ($place)");
        if (!$stmt_opts) {
            error_log("Store Page Error: Failed to prepare options statement for " . $tbl . ": " . $conn->error);
        } else {
            $stmt_opts->bind_param($types,...$ids);
            if (!$stmt_opts->execute()) {
                error_log("Store Page Error: Failed to execute options statement for " . $tbl . ": " . $stmt_opts->error);
            } else {
                $opts_result = $stmt_opts->get_result();
                if ($opts_result) {
                    foreach($opts_result as $r) ${$tbl}[$r['product_id']][]=$r;
                } else {
                    error_log("Store Page Error: Failed to get options result for " . $tbl . ": " . $conn->error);
                }
            }
        }
    }
}

/* التقييم */
$rating=['avg'=>0,'count'=>0,'user'=>0];
$rv=$conn->prepare("SELECT ROUND(AVG(rating),2) AS a,COUNT(*) AS c FROM store_ratings WHERE store_id=?");
if (!$rv) {
    error_log("Store Page Error: Failed to prepare rating average statement: " . $conn->error);
} else {
    $rv->bind_param("i",$store_id);
    if (!$rv->execute()) {
        error_log("Store Page Error: Failed to execute rating average statement: " . $rv->error);
    } else {
        $rv_result = $rv->get_result();
        if ($rv_result) {
            $tmp=$rv_result->fetch_assoc();
            $rating['avg']=$tmp['a']??0; $rating['count']=$tmp['c']??0;
        } else {
            error_log("Store Page Error: Failed to get rating average result: " . $conn->error);
        }
    }
}

if($user_id){
  $ru=$conn->prepare("SELECT rating FROM store_ratings WHERE store_id=? AND user_id=?");
  if (!$ru) {
      error_log("Store Page Error: Failed to prepare user rating statement: " . $conn->error);
  } else {
      $ru->bind_param("ii",$store_id,$user_id);
      if (!$ru->execute()) {
          error_log("Store Page Error: Failed to execute user rating statement: " . $ru->error);
      } else {
          $ru_result = $ru->get_result();
          if ($ru_result) {
              $rating['user']=$ru_result->fetch_assoc()['rating']??0;
          } else {
              error_log("Store Page Error: Failed to get user rating result: " . $conn->error);
          }
      }
  }
}

/* عدد السلة لعرضه في الهيدر */
$cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'],'qty')) : 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=htmlspecialchars($store['name'])?> | سوق الناصرية</title>
<meta name="description" content="تسوق من <?=htmlspecialchars($store['name'])?> في سوق الناصرية. اكتشف منتجات متنوعة بأفضل الأسعار.">

<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700;800&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>

<style>
:root{
 --primary:#5A55D2;--secondary:#4440B3;--accent:#FF7B54;--gray:#6A7185;
 --light:#FDFDFF;--border:#D9DCE5;--radius:12px;--shadow:0 4px 6px rgba(0,0,0,.05);
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

/* ========= STORE BANNER ========= */
.store-banner{margin-top:80px;background:#fff;padding:2rem 5%;
              border-bottom:1px solid var(--border);position:relative}
.store-banner::before{content:'';position:absolute;top:0;left:0;right:0;height:100px;
                      background:linear-gradient(135deg,var(--primary)20%,var(--secondary));opacity:.05}
.store-content{position:relative;display:flex;gap:2rem;align-items:center;max-width:1200px;margin:0 auto}
.store-logo{width:120px;height:120px;border-radius:var(--radius);overflow:hidden;
            box-shadow:var(--shadow);background:#fff;border:3px solid #fff;
            display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:var(--primary)}
.store-logo img{width:100%;height:100%;object-fit:cover}
.store-info{flex:1}
.store-info h1{font-size:2.5rem;margin-bottom:.5rem;font-weight:800;color:#1A202C;font-family:'Cairo',sans-serif}
.store-info p{color:var(--gray);font-size:1.1rem;margin-bottom:1rem;line-height:1.7}
.store-stats{display:flex;gap:2rem;margin-bottom:1rem}
.stat-item{text-align:center}
.stat-item .number{font-size:1.5rem;font-weight:700;color:var(--primary);display:block}
.stat-item .label{font-size:.9rem;color:var(--gray)}
.rating-section{display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
.rating-display{display:flex;align-items:center;gap:.5rem}
.rating-stars{font-size:1.2rem;color:#FFD700}
.rating-text{color:var(--gray);font-size:.9rem}

/* ========= RATING FORM ========= */
.rating-form{display:flex;align-items:center;gap:.5rem}
.rating-form .rating-stars{cursor:pointer}
.rating-form .star{transition:var(--trans);cursor:pointer}
.rating-form .star:hover{transform:scale(1.1)}
.rate-btn{background:var(--primary);color:#fff;border:none;padding:.5rem 1rem;
          border-radius:20px;font-size:.9rem;cursor:pointer;transition:var(--trans)}
.rate-btn:hover{background:var(--secondary)}

/* ========= FILTERS ========= */
.filters-section{background:#fff;padding:1.5rem 5%;border-bottom:1px solid var(--border)}
.filters-container{max-width:1200px;margin:0 auto}
.categories-filter{margin-bottom:1.5rem}
.categories-scroll{display:flex;gap:.5rem;overflow-x:auto;padding-bottom:.5rem}
.categories-scroll::-webkit-scrollbar{height:4px}
.categories-scroll::-webkit-scrollbar-thumb{background:var(--primary);border-radius:2px}
.category-btn{background:#fff;border:2px solid var(--border);color:var(--gray);
              padding:.6rem 1.2rem;border-radius:25px;font-weight:500;cursor:pointer;
              transition:var(--trans);white-space:nowrap;font-size:.9rem}
.category-btn.active,.category-btn:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
.search-filters{display:flex;gap:1rem;flex-wrap:wrap}
.search-input{flex:1;min-width:250px;padding:.8rem 1rem;border:2px solid var(--border);
              border-radius:25px;font-size:.9rem;transition:var(--trans)}
.search-input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(90,85,210,.1)}
.brand-select{padding:.8rem 1rem;border:2px solid var(--border);border-radius:25px;
              background:#fff;color:var(--gray);font-size:.9rem;min-width:150px}

/* ========= PRODUCTS GRID ========= */
.products-section{padding:2rem 5%;background:var(--light)}
.products-container{max-width:1200px;margin:0 auto}
.products-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem}
.products-count{color:var(--gray);font-size:.9rem}
.sort-select{padding:.5rem 1rem;border:1px solid var(--border);border-radius:20px;background:#fff}
.products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem}
.product-card{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);
              overflow:hidden;transition:var(--trans);border:1px solid var(--border);
              display:flex;flex-direction:column;height:100%}
.product-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-hover)}
.product-image{position:relative;padding-top:75%;background:#f8f9fa;overflow:hidden}
.product-image img,.product-image .swiper{position:absolute;inset:0;width:100%;height:100%}
.product-image img{object-fit:cover;transition:var(--trans)}
.product-card:hover .product-image img{transform:scale(1.05)}
.product-badge{position:absolute;top:10px;right:10px;background:var(--accent);
               color:#fff;padding:.3rem .6rem;border-radius:15px;font-size:.7rem;font-weight:600}
.product-info{padding:1.2rem;display:flex;flex-direction:column;flex-grow:1}
.product-category{color:var(--primary);font-size:.8rem;font-weight:600;margin-bottom:.3rem}
.product-title{font-size:1.1rem;font-weight:600;color:#1A202C;margin-bottom:.5rem;
               display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.product-price{font-size:1.3rem;font-weight:700;color:var(--secondary);margin-bottom:1rem}
.product-options{margin-bottom:1rem}
.option-label{font-size:.85rem;color:var(--gray);margin-bottom:.5rem;display:block;font-weight:500}
.color-dots{display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.8rem}
.color-dot{width:28px;height:28px;border-radius:50%;border:3px solid #fff;cursor:pointer;
           box-shadow:0 2px 4px rgba(0,0,0,.1);position:relative;transition:var(--trans)}
.color-dot.selected{border-color:var(--primary);transform:scale(1.1)}
.color-dot input{opacity:0;position:absolute}
.sizes-list{display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.8rem}
.size-option{padding:.4rem .8rem;border:2px solid var(--border);border-radius:6px;
             font-size:.85rem;cursor:pointer;transition:var(--trans);background:#fff}
.size-option.selected{background:var(--primary);color:#fff;border-color:var(--primary)}
.size-option input{opacity:0;position:absolute}
.add-cart-btn{margin-top:auto;padding:.8rem;border:none;border-radius:8px;
              background:linear-gradient(135deg,var(--accent),#FF8A6B);color:#fff;
              font-weight:600;cursor:pointer;display:flex;align-items:center;
              gap:.5rem;justify-content:center;transition:var(--trans)}
.add-cart-btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(255,123,84,.3)}
.add-cart-btn:disabled{opacity:.6;cursor:not-allowed;transform:none}

/* ========= EMPTY STATE ========= */
.no-products{text-align:center;padding:4rem 2rem;color:var(--gray)}
.no-products i{font-size:4rem;margin-bottom:1rem;opacity:.3}
.no-products h3{font-size:1.5rem;margin-bottom:.5rem}
.no-products p{font-size:1rem}

/* ========= TOAST ========= */
#toast{position:fixed;bottom:25px;left:50%;transform:translateX(-50%);
       background:#2D3748;color:#fff;padding:1rem 1.5rem;border-radius:8px;
       opacity:0;pointer-events:none;transition:var(--trans);z-index:1001;
       box-shadow:var(--shadow-hover)}
#toast.show{opacity:1;pointer-events:auto}

/* ========= RESPONSIVE ========= */
@media(max-width:768px){
 nav ul{display:none;position:absolute;top:100%;right:0;background:#fff;color:#333;
        flex-direction:column;padding:1rem;border-radius:var(--radius);box-shadow:var(--shadow);min-width:200px}
 nav ul.open{display:flex}
 nav ul a{color:#333;padding:.5rem 1rem}
 .hamburger{display:block}
 .store-content{flex-direction:column;text-align:center;gap:1.5rem}
 .store-logo{width:100px;height:100px}
 .store-info h1{font-size:2rem}
 .store-stats{justify-content:center}
 .search-filters{flex-direction:column}
 .search-input{min-width:auto}
 .products-grid{grid-template-columns:repeat(auto-fill,minmax(250px,1fr))}
 .products-header{flex-direction:column;gap:1rem;align-items:stretch}
}
@media(max-width:480px){
 .store-logo{width:80px;height:80px}
 .store-info h1{font-size:1.7rem}
 .products-grid{grid-template-columns:1fr}
 .categories-scroll{gap:.3rem}
 .category-btn{padding:.5rem 1rem;font-size:.8rem}
}

/* ========= LOADING ========= */
.loading{opacity:0;animation:fadeInUp .6s ease forwards}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.product-card:nth-child(1){animation-delay:.1s}
.product-card:nth-child(2){animation-delay:.2s}
.product-card:nth-child(3){animation-delay:.3s}
.product-card:nth-child(4){animation-delay:.4s}
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
      <li><a href="/">الرئيسية</a></li> <li><a href="/stores">المتاجر</a></li> <li><a href="/cart">السلة</a></li> </ul>
  </nav>

  <a href="/cart" class="cart-icon" aria-label="السلة"> <i class="fas fa-shopping-cart"></i>
    <span id="cart-count"><?= $cart_count ?></span>
  </a>
</header>

<section class="store-banner">
  <div class="store-content">
    <div class="store-logo">
      <?php $logo=fix_url($store['logo']??''); echo $logo?'<img src="'.htmlspecialchars($logo).'" alt="'.htmlspecialchars($store['name']).'">' : '<i class="fas fa-store"></i>';?>
    </div>
    <div class="store-info">
      <h1><?=htmlspecialchars($store['name'])?></h1>
      <p><?=htmlspecialchars($store['description']??'متجر متميز يقدم منتجات عالية الجودة')?></p>
      
      <div class="store-stats">
        <div class="stat-item">
          <span class="number"><?= count($products) ?></span>
          <span class="label">منتج</span>
        </div>
        <div class="stat-item">
          <span class="number"><?= $rating['count'] ?></span>
          <span class="label">تقييم</span>
        </div>
        <div class="stat-item">
          <span class="number"><?= count($categories) ?></span>
          <span class="label">فئة</span>
        </div>
      </div>

      <div class="rating-section">
        <div class="rating-display">
          <div class="rating-stars">
            <?php for($i=1;$i<=5;$i++): ?>
              <i class="fas fa-star<?= $i<=$rating['avg']?' filled':'' ?>"></i>
            <?php endfor; ?>
          </div>
          <span class="rating-text"><?= $rating['avg'] ?> من 5 (<?= $rating['count'] ?> تقييم)</span>
        </div>
        
        <?php if($user_id): ?>
          <form class="rating-form" method="post" action="/rate_store"> <?= csrf_field() ?>
            <input type="hidden" name="store_id" value="<?= $store_id ?>">
            <input type="hidden" name="rating" id="rating-input">
            <div class="rating-stars" id="rating-stars" data-user="<?= $rating['user'] ?>">
              <?php for($i=1;$i<=5;$i++): ?>
                <span class="star<?= $i<=$rating['user']?' filled':'' ?>" data-star="<?= $i ?>">
                  <i class="fas fa-star"></i>
                </span>
              <?php endfor; ?>
            </div>
            <button type="submit" class="rate-btn">تقييم</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section class="filters-section">
  <div class="filters-container">
    <div class="categories-filter">
      <div class="categories-scroll">
        <button class="category-btn active" data-cat="الكل">جميع المنتجات</button>
        <?php foreach($categories as $c): ?>
          <button class="category-btn" data-cat="<?=htmlspecialchars($c)?>"><?=htmlspecialchars($c)?></button>
        <?php endforeach; ?>
      </div>
    </div>
    
    <div class="search-filters">
      <input type="text" class="search-input" id="products-search" placeholder="ابحث عن منتج...">
      <select class="brand-select" id="brand-filter">
        <option value="">جميع الماركات</option>
        <?php foreach($brands as $b): ?>
          <option value="<?=htmlspecialchars($b)?>"><?=htmlspecialchars($b)?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</section>

<section class="products-section">
  <div class="products-container">
    <div class="products-header">
      <div class="products-count" id="products-count">
        عرض <?= count($products) ?> منتج
      </div>
      <select class="sort-select" id="sort-select">
        <option value="newest">الأحدث</option>
        <option value="price-low">السعر: من الأقل للأعلى</option>
        <option value="price-high">السعر: من الأعلى للأقل</option>
        <option value="name">الاسم</option>
      </select>
    </div>

    <div class="products-grid" id="products-container">
      <?php if(!$products): ?>
        <div class="no-products">
          <i class="fas fa-box-open"></i>
          <h3>لا توجد منتجات</h3>
          <p>لم يتم إضافة أي منتجات لهذا المتجر بعد</p>
        </div>
      <?php else: foreach($products as $p):
        $pid=$p['id']; $gallery=[];
        if($p['image']) $gallery[]=fix_url($p['image']);
        foreach($product_images[$pid]??[] as $im){
          $u=fix_url($im); 
          if(!in_array($u,$gallery)) $gallery[]=$u;
        }
        if(!$gallery) $gallery[]='https://via.placeholder.com/300x225?text=صورة+المنتج';
      ?>
        <div class="product-card loading" data-cat="<?=htmlspecialchars($p['category'])?>" 
             data-brand="<?=htmlspecialchars($p['brand'])?>" data-title="<?=htmlspecialchars($p['name'])?>"
             data-price="<?=$p['price']?>">
          
          <div class="product-image">
            <?php if(count($gallery)>1): ?>
              <div class="swiper" id="sw<?= $pid ?>">
                <div class="swiper-wrapper">
                  <?php foreach($gallery as $g): ?>
                    <div class="swiper-slide">
                      <img src="<?= htmlspecialchars($g) ?>" alt="<?=htmlspecialchars($p['name'])?>" loading="lazy">
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
              </div>
            <?php else: ?>
              <img src="<?= htmlspecialchars($gallery[0]) ?>" alt="<?=htmlspecialchars($p['name'])?>" loading="lazy">
            <?php endif; ?>
            
            <?php if($p['brand']): ?>
              <div class="product-badge"><?=htmlspecialchars($p['brand'])?></div>
            <?php endif; ?>
          </div>

          <div class="product-info">
            <?php if($p['category']): ?>
              <div class="product-category"><?=htmlspecialchars($p['category'])?></div>
            <?php endif; ?>
            
            <h3 class="product-title"><?=htmlspecialchars($p['name'])?></h3>
            <div class="product-price"><?=number_format($p['price'],0)?> د.ع</div>

            <?php if(!empty($product_colors[$pid])): ?>
              <div class="product-options">
                <span class="option-label">اختر اللون:</span>
                <div class="color-dots">
                  <?php foreach($product_colors[$pid] as $i=>$c): ?>
                    <label class="color-dot<?= !$i?' selected':'' ?>" style="background:<?=htmlspecialchars($c['color_code'])?>">
                      <input type="radio" name="color_id_<?= $pid ?>" value="<?= $c['id'] ?>" <?= !$i?'checked':'' ?>>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if(!empty($product_sizes[$pid])): ?>
              <div class="product-options">
                <span class="option-label">اختر المقاس:</span>
                <div class="sizes-list">
                  <?php foreach($product_sizes[$pid] as $i=>$s): ?>
                    <label class="size-option<?= !$i?' selected':'' ?>">
                      <input type="radio" name="size_id_<?= $pid ?>" value="<?= $s['id'] ?>" <?= !$i?'checked':'' ?>>
                      <?=htmlspecialchars($s['size_value'])?>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <form class="add-to-cart-form" method="post" action="/add_to_cart"> <?= csrf_field() ?>
              <input type="hidden" name="product_id" value="<?= $pid ?>">
              <button class="add-cart-btn" type="submit">
                <i class="fas fa-cart-plus"></i>
                أضف للسلة
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</section>

<div id="toast"></div>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
/* Hamburger menu */
const hamburger=document.querySelector('.hamburger');
const navList=document.querySelector('nav ul');
hamburger.addEventListener('click',()=>{
  navList.classList.toggle('open');
});

/* Product filtering and search */
function filterProducts(){
  const cat=document.querySelector('.category-btn.active').dataset.cat;
  const txt=document.getElementById('products-search').value.toLowerCase();
  const brand=document.getElementById('brand-filter').value.toLowerCase();
  const sort=document.getElementById('sort-select').value;
  
  let products=Array.from(document.querySelectorAll('.product-card'));
  let visibleCount=0;
  
  // Filter
  products.forEach(card=>{
    const matchesCat=cat==='الكل'||card.dataset.cat===cat;
    const matchesText=card.dataset.title.toLowerCase().includes(txt);
    const matchesBrand=!brand||card.dataset.brand.toLowerCase()===brand;
    const isVisible=matchesCat&&matchesText&&matchesBrand;
    
    card.style.display=isVisible?'flex':'none';
    if(isVisible) visibleCount++;
  });
  
  // Sort visible products
  const visibleProducts=products.filter(p=>p.style.display!=='none');
  visibleProducts.sort((a,b)=>{
    switch(sort){
      case 'price-low':
        return parseFloat(a.dataset.price)-parseFloat(b.dataset.price);
      case 'price-high':
        return parseFloat(b.dataset.price)-parseFloat(a.dataset.price);
      case 'name':
        return a.dataset.title.localeCompare(b.dataset.title);
      default:
        return 0;
    }
  });
  
  // Reorder in DOM
  const container=document.getElementById('products-container');
  visibleProducts.forEach(product=>{
    container.appendChild(product);
  });
  
  // Update count
  document.getElementById('products-count').textContent=`عرض ${visibleCount} منتج`;
  
  // Show no results message
  const noResults=document.querySelector('.no-products');
  if(noResults) noResults.remove();
  
  if(visibleCount===0){
    const div=document.createElement('div');
    div.className='no-products';
    div.innerHTML=`
      <i class="fas fa-search"></i>
      <h3>لا توجد نتائج</h3>
      <p>لم نجد منتجات تطابق بحثك</p>
    `;
    container.appendChild(div);
  }
}

/* Event listeners */
document.querySelectorAll('.category-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    document.querySelectorAll('.category-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    filterProducts();
  });
});

document.getElementById('products-search').addEventListener('input',filterProducts);
document.getElementById('brand-filter').addEventListener('change',filterProducts);
document.getElementById('sort-select').addEventListener('change',filterProducts);

/* Product options selection */
document.addEventListener('change',e=>{
  if(e.target.closest('.color-dots')){
    e.target.closest('.color-dots').querySelectorAll('.color-dot').forEach(d=>d.classList.remove('selected'));
    e.target.parentNode.classList.add('selected');
  }
  if(e.target.closest('.sizes-list')){
    e.target.closest('.sizes-list').querySelectorAll('.size-option').forEach(s=>s.classList.remove('selected'));
    e.target.parentNode.classList.add('selected');
  }
});

/* Add to cart */
document.querySelectorAll('.add-to-cart-form').forEach(form=>{
  form.addEventListener('submit',e=>{
    e.preventDefault();
    const card=form.closest('.product-card');
    const data=new FormData(form);
    
    const colorInput=card.querySelector('.color-dot.selected input');
    if(colorInput) data.append('color_id',colorInput.value);
    
    const sizeInput=card.querySelector('.size-option.selected input');
    if(sizeInput) data.append('size_id',sizeInput.value);

    const btn=form.querySelector('.add-cart-btn');
    const originalText=btn.innerHTML;
    btn.disabled=true;
    btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> جاري الإضافة...';
    
    fetch(form.action,{method:'POST',body:data})
      .then(r=>r.json())
      .then(d=>{
        if(d.success){
          showToast('تمت إضافة المنتج للسلة بنجاح!');
          document.getElementById('cart-count').textContent=d.count;
        }else{
          showToast(d.msg||'حدث خطأ أثناء إضافة المنتج');
        }
      })
      .catch(e=>{
        console.error(e);
        showToast('حدث خطأ في الاتصال');
      })
      .finally(()=>{
        btn.innerHTML=originalText;
        btn.disabled=false;
      });
  });
});

/* Store rating */
document.querySelectorAll('.rating-form .star').forEach(star=>{
  star.addEventListener('click',()=>{
    const rating=star.dataset.star;
    document.getElementById('rating-input').value=rating;
    
    document.querySelectorAll('.rating-form .star').forEach(s=>{
      s.classList.toggle('filled',s.dataset.star<=rating);
    });
  });
});

document.querySelector('.rating-form')?.addEventListener('submit',e=>{
  e.preventDefault();
  const form=e.target;
  const data=new FormData(form);
  
  fetch(form.action,{method:'POST',body:data})
    .then(r=>r.json())
    .then(d=>{
      if(d.success){
        showToast('تم تقييم المتجر بنجاح!');
        // Update rating display
        document.querySelector('.rating-text').textContent=`${d.avg} من 5 (${d.count} تقييم)`;
      }else{
        showToast(d.msg||'حدث خطأ أثناء التقييم');
      }
    })
    .catch(e=>{
      console.error(e);
      showToast('حدث خطأ في الاتصال');
    });
});

/* Toast notification */
function showToast(message){
  const toast=document.getElementById('toast');
  toast.textContent=message;
  toast.classList.add('show');
  setTimeout(()=>toast.classList.remove('show'),3000);
}

/* Initialize Swiper for product galleries */
window.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('.swiper').forEach(swiper=>{
    new Swiper(swiper,{
      loop:true,
      pagination:{
        el:swiper.querySelector('.swiper-pagination'),
        clickable:true
      },
      navigation:{
        nextEl:swiper.querySelector('.swiper-button-next'),
        prevEl:swiper.querySelector('.swiper-button-prev')
      },
      rtl:true
    });
  });
  
  // Initialize loading animations
  const observer=new IntersectionObserver((entries)=>{
    entries.forEach(entry=>{
      if(entry.isIntersecting){
        entry.target.style.opacity='1';
        entry.target.style.transform='translateY(0)';
      }
    });
  },{threshold:0.1});

  document.querySelectorAll('.loading').forEach(el=>{
    observer.observe(el);
  });
});
</script>
</body>
</html>
