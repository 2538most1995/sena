<?php
require_once '../config.php';
require_once 'auth.php';
require_once 'backup_service.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!hash_equals(csrfToken(), $_POST['csrf'] ?? '')) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'กรุณาโหลดหน้าใหม่']); exit; }
$conn = getDBConnection();
$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID ไม่ถูกต้อง']);
    exit;
}

switch ($action) {
    case 'update_status':
        $status = $_POST['status'] ?? '';
        $allowed = ['pending', 'approved', 'rejected'];

        if (!in_array($status, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'สถานะไม่ถูกต้อง']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE registrations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);

        if ($stmt->execute()) {
            $statusText = ['pending' => 'รอดำเนินการ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ไม่อนุมัติ'];
            echo json_encode(['success' => true, 'message' => 'อัพเดทสถานะเป็น: ' . ($statusText[$status] ?? $status)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด']);
        }
        $stmt->close();
        break;

    case 'delete':
        try {
            if (!hash_equals(csrfToken(), $_POST['csrf'] ?? '')) throw new Exception('กรุณาโหลดหน้าใหม่ก่อนลบข้อมูล');
            $backup = backupAndClear($conn, null, $id, true);
            echo json_encode(['success' => true, 'message' => 'สำรองและลบข้อมูลสำเร็จ', 'backup' => $backup]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action ไม่ถูกต้อง']);
}

$conn->close();
