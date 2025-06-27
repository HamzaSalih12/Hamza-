<?php
/**  delete_product.php  |  نسخة مُصحَّحة **/

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();
require 'db.php';

/* ===== التحقّق من جلسة البائع ===== */
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'vendor') {
    header('Location: login.php');
    exit;
}
$vendor_id = (int)$_SESSION['user_id'];

/* ===== استرجاع المتجر المملوك ===== */
$store = $conn->query("SELECT id FROM stores WHERE owner_id = $vendor_id LIMIT 1")->fetch_assoc();
if (!$store) { http_response_code(403); exit('لا تمتلك متجراً صالحاً.'); }
$store_id = (int)$store['id'];

/* ===== فحص المعرّف و CSRF ===== */
if (empty($_GET['id']) || !ctype_digit($_GET['id']))      exit('رقم منتج غير صالح.');
if (empty($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf']))
    exit('فشل التحقق الأمني، حدّث الصفحة وأعد المحاولة.');

$product_id = (int)$_GET['id'];

/* ===== التحقّق من ملكيّة المنتج واسترجاع صوره ===== */
$stmt = $conn->prepare("
    SELECT p.image,
           (SELECT GROUP_CONCAT(image) FROM product_images WHERE product_id = p.id) AS extra_imgs
    FROM   products p
    WHERE  p.id = ?  AND p.store_id = ?
    LIMIT  1
");
$stmt->bind_param('ii', $product_id, $store_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res->num_rows) exit('المنتج غير موجود أو لا تملك صلاحية حذفه.');
$prodData = $res->fetch_assoc();

/* ===== حذف الصور من جداولها ===== */
$stmt = $conn->prepare("DELETE FROM product_images WHERE product_id = ?");
$stmt->bind_param('i', $product_id);
$stmt->execute();

$stmt = $conn->prepare("DELETE FROM product_colors WHERE product_id = ?");
$stmt->bind_param('i', $product_id);
$stmt->execute();

$stmt = $conn->prepare("DELETE FROM product_sizes WHERE product_id = ?");
$stmt->bind_param('i', $product_id);
$stmt->execute();

/* ===== حذف المنتج نفسه ===== */
$stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND store_id = ? LIMIT 1");
$stmt->bind_param('ii', $product_id, $store_id);
$stmt->execute();

/* ===== إزالة ملفات الصور من الخادوم ===== */
$allImgs = array_filter(array_merge(
    [$prodData['image']],
    explode(',', $prodData['extra_imgs'] ?? '')
));
foreach ($allImgs as $rel) {
    $path = __DIR__ . '/' . ltrim($rel, '/');
    if (is_file($path)) @unlink($path);
}

/* ===== العودة مع رسالة نجاح ===== */
header('Location: vendor_dashboard.php?msg=deleted');
exit;
?>
