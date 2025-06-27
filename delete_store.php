<?php
session_start();
require 'db.php';
require_once 'helpers.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') exit;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['token'] ?? '')) {
    header('Location: admin_dashboard.php?tab=stores');
    exit;
}

$id = intval($_POST['id'] ?? 0);
$stmt = $conn->prepare('DELETE FROM stores WHERE id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
header('Location: admin_dashboard.php?tab=stores');
exit;
?>
