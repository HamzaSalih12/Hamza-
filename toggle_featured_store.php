<?php
session_start();
require 'db.php';
require_once 'helpers.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';
$token  = $_GET['token'] ?? '';

if (!verify_csrf($token)) {
    header('Location: admin_dashboard.php');
    exit;
}

if ($id && in_array($action, ['set', 'unset'])) {
    $val = $action == 'set' ? 1 : 0;
    $stmt = $conn->prepare("UPDATE stores SET is_featured=? WHERE id=?");
    $stmt->bind_param("ii", $val, $id);
    $stmt->execute();
}

header('Location: admin_dashboard.php?tab=stores');
exit;
