<?php
session_start();
require 'db.php';
require_once 'csrf.php';   // ⟵ إضافة
csrf_verify();             // ⟵ تحقُّق فوري

// عند إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'الرجاء ملء جميع الحقول';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['user_role'] = $user['role'];

            header('Location: '.(
                $user['role']==='admin'  ? 'admin_dashboard.php'  :
                ($user['role']==='vendor'? 'vendor_dashboard.php' : 'index.php')
            ));
            exit;
        }
        $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تسجيل الدخول | سوق الناصرية الإلكتروني</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body{background:#f5f6fa;font-family:'Tajawal',sans-serif}
        .box{max-width:360px;margin:90px auto;padding:2.3rem;background:#fff;
             border-radius:14px;box-shadow:0 7px 26px rgba(108,99,255,.09)}
        h2{text-align:center;color:#4D44DB;margin-bottom:1.5rem}
        input{width:100%;padding:.8rem;border:1px solid #ddd;border-radius:7px;
              font-size:1.02rem;margin-bottom:1rem}
        button{width:100%;background:#FF6584;color:#fff;font-weight:700;border:none;
               border-radius:7px;padding:.85rem 0;font-size:1.13rem}
        .err{background:#ffe6e6;color:#ff2e2e;border-radius:7px;text-align:center;
             margin-bottom:1rem;padding:.7rem}
    </style>
</head>
<body>
<div class="box">
    <h2>تسجيل الدخول</h2>
    <?php if(!empty($error)):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?>
    <form method="post">
        <?= csrf_field() ?>   <!-- ⟵ التوكن الضروري -->
        <input type="email"    name="email"    placeholder="البريد الإلكتروني" required>
        <input type="password" name="password" placeholder="كلمة المرور"    required>
        <button type="submit">دخول</button>
    </form>
</div>
</body>
</html>
