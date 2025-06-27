<?php
/* ---------------- الإعدادات العامة ---------------- */
require_once 'db.php';
require_once 'helpers.php';   // يحتوي على require_admin() و csrf_token() وflash
require_admin();

/* ---------------- جلب بيانات المتجر ---------------- */
$store_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();

if (!$store) {
    set_flash('المتجر غير موجود', 'error');
    header('Location: admin_dashboard.php?tab=stores');
    exit;
}

/* ---------------- حفظ التعديلات ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* 1) حماية CSRF */
    if (!verify_csrf($_POST['_token'] ?? '')) {
        set_flash('رمز CSRF غير صالح، أعد المحاولة', 'error');
        header("Location: edit_store.php?id=$store_id");
        exit;
    }

    /* 2) قراءة القيم */
    $name      = trim($_POST['name']      ?? '');
    $phone     = trim($_POST['phone']     ?? '');
    $logo      = trim($_POST['logo']      ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '' || $phone === '') {
        set_flash('يرجى تعبئة الحقول الإلزامية', 'error');
        header("Location: edit_store.php?id=$store_id");
        exit;
    }

    /* 3) تحديث القيم آمنًا بـ Prepared-Statement */
    $q = $conn->prepare(
        "UPDATE stores SET name = ?, phone = ?, logo = ?, is_active = ? WHERE id = ?"
    );
    $q->bind_param("sssii", $name, $phone, $logo, $is_active, $store_id);

    if ($q->execute()) {
        set_flash('تم حفظ تعديلات المتجر بنجاح');
    } else {
        set_flash('حدث خطأ أثناء الحفظ: ' . $q->error, 'error');
    }
    header('Location: admin_dashboard.php?tab=stores');
    exit;
}

/* ---------------- واجهة HTML ---------------- */
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تعديل المتجر</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
<style>
    body{font-family:'Tajawal',sans-serif;background:#f9f9fc;margin:0;padding:40px;}
    .box{background:#fff;max-width:460px;margin:auto;padding:25px 30px;border-radius:12px;
         box-shadow:0 6px 25px rgba(0,0,0,.06);}
    h2{color:#6c63ff;margin-bottom:18px;text-align:center}
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
    <h2>تعديل بيانات المتجر</h2>

    <?php if($f = get_flash()): ?>
        <div class="flash <?= $f[1] ?>"><?= htmlspecialchars($f[0]) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <label>اسم المتجر *</label>
        <input type="text" name="name" value="<?= htmlspecialchars($store['name']) ?>" required>

        <label>الهاتف *</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($store['phone']) ?>" required>

        <label>رابط الشعار</label>
        <input type="text" name="logo" value="<?= htmlspecialchars($store['logo']) ?>">

        <label><input type="checkbox" name="is_active" <?= $store['is_active'] ? 'checked' : '' ?>> متجر نشط</label>

        <button type="submit">حفظ التعديلات</button>
        <a href="admin_dashboard.php?tab=stores" class="back" style="color:#fff;padding:11px 28px;display:inline-block;border-radius:8px">رجوع</a>
    </form>
</div>

</body>
</html>
