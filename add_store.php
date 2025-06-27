<?php
/*------------------------- الإعدادات العامة -------------------------*/
require_once 'db.php';
require_once 'helpers.php';        // require_admin() , csrf_token() , set_flash() , get_flash()
require_admin();

/*------------------------- إضافة متجر جديد -------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* 1) حماية CSRF */
    if (!verify_csrf($_POST['_token'] ?? '')) {
        set_flash('رمز CSRF غير صالح – أعد المحاولة', 'error');
        header('Location: add_store.php');
        exit;
    }

    /* 2) قراءة القيم */
    $name      = trim($_POST['name']      ?? '');
    $owner_id  = intval($_POST['owner_id'] ?? 0);
    $phone     = trim($_POST['phone']     ?? '');
    $logo      = trim($_POST['logo']      ?? '');
    $is_active = (isset($_POST['is_active'])) ? 1 : 0;

    /* 3) تحقق من القيم */
    if ($name === '' || $phone === '' || $owner_id < 1) {
        set_flash('يرجى تعبئة جميع الحقول الإلزامية', 'error');
        header('Location: add_store.php');
        exit;
    }

    /* 4) إدراج المتجر بـ Prepared-Statement */
    $stmt = $conn->prepare(
        "INSERT INTO stores (name, owner_id, phone, logo, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param("sissi", $name, $owner_id, $phone, $logo, $is_active);

    if ($stmt->execute()) {
        set_flash('تم إضافة المتجر بنجاح');
        header('Location: admin_dashboard.php?tab=stores');
        exit;
    }

    set_flash('حدث خطأ أثناء الإضافة: ' . $stmt->error, 'error');
    header('Location: add_store.php');
    exit;
}

/*------------------------- واجهة HTML -------------------------*/
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إضافة متجر جديد</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
<style>
    body{font-family:'Tajawal',sans-serif;background:#f9f9fc;margin:0;padding:40px;}
    .box{background:#fff;max-width:460px;margin:auto;padding:25px 30px;border-radius:12px;
         box-shadow:0 6px 25px rgba(0,0,0,.06);}
    h2{color:#6c63ff;margin-bottom:20px;text-align:center}
    label{display:block;font-weight:600;margin-bottom:6px;margin-top:14px}
    input,select{width:100%;padding:10px;border:1px solid #ddd;border-radius:8px}
    button{background:#6c63ff;color:#fff;border:none;padding:11px 35px;border-radius:8px;
           font-weight:700;font-size:1rem;cursor:pointer;margin-top:22px}
    .back{background:#999;text-decoration:none;margin-right:8px}
    .flash{padding:12px;border-radius:8px;margin-bottom:15px;text-align:center;font-weight:600}
    .flash.success{background:#d1f5d3;color:#0a820c}
    .flash.error{background:#ffd7d7;color:#c20000}
</style>
</head>
<body>

<div class="box">
    <h2>إضافة متجر جديد</h2>

    <?php if($f = get_flash()): ?>
        <div class="flash <?= $f[1] ?>"><?= htmlspecialchars($f[0]) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">

        <label>اسم المتجر *</label>
        <input type="text" name="name" required>

        <label>ID صاحب المتجر *</label>
        <input type="number" name="owner_id" required placeholder="مثال: 5">

        <label>الهاتف *</label>
        <input type="text" name="phone" required>

        <label>رابط الشعار (اختياري)</label>
        <input type="text" name="logo" placeholder="https://...">

        <label><input type="checkbox" name="is_active" checked> متجر نشط</label>

        <button type="submit">إضافة المتجر</button>
        <a href="admin_dashboard.php?tab=stores" class="back" style="color:#fff;padding:11px 28px;display:inline-block;border-radius:8px">رجوع</a>
    </form>
</div>

</body>
</html>
