<?php
/**
 * auth.php
 * -----------
 * وظائف المِلَفّ:
 *   1) current_user()   : ترجع معرّف المستخدم الحالى (أو null).
 *   2) login_user($id,$remember=false)
 *   3) logout_user()
 *
 * يعتمد على:
 *   - db.php  : كائن mysqli فى متغيّر $conn
 * يستخدم Cookie باسم remember لتفعيل «تذكّرنى» لمدة 30 يومًا.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db.php';

/* ============ 1. مَن هو المستخدم الحالى؟ ============ */
function current_user()
{
    // موجود فى الجلسة
    if (isset($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }

    // جرّب الـ Cookie «تذكرنى»
    if (!empty($_COOKIE['remember']) && preg_match('/^[a-f0-9]{64}$/', $_COOKIE['remember'])) {
        global $conn;
        $token = $_COOKIE['remember'];

        $stmt = $conn->prepare("SELECT id FROM users WHERE remember_token=? LIMIT 1");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row) {
            // نجاح: فعّل الجلسة
            $_SESSION['user_id'] = $row['id'];
            return (int)$row['id'];
        }
    }
    return null;    // زائر
}

/* ============ 2. تسجيل الدخول ============ */
function login_user(int $uid, bool $remember = false): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $uid;

    if ($remember) {
        $token = bin2hex(random_bytes(32));  // 64 حرفًا عشوائية
        global $conn;
        $stmt = $conn->prepare("UPDATE users SET remember_token=? WHERE id=? LIMIT 1");
        $stmt->bind_param('si', $token, $uid);
        $stmt->execute();

        // Cookie لمدة 30 يوم
        setcookie(
            'remember',
            $token,
            [
                'expires'  => time() + 60 * 60 * 24 * 30,
                'path'     => '/',
                'secure'   => false,   // غيّره إلى true إذا لديك HTTPS
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }
}

/* ============ 3. تسجيل الخروج ============ */
function logout_user(): void
{
    // امسح Cookie
    if (isset($_COOKIE['remember'])) {
        setcookie('remember', '', time() - 3600, '/', '', false, true);
    }

    // أنهِ الجلسة
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}
