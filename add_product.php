<?php
/**  add_product.php  |  إصدار آمن ومُحسَّن **/

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();
require 'db.php';

/* ============ التحقّق من جلسة البائع ============ */
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'vendor') {
    header('Location: login.php');
    exit;
}
$vendor_id = (int)$_SESSION['user_id'];

/* ============ استرجاع المتجر ============ */
$store = $conn->query("SELECT * FROM stores WHERE owner_id = $vendor_id LIMIT 1")->fetch_assoc();
if (!$store) {
    die('<h3 style="color:#d00;text-align:center;margin:40px">لم يتم العثور على متجر لهذا البائع، يرجى إنشاء متجر أولاً.</h3>');
}
$store_id   = (int)$store['id'];
$store_name = $store['name'] ?? '';

/* ============ إنشاء الرمـز CSRF ============ */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ============ ثوابت رفع الملفات ============ */
const MAX_IMG_SIZE   = 5 * 1024 * 1024;                 // 5MB
$allowed_mime = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
$uploads_dir  = __DIR__.'/uploads';
if (!is_dir($uploads_dir)) mkdir($uploads_dir,0775,true);

$msg = $error = '';

/* ============ معالجة النموذج ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* --- فحص التوكن CSRF --- */
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf'])) {
        $error = 'فشل التحقق الأمني، أعد تحميل الصفحة وحاول مجدداً.';
    }

    /* --- التقاط وتدقيق الحقول الأساسية --- */
    $name      = trim($_POST['name']      ?? '');
    $category  = trim($_POST['category']  ?? '');
    $brand     = trim($_POST['brand']     ?? '');
    $price     = (float)($_POST['price']  ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!$error && (!$name || !$category || !$brand || $price<=0)) {
        $error = 'يرجى تعبئة جميع الحقول المطلوبة وإدخال سعر صالح.';
    }

    /* --- رفع ومعالجة الصور --- */
    $images_urls = [];
    $image     = null;

    if (!$error && !empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {

            /* تهيئة بيانات الملف */
            $err   = $_FILES['images']['error'][$i];
            $size  = $_FILES['images']['size'][$i];
            $nameF = basename($_FILES['images']['name'][$i]);
            $mime  = mime_content_type($tmp);

            /* تحقّقات */
            if ($err !== UPLOAD_ERR_OK)          { $error = 'فشل رفع إحدى الصور.'; break; }
            if ($size > MAX_IMG_SIZE)            { $error = 'حجم الصورة يتجاوز 5MB.'; break; }
            if (!isset($allowed_mime[$mime]))    { $error = 'امتداد صورة غير مسموح.'; break; }

            /* توليد اسم آمن وفريد للملف */
            $ext   = $allowed_mime[$mime];
            $hash  = bin2hex(random_bytes(8));
            $fname = time()."_{$hash}.{$ext}";
            $dest  = "{$uploads_dir}/{$fname}";

            if (!move_uploaded_file($tmp,$dest)) { $error = 'خطأ أثناء حفظ الصورة.'; break; }

            $images_urls[] = "uploads/{$fname}";
        }
        /* الصورة الرئيسية هي الأولى */
        if (!$error && $images_urls) $image = $images_urls[0];
    }

    /* --- إدخال البيانات في قاعدة البيانات --- */
    if (!$error) {

        /* المنتج */
        $stmt = $conn->prepare("
            INSERT INTO products
            (store_id,name,category,brand,price,image,is_active,created_at)
            VALUES (?,?,?,?,?,?,?,NOW())
        ");
        $stmt->bind_param(
            'isssdsi',
            $store_id,
            $name,
            $category,
            $brand,
            $price,
            $image,
            $is_active
        );

        if (!$stmt->execute()) {
            $error = 'حدث خطأ أثناء إضافة المنتج: '.$stmt->error;
        } else {

            $product_id = $stmt->insert_id;

            /* صور إضافية */
            if ($images_urls) {
                $pi_stmt = $conn->prepare("INSERT INTO product_images (product_id,image) VALUES (?,?)");
                foreach ($images_urls as $url) {
                    $pi_stmt->bind_param('is',$product_id,$url);
                    $pi_stmt->execute();
                }
            }

            /* الألوان */
            if (!empty($_POST['color_name'])) {
                $cn  = $_POST['color_name'];
                $cc  = $_POST['color_code'] ?? [];
                $cl_stmt = $conn->prepare("INSERT INTO product_colors (product_id,color_name,color_code) VALUES (?,?,?)");
                for ($i=0;$i<count($cn);$i++) {
                    $cname = trim($cn[$i]);
                    $ccode = trim($cc[$i] ?? '');
                    if ($cname && $ccode) {
                        $cl_stmt->bind_param('iss',$product_id,$cname,$ccode);
                        $cl_stmt->execute();
                    }
                }
            }

            /* القياسات */
            if (!empty($_POST['size_value'])) {
                $sv = $_POST['size_value'];
                $st = $_POST['size_type'] ?? [];
                $sz_stmt = $conn->prepare("INSERT INTO product_sizes (product_id,size_type,size_value) VALUES (?,?,?)");
                for ($i=0;$i<count($sv);$i++) {
                    $val = trim($sv[$i]);
                    $typ = in_array(($st[$i]??''),['number','letter']) ? $st[$i] : 'number';
                    if ($val) {
                        $sz_stmt->bind_param('iss',$product_id,$typ,$val);
                        $sz_stmt->execute();
                    }
                }
            }

            $msg = '✔ تمت إضافة المنتج بنجاح!';
        }
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>إضافة منتج – لوحة <?= htmlspecialchars($store_name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body{margin:0;background:#f5f6ff;font-family:'Tajawal',sans-serif}
        .box{max-width:460px;background:#fff;margin:65px auto 40px;padding:2.3rem 1.6rem;border-radius:12px;box-shadow:0 6px 38px #6c63ff21}
        h2{color:#6c63ff;margin-bottom:1.3rem;font-size:1.45rem}
        label{font-weight:600;margin-bottom:6px;display:block}
        input,select{width:100%;margin-bottom:0.9rem;padding:0.7rem;border:1px solid #dcdde6;border-radius:7px;font-size:0.95rem}
        input[type="file"]{padding:0.4rem}
        .msg{color:#129c40;font-weight:700;margin-bottom:1rem}
        .error{color:#d63a3a;font-weight:700;margin-bottom:1rem}
        .area{background:#f8f7ff;border-radius:10px;padding:1rem 0.8rem;margin-bottom:1.2rem}
        .group{display:flex;align-items:center;gap:7px;margin-bottom:8px}
        .group input[type="color"]{min-width:46px;padding:0}
        .group select{min-width:78px}
        .rm-btn{background:#eee;border:none;color:#d22;font-weight:700;border-radius:6px;padding:0.25rem 0.7rem;cursor:pointer}
        .add-btn,.save-btn,.back-btn{border:none;border-radius:7px;padding:0.7rem 1.7rem;font-weight:700;cursor:pointer;font-size:0.96rem}
        .add-btn{background:#ff6584;color:#fff;margin-top:-4px;margin-bottom:1.1rem}
        .save-btn{background:#6c63ff;color:#fff;margin-left:6px}
        .back-btn{background:#888;color:#fff;text-decoration:none}
        @media(max-width:500px){.box{margin:40px 12px}}
    </style>
</head>
<body>
<div class="box">
    <h2>إضافة منتج جديد</h2>

    <?php if($msg):   echo '<div class="msg">'.htmlspecialchars($msg).'</div>';  ?>
    <?php elseif($error): echo '<div class="error">'.htmlspecialchars($error).'</div>'; endif; ?>

    <form method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <label>اسم المنتج</label>
        <input required name="name" type="text" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">

        <label>الصنف</label>
        <input required name="category" type="text" value="<?= htmlspecialchars($_POST['category'] ?? '') ?>">

        <label>الماركة</label>
        <input required name="brand" type="text" placeholder="مثال: Maybelline" value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>">

        <label>السعر (د.ع)</label>
        <input required name="price" type="number" min="1" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">

        <label>صور المنتج (يمكن اختيار عدة صور)</label>
        <input name="images[]" type="file" accept="image/*" multiple>

        <label><input type="checkbox" name="is_active" <?= isset($_POST['is_active'])?'checked':'' ?>> متاح للبيع</label>

        <hr style="margin:1.3rem 0">

        <!-- ألوان -->
        <div class="area" id="colors-area">
            <label>ألوان المنتج (اختياري)</label>
            <div class="group">
                <input type="text" name="color_name[]" placeholder="اسم اللون">
                <input type="color" name="color_code[]" value="#ff0000">
                <button type="button" class="rm-btn" onclick="this.parentElement.remove()">✕</button>
            </div>
        </div>
        <button type="button" class="add-btn" onclick="addColor()">إضافة لون</button>

        <!-- قياسات -->
        <div class="area" id="sizes-area">
            <label>قياسات المنتج (اختياري)</label>
            <div class="group">
                <select name="size_type[]"><option value="number">رقم</option><option value="letter">حرف</option></select>
                <input type="text" name="size_value[]" placeholder="القياس">
                <button type="button" class="rm-btn" onclick="this.parentElement.remove()">✕</button>
            </div>
        </div>
        <button type="button" class="add-btn" onclick="addSize()">إضافة قياس</button>

        <br><br>
        <button class="save-btn" type="submit">حفظ المنتج</button>
        <a href="vendor_dashboard.php" class="back-btn">عودة</a>
    </form>
</div>

<script>
/* ديناميكية الألوان */
function addColor(){
    document.getElementById('colors-area').insertAdjacentHTML('beforeend',`
        <div class="group">
            <input type="text" name="color_name[]" placeholder="اسم اللون">
            <input type="color" name="color_code[]" value="#ffb6c1">
            <button type="button" class="rm-btn" onclick="this.parentElement.remove()">✕</button>
        </div>
    `);
}
/* ديناميكية القياسات */
function addSize(){
    document.getElementById('sizes-area').insertAdjacentHTML('beforeend',`
        <div class="group">
            <select name="size_type[]">
                <option value="number">رقم</option>
                <option value="letter">حرف</option>
            </select>
            <input type="text" name="size_value[]" placeholder="القياس">
            <button type="button" class="rm-btn" onclick="this.parentElement.remove()">✕</button>
        </div>
    `);
}
</script>
</body>
</html>
