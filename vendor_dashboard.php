<?php
// --------------- الإعدادات الأساسيّة ---------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require 'db.php';

// --------------- التحقّق من جلسة البائع ---------------
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'vendor') {
    header('Location: login.php');
    exit;
}
$vendor_id = (int)$_SESSION['user_id'];

// --------------- جلب المتجر التابع للبائع ---------------
$stmt = $conn->prepare("SELECT id, name FROM stores WHERE owner_id = ? LIMIT 1");
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$store  = $stmt->get_result()->fetch_assoc();
if (!$store) {
    die('لم يتم العثور على متجر مرتبط بهذا الحساب.');
}
$store_id   = (int)$store['id'];
$store_name = $store['name'];

// --------------- جلب المنتجات ---------------
$prod_stmt = $conn->prepare("SELECT id, name, category, price, is_active,
                                    COALESCE(image, '') AS image
                             FROM products
                             WHERE store_id = ?
                             ORDER BY created_at DESC");
$prod_stmt->bind_param('i', $store_id);
$prod_stmt->execute();
$products = $prod_stmt->get_result();

// --------------- جلب الطلبات مع الألوان والقياسات ---------------
$orders_stmt = $conn->prepare("
    SELECT o.id, o.customer_name, o.customer_phone, o.customer_address,
           o.status, o.created_at,
           oi.product_id, oi.quantity, oi.color_name, oi.color_code, oi.size_id,
           p.name AS product_name
    FROM orders           o
    JOIN order_items      oi ON oi.order_id = o.id
    JOIN products         p  ON p.id       = oi.product_id
    WHERE oi.store_id = ?
    ORDER BY o.created_at DESC
");
$orders_stmt->bind_param('i', $store_id);
$orders_stmt->execute();
$orders = $orders_stmt->get_result();

// --------------- بناء خريطة القياسات لمرة واحدة ---------------
$size_map = [];
$size_ids = [];

foreach ($orders as $or) {
    if (!empty($or['size_id'])) $size_ids[] = (int)$or['size_id'];
}
$size_ids = array_unique($size_ids);
if ($size_ids) {
    $in  = implode(',', $size_ids);
    $sql = "SELECT id, size_type, size_value FROM product_sizes WHERE id IN ($in)";
    $res = $conn->query($sql);
    while ($sz = $res->fetch_assoc()) $size_map[(int)$sz['id']] = $sz;
}
/* أعد مؤشر النتائج لبداية الجدول */
$orders->data_seek(0);

// --------------- إنشاء / تحديث CSRF Token ---------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>لوحة تحكّم البائع | سوق الناصرية</title>
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&display=swap"
      rel="stylesheet">
<style>
    :root{
        --primary:#6C63FF;--secondary:#4D44DB;--accent:#FF6584;
        --light:#F8F9FA;--dark:#212529;--trans:.25s ease;
    }
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Tajawal',sans-serif}
    body{background:var(--light);color:var(--dark)}
    /* ===== الهيدر ===== */
    header{background:linear-gradient(135deg,var(--primary),var(--secondary));
           color:#fff;padding:1rem 5%;box-shadow:0 4px 12px #0002;
           position:fixed;top:0;width:100%;z-index:1000;display:flex;
           justify-content:space-between;align-items:center}
    .logout-btn{background:#fff;color:var(--primary);border:2px solid var(--primary);
                border-radius:7px;padding:.4rem 1.2rem;font-weight:600;cursor:pointer;
                transition:var(--trans)}
    .logout-btn:hover{background:var(--accent);color:#fff;border-color:var(--accent)}
    /* ===== المحتوى ===== */
    .dashboard{margin-top:90px;padding:2.5rem 6%}
    .dash-title{font-size:2rem;font-weight:800;margin-bottom:1.5rem}
    .tabs{display:flex;gap:1rem;margin-bottom:2rem;flex-wrap:wrap}
    .tab-btn{background:var(--primary);color:#fff;border:none;cursor:pointer;padding:.55rem 2rem;
             border-radius:7px 7px 0 0;font-weight:600;transition:var(--trans)}
    .tab-btn:hover,.tab-btn.active{background:var(--accent)}
    .panel{display:none;background:#fff;border-radius:0 0 10px 10px;padding:2rem 1rem;
           box-shadow:0 5px 18px #6c63ff14;margin-bottom:3rem}
    .panel.active{display:block}
    .add-btn{background:var(--primary);color:#fff;border:none;border-radius:7px;
             padding:.6rem 1.6rem;font-weight:600;margin-bottom:1.3rem;cursor:pointer;
             transition:var(--trans)}
    .add-btn:hover{background:var(--accent)}
    /* جدول */
    .table-wrap{overflow-x:auto}
    table{width:100%;border-collapse:collapse;font-size:.95rem}
    th,td{padding:.8rem .5rem;text-align:center}
    th{background:var(--secondary);color:#fff}
    tr:not(:last-child)>td{border-bottom:1px solid #eee}
    img.prod-img{width:50px;height:50px;border-radius:9px;object-fit:cover}
    .act-btn{background:var(--accent);color:#fff;border:none;border-radius:6px;
             padding:.35rem .9rem;font-size:.9rem;cursor:pointer;transition:var(--trans)}
    .act-btn:hover{background:var(--primary)}
    .color-dot{width:22px;height:22px;border-radius:50%;display:inline-block;margin-left:6px;
               border:2px solid #ccc;box-shadow:0 1px 6px #0001;vertical-align:middle}
    .size-label{background:#e9ecef;border-radius:5px;padding:.15rem .7rem;font-size:.87rem}
    @media(max-width:800px){
        .dashboard{padding:1.2rem 3%}.tab-btn{padding:.5rem 1.1rem}
        .panel{padding:1rem .4rem}
    }
</style>
</head>
<body>
<header>
    <div style="display:flex;align-items:center;font-size:1.35rem;font-weight:700">
        <i class="fas fa-store" style="margin-left:10px;color:var(--accent)"></i>
        لوحة تحكّم – <?= htmlspecialchars($store_name) ?>
    </div>
    <form action="logout.php" method="post">
        <input type="hidden" name="csrf" value="<?= $token ?>">
        <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> خروج</button>
    </form>
</header>

<section class="dashboard">
    <h2 class="dash-title">مرحباً بك، يمكنك إدارة متجرك من هنا</h2>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" onclick="showTab(0)">منتجاتي</button>
        <button class="tab-btn"        onclick="showTab(1)">الطلبات</button>
    </div>

    <!-- === لوحة المنتجات === -->
    <div class="panel active">
        <button class="add-btn" onclick="location.href='add_product.php'">
            <i class="fas fa-plus"></i> إضافة منتج
        </button>
        <div class="table-wrap">
        <table>
            <tr><th>صورة</th><th>الاسم</th><th>الصنف</th><th>السعر</th><th>الحالة</th><th>إجراءات</th></tr>
            <?php foreach ($products as $p): ?>
            <tr>
                <td><img class="prod-img"
                         src="<?= $p['image'] ?: 'https://via.placeholder.com/50' ?>"></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['category']) ?></td>
                <td><?= number_format($p['price']) ?> د.ع</td>
                <td><?= $p['is_active'] ? 'متاح' : 'معطل' ?></td>
                <td>
                    <button class="act-btn"
                            onclick="location.href='edit_product.php?id=<?= $p['id'] ?>'">
                        تعديل
                    </button>
                    <button class="act-btn"
                            onclick="confirmDelete(<?= $p['id'] ?>)">
                        حذف
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    </div>

    <!-- === لوحة الطلبات === -->
    <div class="panel">
        <div class="table-wrap">
        <table>
            <tr>
                <th>رقم</th><th>المنتج</th><th>اللون</th><th>المقاس</th><th>الكمية</th>
                <th>الزبون</th><th>الهاتف</th><th>العنوان</th><th>الحالة</th><th>تحديث</th>
            </tr>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td><?= $o['id'] ?></td>
                <td><?= htmlspecialchars($o['product_name']) ?></td>
                <td>
                    <?php if ($o['color_name']): ?>
                        <span class="color-dot" style="background:<?= $o['color_code'] ?>"></span>
                        <?= htmlspecialchars($o['color_name']) ?>
                    <?php else: ?> - <?php endif; ?>
                </td>
                <td>
                    <?php
                        $sz = $o['size_id'] && isset($size_map[$o['size_id']]) ? $size_map[$o['size_id']] : null;
                        echo $sz ? '<span class="size-label">'.$sz['size_value'].'</span>' : '-';
                    ?>
                </td>
                <td><?= $o['quantity'] ?></td>
                <td><?= htmlspecialchars($o['customer_name']) ?></td>
                <td><?= htmlspecialchars($o['customer_phone']) ?></td>
                <td><?= htmlspecialchars($o['customer_address']) ?></td>
                <td>
                    <?php
                        echo $o['status']=='pending'   ? 'قيد التنفيذ'
                            : ($o['status']=='delivered'? 'تم التسليم' : 'ملغي');
                    ?>
                </td>
                <td>
                    <form action="update_order_status.php" method="post">
                        <input type="hidden" name="csrf"      value="<?= $token ?>">
                        <input type="hidden" name="order_id"  value="<?= $o['id'] ?>">
                        <select name="status" onchange="this.form.submit()">
                            <option value="pending"   <?= $o['status']=='pending'?'selected':'' ?>>قيد التنفيذ</option>
                            <option value="delivered" <?= $o['status']=='delivered'?'selected':'' ?>>تم التسليم</option>
                            <option value="cancelled" <?= $o['status']=='cancelled'?'selected':'' ?>>ملغي</option>
                        </select>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    </div>
</section>

<script>
function showTab(i){
    document.querySelectorAll('.panel').forEach((p,idx)=>p.classList.toggle('active',idx===i));
    document.querySelectorAll('.tab-btn').forEach((b,idx)=>b.classList.toggle('active',idx===i));
}
function confirmDelete(id){
    if(confirm('هل أنت متأكد من حذف المنتج؟')){
        location.href='delete_product.php?id='+id+'&csrf=<?=$token?>';
    }
}
</script>
</body>
</html>
