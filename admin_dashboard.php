<?php
/*************************************************
 * لوحة تحكّم الإدارة – سوق الناصرية الإلكتروني
 * -----------------------------------------------
 * يحتاج إلى:
 *   - db.php          (اتصال قاعدة البيانات)
 *   - helpers.php     (توكن CSRF + flash + حراسة صلاحيات)
 *************************************************/
require_once 'db.php';
require_once 'helpers.php';

require_admin();                           // حماية الصفحة

/* ---------- إحصائيات سريعة ---------- */
$stores_count     = $conn->query("SELECT COUNT(*) c FROM stores"            )->fetch_assoc()['c'] ?? 0;
$products_count   = $conn->query("SELECT COUNT(*) c FROM products"          )->fetch_assoc()['c'] ?? 0;
$orders_count     = $conn->query("SELECT COUNT(*) c FROM orders"            )->fetch_assoc()['c'] ?? 0;
$pending_orders   = $conn->query("SELECT COUNT(*) c FROM orders WHERE status='pending'")->fetch_assoc()['c'] ?? 0;

/* ---------- جداول مختصرة ---------- */
$stores   = $conn->query("SELECT s.*,u.name owner_name
                          FROM stores s
                          JOIN users u ON u.id=s.owner_id
                          ORDER BY s.created_at DESC
                          LIMIT 5");

$products = $conn->query("SELECT p.*,s.name store_name
                          FROM products p
                          JOIN stores   s ON s.id=p.store_id
                          ORDER BY p.created_at DESC
                          LIMIT 5");

$orders   = $conn->query("
    SELECT  o.*,
            (SELECT s.name  FROM stores    s
             JOIN order_items oi2 ON s.id=oi2.store_id
             WHERE oi2.order_id=o.id LIMIT 1) store_name,
            (SELECT p.name  FROM products  p
             JOIN order_items oi3 ON p.id=oi3.product_id
             WHERE oi3.order_id=o.id LIMIT 1) product_name,
            (SELECT oi.quantity FROM order_items oi WHERE oi.order_id=o.id LIMIT 1) qty
    FROM orders o
    ORDER BY o.created_at DESC
    LIMIT 5
");

$admins = $conn->query("SELECT * FROM users WHERE role='admin' ORDER BY created_at DESC");

/* ---------- رسائل فلاش ---------- */
$flash = get_flash();                      // [0] = msg , [1] = type
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>لوحة تحكم الإدارة | سوق الناصرية</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
<style>
    :root{
        --primary:#6C63FF;--secondary:#4D44DB;--accent:#FF6584;
        --light:#F8F9FA;--dark:#212529;--trans:.3s ease;
    }
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Tajawal',sans-serif}
    body{background:var(--light);color:var(--dark)}

    /* ======= HEADER ======= */
    header{background:linear-gradient(135deg,var(--primary),var(--secondary));
           color:#fff;padding:1.2rem 5%;position:fixed;inset:0 0 auto 0;
           display:flex;justify-content:space-between;align-items:center;
           box-shadow:0 4px 12px #0002;z-index:1000}
    .logout-btn{background:#fff;color:var(--primary);border:2px solid var(--primary);
                border-radius:7px;padding:.4rem 1.2rem;font-weight:600;
                cursor:pointer;transition:var(--trans)}
    .logout-btn:hover{background:var(--accent);border-color:var(--accent);color:#fff}

    /* ======= MAIN SECTION ======= */
    .admin-section{margin-top:90px;padding:2.5rem 6%}
    .admin-title{font-size:2.1rem;font-weight:700;margin-bottom:1.7rem}

    /* stats */
    .stats{display:flex;gap:2rem;flex-wrap:wrap;margin-bottom:2.2rem}
    .stat{flex:1;min-width:210px;background:#fff;border-radius:11px;
          box-shadow:0 4px 15px #6c63ff12;padding:1.6rem 1.8rem;text-align:center}
    .stat h4{font-size:1.05rem;color:#555;margin-bottom:.4rem}
    .stat .num{font-size:2rem;font-weight:700;color:var(--primary)}

    /* tabs */
    .tabs{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:2rem}
    .tab-btn{background:var(--primary);color:#fff;border:none;border-radius:7px 7px 0 0;
             padding:.6rem 2rem;font-weight:600;cursor:pointer;transition:var(--trans)}
    .tab-btn.active,.tab-btn:hover{background:var(--accent)}
    .panel{display:none;background:#fff;border-radius:0 0 11px 11px;
           box-shadow:0 5px 20px #6c63ff12;padding:2rem 1.3rem;margin-bottom:2rem}
    .panel.active{display:block}

    /* table */
    .table-wrap{overflow-x:auto}
    table{width:100%;border-collapse:collapse;margin-bottom:1rem}
    th,td{padding:.85rem .5rem;text-align:center}
    th{background:var(--secondary);color:#fff;font-weight:600}
    tr:not(:last-child) td{border-bottom:1px solid #eee}
    img.mini{width:44px;height:44px;object-fit:cover;border-radius:7px}

    .btn{display:inline-block;padding:.4rem .9rem;border-radius:6px;font-size:.9rem;
         color:#fff;background:var(--accent);text-decoration:none;border:none;
         cursor:pointer;transition:var(--trans)}
    .btn:hover{background:var(--primary)}
    .btn.gray{background:#ccc!important;color:#000}
    .flash{padding:.8rem 1rem;border-radius:8px;margin-bottom:1.4rem;font-weight:600}
    .flash.success{background:#d4edda;color:#155724}
    .flash.error  {background:#f8d7da;color:#721c24}

    @media(max-width:800px){
        .admin-section{padding:1.2rem 2%}
        .tab-btn{padding:.45rem .9rem}
        .panel{padding:1rem .3rem}
        .stats{flex-direction:column;gap:1rem}
    }
</style>
</head>
<body>

<!-- ===== HEADER ===== -->
<header>
    <div style="display:flex;align-items:center;font-size:1.4rem;font-weight:600">
        <i class="fas fa-user-shield" style="margin-left:10px;color:var(--accent)"></i>
        لوحة تحكم الإدارة – سوق الناصرية
    </div>
    <form action="logout.php" method="post">
        <button class="logout-btn" type="submit"><i class="fas fa-sign-out-alt"></i> خروج</button>
    </form>
</header>

<section class="admin-section">

    <h2 class="admin-title">مرحبًا بك 👋</h2>

    <!-- رسائل فلاش -->
    <?php if($flash): ?>
        <div class="flash <?= $flash[1]==='error'?'error':'success' ?>">
            <?= htmlspecialchars($flash[0]) ?>
        </div>
    <?php endif; ?>

    <!-- إحصائيات -->
    <div class="stats">
        <div class="stat"><h4>عدد المتاجر</h4><div class="num"><?= $stores_count ?></div></div>
        <div class="stat"><h4>عدد المنتجات</h4><div class="num"><?= $products_count ?></div></div>
        <div class="stat"><h4>إجمالي الطلبات</h4><div class="num"><?= $orders_count ?></div></div>
        <div class="stat"><h4>طلبات قيد التنفيذ</h4><div class="num"><?= $pending_orders ?></div></div>
    </div>

    <!-- تبويبات -->
    <div class="tabs">
        <button class="tab-btn active" onclick="showPanel(0)">المتاجر</button>
        <button class="tab-btn"         onclick="showPanel(1)">المنتجات</button>
        <button class="tab-btn"         onclick="showPanel(2)">الطلبات</button>
        <button class="tab-btn"         onclick="showPanel(3)">المسؤولون</button>
    </div>

    <!-- ===== PANEL 0 : STORES ===== -->
    <div class="panel active">
        <a class="btn" href="add_store.php"><i class="fas fa-plus"></i> إضافة متجر</a>
        <div class="table-wrap">
            <table>
                <tr>
                    <th>الشعار</th><th>المتجر</th><th>المالك</th><th>الهاتف</th>
                    <th>الحالة</th><th>مميز؟</th><th>إجراءات</th>
                </tr>
                <?php while($s=$stores->fetch_assoc()): ?>
                <tr>
                    <td><img class="mini" src="<?= $s['logo']?htmlspecialchars($s['logo']):'https://via.placeholder.com/44' ?>"></td>
                    <td><?= htmlspecialchars($s['name']) ?></td>
                    <td><?= htmlspecialchars($s['owner_name']) ?></td>
                    <td><?= htmlspecialchars($s['phone']) ?></td>
                    <td style="color:<?= $s['is_active']?'#009966':'#ff3860' ?>;font-weight:600">
                        <?= $s['is_active']?'نشط':'معطل' ?>
                    </td>
                    <td>
                        <?php if($s['is_featured']): ?>
                            <span style="color:#FFD700;font-weight:bold">★</span>
                            <a class="btn gray" href="toggle_featured_store.php?id=<?= $s['id'] ?>&action=unset&token=<?= csrf_token() ?>" onclick="return confirm('إلغاء تمييز؟')">إلغاء</a>
                        <?php else: ?>
                            <span style="color:#aaa">—</span>
                            <a class="btn" href="toggle_featured_store.php?id=<?= $s['id'] ?>&action=set&token=<?= csrf_token() ?>" onclick="return confirm('تمييز المتجر؟')">تمييز</a>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a class="btn" href="edit_store.php?id=<?= $s['id'] ?>"><i class="fas fa-edit"></i></a>
                        <a class="btn" href="toggle_store.php?id=<?= $s['id'] ?>&action=<?= $s['is_active']?'deactivate':'activate' ?>&token=<?= csrf_token() ?>"
                           onclick="return confirm('<?= $s['is_active']?'تعطيل':'تفعيل' ?> المتجر؟')">
                           <i class="fas <?= $s['is_active']?'fa-ban':'fa-check' ?>"></i>
                        </a>
                        <form action="delete_store.php" method="post" style="display:inline" onsubmit="return confirm('حذف المتجر؟')">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <input type="hidden" name="token" value="<?= csrf_token() ?>">
                            <button class="btn" type="submit"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>

    <!-- ===== PANEL 1 : PRODUCTS ===== -->
    <div class="panel">
        <div class="table-wrap">
            <table>
                <tr>
                    <th>الصورة</th><th>المنتج</th><th>المتجر</th><th>الصنف</th>
                    <th>السعر</th><th>الحالة</th><th>إجراءات</th>
                </tr>
                <?php while($p=$products->fetch_assoc()): ?>
                <tr>
                    <td><img class="mini" src="<?= $p['image']?htmlspecialchars($p['image']):'https://via.placeholder.com/44' ?>"></td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td><?= htmlspecialchars($p['store_name']) ?></td>
                    <td><?= htmlspecialchars($p['category']) ?></td>
                    <td><?= number_format($p['price'],0,'.',',') ?> د.ع</td>
                    <td><?= $p['is_active']?'نشط':'معطل' ?></td>
                    <td class="actions">
                        <a class="btn" href="edit_product.php?id=<?= $p['id'] ?>"><i class="fas fa-edit"></i></a>
                        <form action="delete_product.php" method="post" style="display:inline" onsubmit="return confirm('حذف المنتج؟')">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="token" value="<?= csrf_token() ?>">
                            <button class="btn" type="submit"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>

    <!-- ===== PANEL 2 : ORDERS ===== -->
    <div class="panel">
        <div class="table-wrap">
            <table>
                <tr>
                    <th>#</th><th>المتجر</th><th>المنتج</th><th>الكمية</th>
                    <th>الزبون</th><th>الهاتف</th><th>العنوان</th><th>الحالة</th><th>عرض</th>
                </tr>
                <?php while($o=$orders->fetch_assoc()): ?>
                <tr>
                    <td><?= $o['id'] ?></td>
                    <td><?= htmlspecialchars($o['store_name']??'--') ?></td>
                    <td><?= htmlspecialchars($o['product_name']??'--') ?></td>
                    <td><?= htmlspecialchars($o['qty']??'--') ?></td>
                    <td><?= htmlspecialchars($o['customer_name']) ?></td>
                    <td><?= htmlspecialchars($o['customer_phone']) ?></td>
                    <td><?= htmlspecialchars($o['customer_address']) ?></td>
                    <td><?= htmlspecialchars($o['status']) ?></td>
                    <td><a class="btn" href="view_order.php?id=<?= $o['id'] ?>"><i class="fas fa-eye"></i></a></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>

    <!-- ===== PANEL 3 : ADMINS ===== -->
    <div class="panel">
        <a class="btn" href="add_admin.php"><i class="fas fa-user-plus"></i> إضافة مسؤول</a>
        <div class="table-wrap">
            <table>
                <tr><th>الاسم</th><th>البريد</th><th>الهاتف</th><th>إجراءات</th></tr>
                <?php while($a=$admins->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($a['name']) ?></td>
                    <td><?= htmlspecialchars($a['email']) ?></td>
                    <td><?= htmlspecialchars($a['phone']) ?></td>
                    <td class="actions">
                        <a class="btn" href="edit_admin.php?id=<?= $a['id'] ?>"><i class="fas fa-edit"></i></a>
                        <?php if($a['id']!=(int)$_SESSION['user_id']): // لا يمكن حذف نفسك ?>
                        <form action="delete_admin.php" method="post" style="display:inline" onsubmit="return confirm('حذف المسؤول؟')">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="token" value="<?= csrf_token() ?>">
                            <button class="btn" type="submit"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>

</section>

<script>
/* تبويبات */
function showPanel(i){
    document.querySelectorAll('.panel').forEach((p,idx)=>p.classList.toggle('active',idx===i));
    document.querySelectorAll('.tab-btn').forEach((b,idx)=>b.classList.toggle('active',idx===i));
}
</script>
</body>
</html>
