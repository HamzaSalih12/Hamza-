<?php
/*  تحديث حالة الطلب – إصدار آمن للبائع  */

session_start();
require 'db.php';

/* إجابات JSON دائمًا */
header('Content-Type: application/json');

/* ---- 1) التحقّق من جلسة البائع ---- */
if (
    !isset($_SESSION['user_id'], $_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'vendor'
) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

/* ---- 2) يُسمح فقط بطلب POST ---- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'invalid_method']);
    exit;
}

/* ---- 3) التحقّق من CSRF ---- */
if (
    empty($_POST['csrf']) ||
    $_POST['csrf'] !== ($_SESSION['csrf_token'] ?? '')
) {
    http_response_code(419);                     // 419-unknown status (CSRF)
    echo json_encode(['error' => 'csrf']);
    exit;
}

/* ---- 4) التحقّق من المُدخلات ---- */
$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$status   = $_POST['status'] ?? '';

$allowed  = ['pending', 'delivered', 'cancelled'];
if (!$order_id || !in_array($status, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_input']);
    exit;
}

/* ---- 5) جلب store_id الخاص بالبائع ---- */
$vendor_id = (int)$_SESSION['user_id'];

$getStore = $conn->prepare(
    "SELECT id FROM stores WHERE owner_id = ? LIMIT 1"
);
$getStore->bind_param('i', $vendor_id);
$getStore->execute();
$store = $getStore->get_result()->fetch_assoc();

if (!$store) {
    http_response_code(403);
    echo json_encode(['error' => 'no_store']);
    exit;
}
$store_id = (int)$store['id'];

/* ---- 6) التحقّق أن الطلب يخصّ هذا المتجر فعليًا ---- */
$check = $conn->prepare(
    "SELECT 1 FROM order_items
     WHERE order_id = ? AND store_id = ? LIMIT 1"
);
$check->bind_param('ii', $order_id, $store_id);
$check->execute();
if (!$check->get_result()->num_rows) {
    http_response_code(403);
    echo json_encode(['error' => 'not_allowed']);
    exit;
}

/* ---- 7) تحديث الحالة باستخدام Prepared Statement ---- */
$upd = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
$upd->bind_param('si', $status, $order_id);

if ($upd->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
