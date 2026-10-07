<?php
require_once '../config.php';require_once 'auth.php';require_once 'export_service.php';
$path = null;
try {
    $id=(int)($_GET['id'] ?? 0);if(!$id)throw new Exception('ไม่พบผู้สมัคร');
    $conn=getDBConnection();$stmt=$conn->prepare('SELECT * FROM registrations WHERE id=?');
    $stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();
    if (!$row) throw new Exception('ไม่พบผู้สมัคร');
    $path=createStudentExport([$row]);sendStudentExport($path,studentExportName($row).'.zip');
} catch(Throwable $e) { http_response_code(400); echo htmlspecialchars($e->getMessage()); }
finally { if ($path) @unlink($path); }
