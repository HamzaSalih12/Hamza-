<?php
error_log('SESSION PATH => '.session_save_path());
session_start();
require 'db.php';
require_once 'csrf.php';
csrf_verify();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['pass']  ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name===''||$email===''||$pass===''){
        $error='كل الحقول مطلوبة';
    } else {
        // تأكد من عدم تكرار البريد
        $c=$conn->prepare("SELECT id FROM users WHERE email=?");
        $c->bind_param("s",$email);$c->execute();
        if($c->get_result()->num_rows){
            $error='البريد مستخدم مسبقاً';
        }else{
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $ins=$conn->prepare("INSERT INTO users(name,email,phone,password,role) VALUES (?,?,?,?, 'customer')");
            $ins->bind_param("ssss",$name,$email,$phone,$hash);
            $ins->execute();
            $_SESSION['user_id']=$ins->insert_id;
            $_SESSION['user_role']='customer';
            header('Location: index.php');exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><title>إنشاء حساب | سوق الناصرية</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
<style>
 body{background:#f5f6fa;font-family:'Tajawal',sans-serif}
 .box{max-width:400px;margin:70px auto;padding:2.4rem;background:#fff;border-radius:14px;
      box-shadow:0 7px 24px rgba(108,99,255,.09)}
 h2{text-align:center;color:#4D44DB;margin-bottom:1.6rem}
 input{width:100%;padding:.8rem;border:1px solid #ddd;border-radius:7px;font-size:1.01rem;margin-bottom:1rem}
 button{width:100%;background:#6C63FF;color:#fff;font-weight:700;border:none;border-radius:7px;padding:.9rem 0;font-size:1.1rem}
 .err{background:#ffe6e6;color:#ff2e2e;border-radius:7px;text-align:center;margin-bottom:1rem;padding:.7rem}
</style>
</head>
<body>
<div class="box">
 <h2>إنشاء حساب جديد</h2>
 <?php if(!empty($error)):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?>
 <form method="post">
   <?= csrf_field() ?>      <!-- ⟵ التوكن -->
   <input name="name"  placeholder="الاسم الكامل"         required>
   <input name="email" type="email"    placeholder="البريد الإلكتروني" required>
   <input name="phone" type="tel"      placeholder="رقم الهاتف اختياري">
   <input name="pass"  type="password" placeholder="كلمة المرور" required>
   <button type="submit">إنشاء الحساب</button>
 </form>
</div>
</body>
</html>
