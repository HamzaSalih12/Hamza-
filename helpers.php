<?php
/* helpers.php
   دوال حماية و أدوات مشتركة
------------------------------------------------*/
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------------- CSRF ---------------- */
function csrf_token(): string
{
    if (empty($_SESSION['_token'])) {
        $_SESSION['_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_token'];
}

function verify_csrf(string $token): bool
{
    return isset($_SESSION['_token']) && hash_equals($_SESSION['_token'], $token);
}

/* -------------- Flash messages -------------- */
function set_flash(string $msg, string $type = 'success'): void
{
    $_SESSION['_flash'] = [$msg, $type];
}

function get_flash(): ?array
{
    if (!empty($_SESSION['_flash'])) {
        $f = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $f;                        // [msg , type]
    }
    return null;
}

/* غلاف متوافق مع الاستدعاءات القديمة */
function flash(?string $msg = null, string $type = 'success')
{
    if ($msg !== null) {               // كتابة رسالة
        set_flash($msg, $type);
        return;
    }
    return get_flash();                // قراءة الرسالة
}

/* ----------- حراسة الصلاحيات ----------- */
function require_admin(): void
{
    if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: login.php');
        exit;
    }
}

function require_vendor(): void
{
    if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'vendor') {
        header('Location: login.php');
        exit;
    }
}

/* ----------- توليد سلاگ ورابط المتجر ----------- */
/**
 * حوِّل نصًّا إلى سلاگ يصلح للروابط: "سوق الناصرية" ← "souq-al-nasria"
 */
function slugify(string $text): string
{
    // استبدال الفراغات وما شابه بشرطة
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);

    // تحويل الحروف المعرَّبة إلى لاتينية حيث أمكن
    if (function_exists('iconv')) {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    }

    // إزالة أى حروف غير مسموح بها
    $text = preg_replace('~[^-\w]+~', '', $text);

    // دمج الشرطات المتتالية
    $text = preg_replace('~-+~', '-', $text);

    // إزالة الشرطات من البداية والنهاية
    $text = trim($text, '-');

    return strtolower($text ?: 'store');
}

/**
 * توليد الرابط الصديق للمتجر
 * مثال: generate_store_url(12, 'سوق الناصرية') => /stores/12/souq-al-nasria
 */
function generate_store_url(int $id, string $name): string
{
    return '/stores/' . $id . '/' . slugify($name);
}
?>
