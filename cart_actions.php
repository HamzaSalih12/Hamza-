<?php
session_start();
header('Content-Type: application/json');

// جلب السلة من الجلسة
$cart = $_SESSION['cart'] ?? [];

$action = $_POST['action'] ?? '';

if ($action == 'remove') {
    // حذف منتج بناءً على (المنتج+اللون+القياس)
    $pid   = $_POST['pid'] ?? '';
    $color = $_POST['color'] ?? '';
    $size  = $_POST['size'] ?? '';

    $cart = array_filter($cart, function($item) use($pid, $color, $size) {
        return !(
            $item['product_id'] == $pid &&
            ($color === '' || (isset($item['color_id']) && $item['color_id'] == $color)) &&
            ($size === ''  || (isset($item['size_id'])  && $item['size_id']  == $size))
        );
    });
    $_SESSION['cart'] = array_values($cart);
    echo json_encode(['success'=>true]);
    exit;
}

if ($action == 'update_qty') {
    $pid   = $_POST['pid'] ?? '';
    $color = $_POST['color'] ?? '';
    $size  = $_POST['size'] ?? '';
    $qty   = intval($_POST['qty'] ?? 1);
    if ($qty < 1) $qty = 1;

    foreach ($cart as &$item) {
        if (
            $item['product_id'] == $pid &&
            ($color === '' || (isset($item['color_id']) && $item['color_id'] == $color)) &&
            ($size === ''  || (isset($item['size_id'])  && $item['size_id']  == $size))
        ) {
            $item['qty'] = $qty;
        }
    }
    unset($item);
    $_SESSION['cart'] = $cart;
    echo json_encode(['success'=>true]);
    exit;
}

echo json_encode(['success'=>false, 'message'=>'طلب غير صالح']);
exit;
