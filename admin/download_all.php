<?php
require_once '../config.php';require_once 'auth.php';require_once 'backup_service.php';
try {
    $id=(int)($_GET['id'] ?? 0);if(!$id)throw new Exception('ไม่พบผู้สมัคร');
    $conn=getDBConnection();$name=backupAndClear($conn,null,$id,false);$path=backupDirectory().'/'.$name;
    header('Content-Type: application/zip');header('Cache-Control: no-store');header('Content-Disposition: attachment; filename="'.$name.'"');header('Content-Length: '.filesize($path));readfile($path);
} catch(Throwable $e) { http_response_code(400); echo htmlspecialchars($e->getMessage()); }
