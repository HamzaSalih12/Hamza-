<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require 'db.php';

if(empty($_POST['customer_name']) || empty($_POST['customer_phone']) || empty($_POST['customer_address'])) {
    die('الرجاء ملء جميع الحقول');
}

$customer_name    = $_POST['customer_name'];
$customer_phone   = $_POST['customer_phone'];
$customer_address = $_POST['customer_address'];
$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    die('سلة المشتريات فارغة');
}

// سجل الطلب
$stmt = $conn->prepare("INSERT INTO orders (customer_name, customer_phone, customer_address, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
$stmt->bind_param("sss", $customer_name, $customer_phone, $customer_address);
$stmt->execute();

if($stmt->affected_rows > 0){
    $order_id = $conn->insert_id;

    // أدخل المنتجات مع اللون والقياس (size_id)
    $ins = $conn->prepare("INSERT INTO order_items (order_id, product_id, store_id, quantity, price, subtotal, color_name, color_code, size_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach($cart as $item){
        $price = $item['price'];
        $qty   = $item['qty'];
        $subtotal = $price * $qty;
        $color_name = isset($item['color_name']) ? $item['color_name'] : null;
        $color_code = isset($item['color_code']) ? $item['color_code'] : null;
        $size_id    = isset($item['size_id']) ? intval($item['size_id']) : null;

        $ins->bind_param("iiiiidssi", $order_id, $item['product_id'], $item['store_id'], $qty, $price, $subtotal, $color_name, $color_code, $size_id);
        $ins->execute();
    }
    unset($_SESSION['cart']);
    echo "<script>alert('تم إرسال الطلب بنجاح! سنتواصل معك قريباً');window.location='index.php';</script>";
} else {
    echo "حدث خطأ أثناء حفظ الطلب";
}
?>
