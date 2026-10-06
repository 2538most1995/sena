<?php
require_once '../config.php';
require_once 'auth.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? 'change_password';

// ==========================================
// Action: Save Email Notification Settings
// ==========================================
if ($action === 'save_email_settings') {
    $notificationEmail = trim($_POST['notification_email'] ?? '');
    $notificationEnabled = ($_POST['notification_enabled'] ?? '0') === '1' ? '1' : '0';

    $conn = getDBConnection();

    // Upsert notification_email
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('notification_email', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->bind_param("s", $notificationEmail);
    $stmt->execute();
    $stmt->close();

    // Upsert notification_enabled
    $stmt2 = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('notification_enabled', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt2->bind_param("s", $notificationEnabled);
    $stmt2->execute();
    $stmt2->close();

    $conn->close();

    echo json_encode(['success' => true, 'message' => 'บันทึกการตั้งค่าอีเมลสำเร็จ']);
    exit;
}

// ==========================================
// Action: Send Test Email
// ==========================================
if ($action === 'test_email') {
    $testEmail = trim($_POST['test_email'] ?? '');

    if (empty($testEmail)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกอีเมลผู้รับ']);
        exit;
    }

    // Send to first email only for test
    $firstEmail = explode(',', $testEmail)[0];
    $result = sendTestEmail(trim($firstEmail));
    echo json_encode($result);
    exit;
}

// ==========================================
// Action: Change Password (default)
// ==========================================
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน']);
    exit;
}

// Check current password
if (!verifyStaffPassword($currentPassword)) {
    echo json_encode(['success' => false, 'message' => 'รหัสผ่านเดิมไม่ถูกต้อง']);
    exit;
}

// Update to new password
try {
    $result = updateStaffPassword($newPassword);
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'เปลี่ยนรหัสผ่านสำเร็จแล้ว']);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถบันทึกรหัสผ่านได้ กรุณาลองใหม่อีกครั้ง']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
