<?php
session_start();
require 'db.php';
require_once 'helpers.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') exit;

$id     = intval($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';
$token  = $_GET['token'] ?? '';

if (!$id || !in_array($action, ['activate','deactivate']) || !verify_csrf($token)) {
    header('Location: admin_dashboard.php?tab=stores');
    exit;
}

$status = $action === 'activate' ? 1 : 0;
$stmt = $conn->prepare('UPDATE stores SET is_active=? WHERE id=?');
$stmt->bind_param('ii', $status, $id);
$stmt->execute();

header('Location: admin_dashboard.php?tab=stores');
exit;
?>
