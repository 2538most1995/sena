<?php
// Backups live outside the web root; downloads always require staff authentication.
function backupDirectory() {
    $dir = getenv('SENA_BACKUP_DIR') ?: dirname(__DIR__, 2) . '/../sena-private-backups';
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new Exception('สร้างพื้นที่สำรองไม่ได้');
    if (!is_writable($dir)) throw new Exception('พื้นที่สำรองเขียนไม่ได้');
    return $dir;
}
function backupAndClear($conn, $semester = null, $id = null, $clear = false, $newSemester = null) {
    if (!class_exists('ZipArchive')) throw new Exception('ไม่พบส่วนขยาย ZIP จึงยังไม่สามารถลบข้อมูลได้');
    $conn->begin_transaction();
    $path = null;
    try {
        $stmt = $conn->prepare('SELECT * FROM registrations WHERE ' . ($id !== null ? 'semester = (SELECT semester FROM (SELECT semester FROM registrations WHERE id = ?) AS selected_registration)' : 'semester = ?') . ' ORDER BY id FOR UPDATE');
        if ($id !== null) $stmt->bind_param('i', $id); else $stmt->bind_param('s', $semester);
        if (!$stmt->execute()) throw new Exception('อ่านข้อมูลสำรองไม่สำเร็จ');
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if (!$rows) throw new Exception('ไม่พบข้อมูลสำหรับสำรอง');
        $name = 'sena_' . str_replace('/', '-', $rows[0]['semester']) . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.zip';
        $path = backupDirectory() . '/' . $name;
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new Exception('สร้าง ZIP ไม่สำเร็จ');
        $json = json_encode(['format' => 'sena-registration-v1', 'created_at' => date(DATE_ATOM), 'registrations' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (!$zip->addFromString('registrations.json', $json)) throw new Exception('สำรองข้อมูลไม่สำเร็จ');
        $map = ['photo_file'=>'photos', 'id_card_file'=>'id_cards', 'house_reg_file'=>'house_registrations', 'certificate_file'=>'certificates', 'certificate_back_file'=>'certificates'];
        $stream=fopen('php://temp','w+'); fwrite($stream,"\xEF\xBB\xBF");
        fputcsv($stream,array_keys($rows[0]));
        foreach ($rows as $row) fputcsv($stream,array_map(function($value) {
            $text=(string)($value ?? '');
            return preg_match('/^[=+@\-\t\r]/',$text) || preg_match('/^0[0-9]+$/D',$text) || preg_match('/^[0-9]{13}$/D',$text) ? "'".$text : $text;
        },array_values($row)));
        rewind($stream);$csv=stream_get_contents($stream);fclose($stream);
        if (!$zip->addFromString('registrations.csv',$csv)) throw new Exception('สำรอง CSV ไม่สำเร็จ');
        $hashes = ['registrations.json' => hash('sha256', $json), 'registrations.csv'=>hash('sha256',$csv)];
        foreach ($rows as $row) foreach ($map as $field => $folder) {
            if (empty($row[$field])) continue;
            $file = UPLOAD_DIR . $folder . '/' . basename($row[$field]);
            if (!is_file($file) || !is_readable($file)) throw new Exception('เอกสารแนบไม่ครบ จึงยกเลิกการลบ: ผู้สมัคร #' . $row['id']);
            $entry = 'uploads/' . $folder . '/' . basename($row[$field]);
            $hashes[$entry] = hash_file('sha256', $file);
            if (!$zip->addFile($file, $entry)) throw new Exception('สำรองเอกสารไม่สำเร็จ');
        }
        if (!$zip->addFromString('checksums.json', json_encode($hashes, JSON_PRETTY_PRINT)) || !$zip->close()) throw new Exception('บันทึก ZIP ไม่สำเร็จ');
        chmod($path, 0600);
        $check = new ZipArchive();
        if ($check->open($path, ZipArchive::CHECKCONS) !== true) throw new Exception('ตรวจสอบ ZIP ไม่ผ่าน');
        foreach ($hashes as $entry => $hash) {
            $contents = $check->getFromName($entry);
            if ($contents === false || !hash_equals($hash, hash('sha256', $contents))) throw new Exception('ตรวจสอบไฟล์สำรองไม่ผ่าน');
        }
        $check->close();
        if ($clear) {
            // Delete only the rows actually included in the verified archive.
            $delete = $conn->prepare('DELETE FROM registrations WHERE id=?');
            foreach ($rows as $row) { if ($id !== null && (int)$row['id'] !== $id) continue; $rid = (int)$row['id']; $delete->bind_param('i', $rid); if (!$delete->execute()) throw new Exception('ลบข้อมูลไม่สำเร็จ'); }
        }
        if ($newSemester !== null) {
            $move = $conn->prepare('UPDATE registrations SET semester=? WHERE id=?');
            foreach ($rows as $row) { if ($id !== null && (int)$row['id'] !== $id) continue; $rid = (int)$row['id']; $move->bind_param('si', $newSemester, $rid); if (!$move->execute()) throw new Exception('ย้ายภาคเรียนไม่สำเร็จ อาจมีเลขบัตรซ้ำในภาคเรียนปลายทาง'); }
        }
        if (!$conn->commit()) throw new Exception('บันทึกการทำรายการไม่สำเร็จ');
        // Retain source attachments as an additional recovery copy.
        return $name;
    } catch (Throwable $e) {
        if (isset($zip)) { try { $zip->close(); } catch (Throwable $closeError) {} }
        $conn->rollback();
        if ($path && is_file($path)) unlink($path);
        throw $e;
    }
}

function inspectBackup($name) {
    if (!is_string($name) || !preg_match('/^sena_[a-zA-Z0-9_-]+\.zip$/D', $name)) throw new Exception('ชื่อไฟล์สำรองไม่ถูกต้อง');
    $zip = new ZipArchive();
    if ($zip->open(backupDirectory() . '/' . $name, ZipArchive::CHECKCONS) !== true) throw new Exception('เปิดไฟล์สำรองไม่ได้');
    try {
        $raw = $zip->getFromName('registrations.json');
        $hashes = json_decode($zip->getFromName('checksums.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($hashes) || !isset($hashes['registrations.json'])) throw new Exception('ไฟล์สำรองไม่มีรายการตรวจสอบ');
        foreach ($hashes as $entry => $hash) {
            if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) throw new Exception('รูปแบบผลตรวจสอบไม่ถูกต้อง');
            $contents = $zip->getFromName($entry);
            if ($contents === false || !hash_equals($hash, hash('sha256', $contents))) throw new Exception('ไฟล์สำรองเสียหาย จึงกู้คืนไม่ได้');
        }
        $data = json_decode($raw ?: '', true, 512, JSON_THROW_ON_ERROR);
        if (($data['format'] ?? '') !== 'sena-registration-v1' || empty($data['registrations']) || !is_array($data['registrations'])) throw new Exception('รูปแบบข้อมูลสำรองไม่รองรับ');
        $map = ['photo_file'=>'photos', 'id_card_file'=>'id_cards', 'house_reg_file'=>'house_registrations', 'certificate_file'=>'certificates', 'certificate_back_file'=>'certificates'];
        foreach ($data['registrations'] as $row) {
            if (!is_array($row) || (!validSemester($row['semester'] ?? '') && ($row['semester'] ?? '') !== 'legacy')) throw new Exception('ข้อมูลภาคเรียนในไฟล์ไม่ถูกต้อง');
            foreach ($row as $value) if (!is_scalar($value) && $value !== null) throw new Exception('ข้อมูลสำรองไม่ถูกต้อง');
            foreach ($map as $field=>$folder) if (!empty($row[$field])) {
                $filename = $row[$field];
                if ($filename !== basename($filename) || !preg_match('/^[a-zA-Z0-9_.-]+\.(jpe?g|png|gif|webp|pdf)$/iD', $filename)) throw new Exception('ชื่อเอกสารในไฟล์สำรองไม่ปลอดภัย');
                $entry = 'uploads/' . $folder . '/' . $filename;
                if (!isset($hashes[$entry])) throw new Exception('ไฟล์สำรองมีเอกสารไม่ครบ');
            }
        }
        return ['data'=>$data, 'hashes'=>$hashes];
    } finally { $zip->close(); }
}

function restoreBackup($conn, $name) {
    $archive = inspectBackup($name);
    $zip = new ZipArchive();
    if ($zip->open(backupDirectory().'/'.$name) !== true) throw new Exception('เปิดไฟล์สำรองไม่ได้');
    $created = []; $restored = 0; $skipped = 0;
    if (!$conn->begin_transaction()) throw new Exception('เริ่มการกู้คืนไม่ได้');
    try {
        $columns = [];
        $result = $conn->query('SHOW COLUMNS FROM registrations');
        while ($column = $result->fetch_assoc()) if ($column['Field'] !== 'id') $columns[] = $column['Field'];
        $find = $conn->prepare('SELECT id FROM registrations WHERE semester=? AND id_card_number=? FOR UPDATE');
        $insert = $conn->prepare('INSERT INTO registrations (`'.implode('`,`', $columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')');
        foreach ($archive['data']['registrations'] as $row) {
            foreach ($columns as $column) if (!array_key_exists($column,$row)) throw new Exception('ข้อมูลสำรองไม่ครบทุกช่อง');
            $find->bind_param('ss', $row['semester'], $row['id_card_number']); $find->execute();
            if ($find->get_result()->num_rows) { $skipped++; continue; }
            foreach (['photo_file'=>'photos','id_card_file'=>'id_cards','house_reg_file'=>'house_registrations','certificate_file'=>'certificates','certificate_back_file'=>'certificates'] as $field=>$folder) {
                if (empty($row[$field])) continue;
                $entry = 'uploads/'.$folder.'/'.$row[$field]; $path = UPLOAD_DIR.$folder.'/'.$row[$field];
                if (is_file($path)) {
                    if (!hash_equals($archive['hashes'][$entry],hash_file('sha256',$path))) throw new Exception('ไฟล์เดิมชื่อซ้ำแต่เนื้อหาต่างกัน จึงยกเลิกการกู้คืน');
                } else {
                    ensureUploadDirs();
                    $contents = $zip->getFromName($entry);
                    $handle = @fopen($path,'x');
                    if (!$handle) throw new Exception('เขียนไฟล์กู้คืนไม่ได้');
                    $created[]=$path;
                    try { if (fwrite($handle,$contents) !== strlen($contents)) throw new Exception('เขียนไฟล์กู้คืนไม่ครบ'); } finally { fclose($handle); }
                    if (!hash_equals($archive['hashes'][$entry],hash_file('sha256',$path))) throw new Exception('ตรวจสอบไฟล์กู้คืนไม่ผ่าน');
                }
            }
            $values = array_map(fn($key)=>$row[$key],$columns);
            $insert->bind_param(str_repeat('s',count($values)),...$values);
            if (!$insert->execute()) throw new Exception('บันทึกข้อมูลกู้คืนไม่ได้');
            $restored++;
        }
        if (!$conn->commit()) throw new Exception('บันทึกการกู้คืนไม่สำเร็จ');
        return ['restored'=>$restored,'skipped'=>$skipped];
    } catch (Throwable $e) {
        $conn->rollback(); foreach ($created as $path) @unlink($path); throw $e;
    } finally { $zip->close(); }
}

function cleanupArchivedFiles($conn,$name) {
    $archive=inspectBackup($name);$counts=['removed'=>0,'retained'=>0,'errors'=>0];$seen=[];
    $stmt=$conn->prepare('SELECT id FROM registrations WHERE photo_file=? OR id_card_file=? OR house_reg_file=? OR certificate_file=? OR certificate_back_file=? LIMIT 1');
    foreach ($archive['data']['registrations'] as $row) foreach (['photo_file'=>'photos','id_card_file'=>'id_cards','house_reg_file'=>'house_registrations','certificate_file'=>'certificates','certificate_back_file'=>'certificates'] as $field=>$folder) {
        if(empty($row[$field]))continue;$filename=$row[$field];$entry='uploads/'.$folder.'/'.$filename;
        if(isset($seen[$entry]))continue;$seen[$entry]=true;$path=UPLOAD_DIR.$folder.'/'.$filename;
        if(!is_file($path))continue;
        $stmt->bind_param('sssss',$filename,$filename,$filename,$filename,$filename);$stmt->execute();
        if($stmt->get_result()->num_rows || !hash_equals($archive['hashes'][$entry],hash_file('sha256',$path))) { $counts['retained']++;continue; }
        if(@unlink($path))$counts['removed']++;else $counts['errors']++;
    }
    return $counts;
}
