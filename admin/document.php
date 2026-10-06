<?php
require_once '../config.php'; require_once 'auth.php';
$conn=getDBConnection(); $id=(int)($_GET['id'] ?? 0);$field=$_GET['field'] ?? '';
$map=['photo_file'=>'photos','id_card_file'=>'id_cards','house_reg_file'=>'house_registrations','certificate_file'=>'certificates','certificate_back_file'=>'certificates'];
if (!isset($map[$field])) { http_response_code(400); exit('เอกสารไม่ถูกต้อง'); }
$stmt=$conn->prepare('SELECT `'.$field.'` filename FROM registrations WHERE id=?');$stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();
if (!$row || empty($row['filename'])) { http_response_code(404);exit('ยังไม่ได้แนบเอกสาร'); }
$name=basename($row['filename']);$path=UPLOAD_DIR.$map[$field].'/'.$name;
if (!is_file($path)) { http_response_code(404);exit('ไม่พบไฟล์เอกสาร'); }
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime,['image/jpeg','image/png','image/gif','image/webp','application/pdf'],true)) { http_response_code(415);exit('รูปแบบไฟล์ไม่รองรับ'); }
header('Content-Type: '.$mime);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');header('Content-Length: '.filesize($path));header('Content-Disposition: '.(isset($_GET['download']) ? 'attachment' : 'inline').'; filename="'.preg_replace('/[^a-zA-Z0-9_.-]/','_',$name).'"');
readfile($path);
