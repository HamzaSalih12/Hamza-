<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'vendor') {
    header('Location: login.php');
    exit;
}

$vendor_id = $_SESSION['user_id'];
$store = $conn->query("SELECT * FROM stores WHERE owner_id = $vendor_id LIMIT 1")->fetch_assoc();
$store_id = $store['id'];

if (!isset($_GET['id'])) {
    die("رقم المنتج غير موجود.");
}

$product_id = intval($_GET['id']);
$product = $conn->query("SELECT * FROM products WHERE id = $product_id AND store_id = $store_id")->fetch_assoc();
if (!$product) die("المنتج غير موجود أو لا تملك صلاحية تعديله.");

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $category = $conn->real_escape_string($_POST['category']);
    $price = (int)$_POST['price'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $image = $product['image'];

    if (!empty($_FILES['image']['name'])) {
        $img_name = time().'_'.basename($_FILES['image']['name']);
        $target = "uploads/".$img_name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $image = $target;
        }
    }

    $sql = "UPDATE products SET name='$name', category='$category', price=$price, image=".($image?"'$image'":"NULL").", is_active=$is_active WHERE id = $product_id AND store_id = $store_id";
    if ($conn->query($sql)) {
        $msg = "تم تعديل المنتج بنجاح!";
        // تحديث بيانات المنتج بعد التعديل
        $product = $conn->query("SELECT * FROM products WHERE id = $product_id AND store_id = $store_id")->fetch_assoc();
    } else {
        $msg = "حدث خطأ أثناء التعديل: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعديل المنتج</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {font-family:'Tajawal',sans-serif;background:#f7f7ff;padding:0;margin:0;}
        .form-box {background:#fff;padding:2.2rem 1.5rem;margin:60px auto;max-width:400px;border-radius:11px;box-shadow:0 7px 40px #6c63ff19;}
        h2 {color:#6c63ff;}
        label {font-weight:600;margin-bottom:8px;display:block;}
        input,select {width:100%;margin-bottom:1.1rem;padding:0.7rem 0.7rem;border-radius:7px;border:1px solid #ddd;}
        button {background:#6c63ff;color:#fff;border:none;padding:0.7rem 2rem;border-radius:7px;font-weight:600;font-size:1rem;cursor:pointer;}
        .msg {margin-bottom:1rem;font-weight:600;color:green;}
        .error {color:red;}
        img.prod-img {width:70px;display:block;margin-bottom:0.8rem;}
    </style>
</head>
<body>
    <div class="form-box">
        <h2>تعديل المنتج</h2>
        <?php if($msg): ?>
            <div class="msg"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <label>اسم المنتج:</label>
            <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
            <label>الصنف:</label>
            <input type="text" name="category" value="<?= htmlspecialchars($product['category']) ?>" required>
            <label>السعر (د.ع):</label>
            <input type="number" name="price" value="<?= htmlspecialchars($product['price']) ?>" required>
            <label>صورة المنتج:</label>
            <?php if($product['image']): ?>
                <img class="prod-img" src="<?= htmlspecialchars($product['image']) ?>">
            <?php endif; ?>
            <input type="file" name="image" accept="image/*">
            <label><input type="checkbox" name="is_active" <?= $product['is_active']?'checked':'' ?>> متاح للبيع</label>
            <button type="submit">تحديث</button>
        </form>
        <div style="margin-top:1rem;"><a href="vendor_dashboard.php">عودة للوحة البائع</a></div>
    </div>
</body>
</html>
