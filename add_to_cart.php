<?php
session_start();
require 'db.php';
require_once 'csrf.php';
csrf_verify();

/* تحقق من مُعرّف المنتج */
if (!isset($_POST['product_id'])) {
    exit(json_encode(['success'=>false,'msg'=>'منتج غير معروف']));
}
$product_id = intval($_POST['product_id']);
$color_id   = isset($_POST['color_id']) ? intval($_POST['color_id']) : null;
$size_id    = isset($_POST['size_id'])  ? intval($_POST['size_id'])  : null;

/* جلب المنتج */
$stmt = $conn->prepare("
  SELECT p.id, p.store_id, p.name, p.price, p.image, s.name AS store_name
  FROM products p JOIN stores s ON p.store_id = s.id
  WHERE p.id = ?
");
$stmt->bind_param("i",$product_id); $stmt->execute();
$prod = $stmt->get_result()->fetch_assoc()
        ?: exit(json_encode(['success'=>false,'msg'=>'المنتج غير موجود']));

/* لون (اختياري) */
$color_name=$color_code=$color_img=null;
if($color_id){
  $c=$conn->prepare("SELECT color_name,color_code,img_url FROM product_colors WHERE id=? AND product_id=?");
  $c->bind_param("ii",$color_id,$product_id);$c->execute();
  if($row=$c->get_result()->fetch_assoc()){
      $color_name=$row['color_name'];$color_code=$row['color_code'];$color_img=$row['img_url'];
  }
}

/* مقاس (اختياري) */
$size_value=$size_type=null;
if($size_id){
  $s=$conn->prepare("SELECT size_value,size_type FROM product_sizes WHERE id=? AND product_id=?");
  $s->bind_param("ii",$size_id,$product_id);$s->execute();
  if($row=$s->get_result()->fetch_assoc()){
      $size_value=$row['size_value'];$size_type=$row['size_type'];
  }
}

/* تحديد الصورة المعروضة */
$img = $color_img ?: $prod['image'] ?: 'https://via.placeholder.com/250';

/* إضافة إلى السلة */
if(!isset($_SESSION['cart'])) $_SESSION['cart']=[];
$item=[
 'product_id'=>$prod['id'],'store_id'=>$prod['store_id'],'store_name'=>$prod['store_name'],
 'name'=>$prod['name'],'img'=>$img,'price'=>$prod['price'],'qty'=>1,
 'color_id'=>$color_id,'color_name'=>$color_name,'color_code'=>$color_code,
 'size_id'=>$size_id,'size_value'=>$size_value,'size_type'=>$size_type
];
$found=false;
foreach($_SESSION['cart'] as &$c){
  if($c['product_id']===$item['product_id'] && $c['color_id']===$item['color_id'] && $c['size_id']===$item['size_id']){
     $c['qty']++;$found=true;break;
  }
}
unset($c);
if(!$found) $_SESSION['cart'][]=$item;

echo json_encode(['success'=>true,'count'=>count($_SESSION['cart'])]);
