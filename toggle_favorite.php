<?php
session_start();
require 'db.php';
$user_id = $_SESSION['user_id'] ?? 0;
$store_id = intval($_POST['store_id'] ?? 0);
if (!$user_id || !$store_id) {
    echo json_encode(['success'=>false, 'msg'=>'غير مصرح']);
    exit;
}
$is_fav = false;
$q = $conn->prepare("SELECT 1 FROM store_favorites WHERE user_id=? AND store_id=?");
$q->bind_param("ii", $user_id, $store_id);
$q->execute(); $q->store_result();
if ($q->num_rows > 0) {
    // احذف من المفضلة
    $del = $conn->prepare("DELETE FROM store_favorites WHERE user_id=? AND store_id=?");
    $del->bind_param("ii", $user_id, $store_id);
    $del->execute();
    $msg = 'تمت الإزالة من المفضلة';
} else {
    // أضف للمفضلة
    $add = $conn->prepare("INSERT IGNORE INTO store_favorites (user_id, store_id) VALUES (?, ?)");
    $add->bind_param("ii", $user_id, $store_id);
    $add->execute();
    $is_fav = true;
    $msg = 'تمت الإضافة للمفضلة';
}
echo json_encode(['success'=>true, 'fav'=>$is_fav, 'msg'=>$msg]);
