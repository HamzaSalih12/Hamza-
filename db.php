<?php
// إعدادات الاتصال بقاعدة بيانات hostinger
$host = "localhost"; // إذا لم يعمل استخدم الهوست الذي يظهر بلوحة تحكم hostinger
$dbname = "u823869296_nasiriyah_mall";
$username = "u823869296_admin6";
$password = "Hlo@1997";

// الاتصال
$conn = new mysqli($host, $username, $password, $dbname);

// التحقق من الاتصال
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
