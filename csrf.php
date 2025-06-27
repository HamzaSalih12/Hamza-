<?php
/* csrf.php – توليد وتحقق CSRF */
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="'.csrf_token().'">';
}
function csrf_verify(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ok = isset($_POST['_csrf'], $_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $_POST['_csrf']);
        if (!$ok) {
            http_response_code(419);
            exit('رمز CSRF غير صالح، أعد تحميل الصفحة.');
        }
    }
}
