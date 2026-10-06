<?php
// Staff verification endpoint
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

// Catch-all exception handler to ensure JSON response
set_exception_handler(function ($e) {
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'ระบบขัดข้อง: ' . $e->getMessage()]);
    exit;
});

// Catch-all error handler for fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Fatal Error: ' . $error['message']]);
    }
});

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$code = $_POST['code'] ?? '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกรหัสเจ้าหน้าที่']);
    exit;
}

if (verifyStaffCode($code)) {
    $_SESSION['staff_logged_in'] = true;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'รหัสเจ้าหน้าที่ไม่ถูกต้อง']);
}
exit;
