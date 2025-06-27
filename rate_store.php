<?php
session_start();
require 'db.php';
require_once 'csrf.php';
csrf_verify();

$user_id = $_SESSION['user_id'] ?? 0;
$store_id= intval($_POST['store_id'] ?? 0);
$rating  = intval($_POST['rating']   ?? 0);

if(!$user_id || !$store_id || $rating<1 || $rating>5){
    exit(json_encode(['success'=>false,'msg'=>'بيانات غير صالحة']));
}

/* إدراج أو تحديث التقييم */
$chk = $conn->prepare("SELECT id FROM store_ratings WHERE store_id=? AND user_id=?");
$chk->bind_param("ii",$store_id,$user_id);$chk->execute();
if($chk->get_result()->num_rows){
   $up=$conn->prepare("UPDATE store_ratings SET rating=? WHERE store_id=? AND user_id=?");
   $up->bind_param("iii",$rating,$store_id,$user_id);$up->execute();
}else{
   $ins=$conn->prepare("INSERT INTO store_ratings(store_id,user_id,rating) VALUES(?,?,?)");
   $ins->bind_param("iii",$store_id,$user_id,$rating);$ins->execute();
}

/* إعادة المتوسط */
$avg = $conn->prepare("SELECT ROUND(AVG(rating),2) AS a,COUNT(*) AS c FROM store_ratings WHERE store_id=?");
$avg->bind_param("i",$store_id);$avg->execute();
$row=$avg->get_result()->fetch_assoc();

echo json_encode(['success'=>true,'avg'=>$row['a'],'count'=>$row['c']]);
