<?php
// admin/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    // If it's an AJAX/JSON request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบอีกครั้ง']);
        exit;
    }
    
    // For specific action files which are also expecting JSON but may not have AJAX headers
    $script = basename($_SERVER['SCRIPT_FILENAME']);
    if (in_array($script, ['action.php', 'action_settings.php'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบอีกครั้ง']);
        exit;
    }

    // Otherwise redirect to main index
    header('Location: ../index.php');
    exit;
}
