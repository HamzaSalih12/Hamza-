<?php
require 'db.php';

// جلب جميع المستخدمين
$result = $conn->query("SELECT id, password FROM users");
while($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $old_pass = $row['password'];

    // إذا كانت كلمة السر ليست هاش (أطوال هاش BCRYPT دائمًا 60)
    if (strlen($old_pass) < 40) {
        $new_pass = password_hash($old_pass, PASSWORD_DEFAULT);
        // حدث كلمة السر
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $new_pass, $id);
        $stmt->execute();
        echo "تم تشفير كلمة سر المستخدم ID $id بنجاح<br>";
    }
}

echo "<hr>انتهت العملية.";
?>
