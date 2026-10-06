<?php
// Logo upload handler - staff only
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function ($e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
});

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify staff password
$password = $_POST['password'] ?? '';
if (!verifyStaffCode($password)) {
    echo json_encode(['success' => false, 'message' => 'รหัสผ่านไม่ถูกต้อง']);
    exit;
}

// Check file
if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเลือกไฟล์โลโก้']);
    exit;
}

$file = $_FILES['logo'];
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'รองรับเฉพาะไฟล์รูปภาพ (jpg, png, gif, webp, svg)']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'ไฟล์ขนาดใหญ่เกิน 5MB']);
    exit;
}

// Save logo
$logoDir = __DIR__ . '/uploads/logo/';
if (!is_dir($logoDir)) {
    mkdir($logoDir, 0755, true);
}

// Remove old logos
$oldFiles = glob($logoDir . 'logo.*');
foreach ($oldFiles as $old) {
    @unlink($old);
}

$targetPath = $logoDir . 'logo.' . $ext;
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode([
        'success' => true,
        'message' => 'อัพโหลดโลโก้สำเร็จ',
        'logo_url' => 'uploads/logo/logo.' . $ext . '?t=' . time()
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกไฟล์']);
}
