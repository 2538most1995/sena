<?php
require_once '../config.php'; require_once 'auth.php'; require_once 'backup_service.php';
$conn = getDBConnection(); $message = ''; $error = ''; $preview = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SESSION['semester_notice'])) { $message=$_SESSION['semester_notice']; unset($_SESSION['semester_notice']); }
if (isset($_GET['download'])) {
    $name = basename($_GET['download']);
    if (!preg_match('/^sena_[a-zA-Z0-9_-]+\.zip$/D', $name)) { http_response_code(400); exit; }
    $path = backupDirectory() . '/' . $name;
    if (!is_file($path)) { http_response_code(404); exit; }
    header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="' . $name . '"'); header('Content-Length: ' . filesize($path)); header('Cache-Control: no-store'); readfile($path); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') try {
    if (!hash_equals(csrfToken(), $_POST['csrf'] ?? '')) throw new Exception('กรุณาโหลดหน้าใหม่');
    if (in_array($_POST['action'] ?? '', ['preview_restore', 'restore'], true)) {
        $name = $_POST['archive'] ?? '';
        $preview = inspectBackup($name); $preview['name'] = $name;
        if ($_POST['action'] === 'restore') {
            if (($_POST['confirmation'] ?? '') !== 'กู้คืน') throw new Exception('พิมพ์คำว่า กู้คืน เพื่อยืนยัน');
            $counts = restoreBackup($conn, $name);
            $message = 'กู้คืน '.$counts['restored'].' รายการ ข้ามข้อมูลที่มีอยู่แล้ว '.$counts['skipped'].' รายการ'; $preview = null;
        }
    } else {
    $term = $_POST['semester'] ?? '';
    if (!validSemester($term) && $term !== 'legacy') throw new Exception('ภาคเรียนต้องอยู่ในรูปแบบ 2/2569 หรือ 1/2569');
    if (($_POST['action'] ?? '') === 'toggle_open') {
        $open=($_POST['open'] ?? '0')==='1' ? '1' : '0';
        $stmt=$conn->prepare("UPDATE settings SET setting_value=? WHERE setting_key='registration_open'"); $stmt->bind_param('s',$open);
        if (!$stmt->execute()) throw new Exception('เปลี่ยนสถานะรับสมัครไม่ได้');
        $message=$open==='1' ? 'เปิดรับสมัครแล้ว' : 'ปิดรับสมัครแล้ว';
    } elseif (($_POST['action'] ?? '') === 'set_current' || ($_POST['action'] ?? '') === 'add_term') {
        if (!validSemester($term)) throw new Exception('เลือกภาคเรียนรับสมัครให้ถูกต้อง');
        $catalog=$conn->prepare('INSERT IGNORE INTO semesters (semester) VALUES (?)'); $catalog->bind_param('s',$term); if (!$catalog->execute()) throw new Exception('สร้างภาคเรียนไม่ได้');
        if ($_POST['action']==='add_term') { $message='เพิ่มภาคเรียน '.$term.' แล้ว'; } else {
        $stmt = $conn->prepare("UPDATE settings SET setting_value=? WHERE setting_key='current_semester'"); $stmt->bind_param('s', $term);
        if (!$stmt->execute()) throw new Exception('บันทึกภาคเรียนไม่ได้'); $message = 'เปลี่ยนภาคเรียนรับสมัครเป็น ' . $term . ' แล้ว'; }
    } elseif (($_POST['action'] ?? '') === 'assign_legacy') {
        if (!validSemester($term)) throw new Exception('ระบุภาคเรียนปลายทางให้ถูกต้อง');
        if (($_POST['confirmation'] ?? '') !== $term) throw new Exception('พิมพ์ภาคเรียนปลายทางเพื่อยืนยัน');
        $backup = backupAndClear($conn, 'legacy', null, false, $term);
        $message = 'สำรองข้อมูลเดิมและจัดเข้าภาคเรียน ' . $term . ' แล้ว';
    } elseif (in_array($_POST['action'] ?? '', ['backup','clear'], true)) {
        $clear = ($_POST['action'] ?? '') === 'clear';
        if ($clear && ($_POST['confirmation'] ?? '') !== $term) throw new Exception('พิมพ์ภาคเรียนให้ตรงก่อนล้างข้อมูล');
        $backup = backupAndClear($conn, $term, null, $clear); $message = $clear ? 'สำรอง ZIP ตรวจสอบครบแล้ว และล้างข้อมูลภาคเรียนสำเร็จ' :  'สำรองข้อมูลและเอกสารสำเร็จ';
        if ($clear && !empty($_POST['purge_files'])) {
            try { $cleanup=cleanupArchivedFiles($conn,$backup); $message.=' · ลบไฟล์ต้นฉบับ '.$cleanup['removed'].' ไฟล์ เก็บไฟล์ที่มีการใช้งาน '.$cleanup['retained'].' ไฟล์';if($cleanup['errors'])$message.=' · ลบไฟล์ไม่ได้ '.$cleanup['errors'].' ไฟล์'; }
            catch(Throwable $cleanupError) { $message.=' · ล้างทะเบียนแล้ว แต่ยังเก็บเอกสารต้นฉบับไว้เนื่องจากตรวจสอบการลบไฟล์ไม่ผ่าน'; }
        }
    } else { throw new Exception('คำสั่งไม่ถูกต้อง'); }
    }
    if ($message) { $_SESSION['semester_notice']=$message; header('Location: semesters.php'); exit; }
} catch (Throwable $e) { $error = $e->getMessage(); }
$terms = semesterOptions($conn);
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>จัดการภาคเรียน</title><link rel="stylesheet" href="../css/style.css?v=<?= assetVersion('css/style.css') ?>"></head><body>
<header class="admin-header"><h1>จัดการภาคเรียนและสำรองข้อมูล</h1><a class="btn btn-secondary" href="index.php">กลับไปทะเบียนผู้สมัคร</a></header>
<main class="admin-container">
<?php if ($message): ?><p class="notice success" role="status"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice error" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<section class="detail-card"><h2>ภาพรวมภาคเรียน</h2><div class="semester-grid"><?php foreach ($terms as $term): $counts=$conn->prepare('SELECT COUNT(*) total FROM registrations WHERE semester=?');$counts->bind_param('s',$term);$counts->execute();$count=$counts->get_result()->fetch_assoc()['total']; ?><a class="semester-card" href="index.php?semester=<?= rawurlencode($term) ?>"><span><?= htmlspecialchars(semesterLabel($term)) ?></span><strong><?= $count ?> <small>ผู้สมัคร</small></strong><?php if ($term===currentSemester($conn)): ?><span class="badge badge-approved"><?= registrationIsOpen($conn) ? 'กำลังรับสมัคร' : 'ปิดรับสมัคร' ?></span><?php endif; ?></a><?php endforeach; ?></div></section>
<section class="detail-card"><h2>ภาคเรียนที่เปิดรับสมัคร</h2><p>ขณะนี้เปิดรับสมัคร <?= htmlspecialchars(currentSemester($conn)) ?> เปลี่ยนภาคเรียนได้โดยข้อมูลเก่ายังคงอยู่</p>
<form method="post" class="filter-bar"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="set_current"><div class="form-group"><label for="new-term">ภาคเรียน / ปี พ.ศ.</label><input id="new-term" name="semester" value="<?= htmlspecialchars(currentSemester($conn)) ?>" pattern="[12]/[2-9][0-9]{3}" placeholder="2/2569" required></div><button class="btn btn-primary">บันทึกภาคเรียนรับสมัคร</button></form><form method="post" class="filter-bar"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="semester" value="<?= htmlspecialchars(currentSemester($conn)) ?>"><input type="hidden" name="action" value="toggle_open"><input type="hidden" name="open" value="<?= registrationIsOpen($conn) ? '0' : '1' ?>"><p>สถานะ: <?= registrationIsOpen($conn) ? 'เปิดรับสมัคร' : 'ปิดรับสมัคร' ?></p><button class="btn btn-secondary"><?= registrationIsOpen($conn) ? 'ปิดรับสมัครชั่วคราว' : 'เปิดรับสมัคร' ?></button></form><form method="post" class="filter-bar"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="add_term"><div class="form-group"><label for="add-term">เพิ่มภาคเรียนใหม่ (ยังไม่เปลี่ยนรอบรับสมัคร)</label><input id="add-term" name="semester" placeholder="1/2570" pattern="[12]/[2-9][0-9]{3}" required></div><button class="btn btn-secondary">เพิ่มภาคเรียน</button></form></section>
<section class="detail-card"><h2>สำรองและล้างข้อมูลรายภาคเรียน</h2><p>ZIP ประกอบด้วยข้อมูลผู้สมัครทุกช่องและไฟล์แนบ ระบบจะตรวจสอบไฟล์สำรองก่อนลบ หากสำรองไม่ครบจะยกเลิกการลบ</p><p>ข้อมูลเดิมยังไม่ระบุภาคเรียน กรุณาตรวจสอบก่อนจัดเข้าภาคเรียน หากไม่เลือก “ลบไฟล์ต้นฉบับ” ระบบจะเก็บเอกสารไว้เพิ่มเติมหลังล้างทะเบียน</p>
<form method="post" class="filter-bar"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><div class="form-group"><label for="clear-term">เลือกภาคเรียน</label><select id="clear-term" name="semester"><?php foreach ($terms as $term): ?><option value="<?= htmlspecialchars($term) ?>"><?= htmlspecialchars(semesterLabel($term)) ?></option><?php endforeach; ?></select></div><div class="form-group"><label for="confirmation">เมื่อล้างข้อมูล ให้พิมพ์ภาคเรียนยืนยัน เช่น 2/2569</label><input id="confirmation" name="confirmation" autocomplete="off"></div><label class="purge-option"><input type="checkbox" name="purge_files" value="1"> ลบไฟล์ต้นฉบับหลังสำรองสำเร็จด้วย (เก็บไฟล์ที่ผู้สมัครอื่นยังใช้ไว้)</label><button name="action" value="backup" class="btn btn-secondary">สำรอง ZIP เท่านั้น</button><button name="action" value="clear" class="btn btn-danger">สำรองก่อน แล้วล้างข้อมูล</button></form></section>
<section class="detail-card"><h2>จัดข้อมูลเดิมเข้าภาคเรียน</h2><p>ใช้เมื่อได้ตรวจสอบแล้วว่าผู้สมัครที่ยังไม่ระบุภาคเรียนทั้งหมดเป็นภาคเรียนเดียวกัน ระบบสำรองก่อนย้าย หากข้อมูลเดิมมีหลายภาคเรียนให้ตรวจแยกรายการก่อน</p><form method="post" class="filter-bar"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="assign_legacy"><div class="form-group"><label for="legacy-term">ภาคเรียนปลายทาง</label><input id="legacy-term" name="semester" placeholder="1/2569" pattern="[12]/[2-9][0-9]{3}" required></div><div class="form-group"><label for="legacy-confirm">พิมพ์ภาคเรียนปลายทางอีกครั้ง</label><input id="legacy-confirm" name="confirmation" required></div><button class="btn btn-primary">สำรองแล้วจัดข้อมูลเดิมเข้าภาคเรียน</button></form></section>
<?php if ($preview): ?><section class="detail-card"><h2>ตรวจสอบก่อนกู้คืน</h2><p>ไฟล์ <?= htmlspecialchars($preview['name']) ?> ผ่านการตรวจสอบ มีผู้สมัคร <?= count($preview['data']['registrations']) ?> รายการ ภาคเรียน <?= htmlspecialchars(implode(', ', array_unique(array_column($preview['data']['registrations'], 'semester')))) ?></p><p>ระบบกู้คืนเฉพาะรายการที่ยังไม่มีเลขบัตรในภาคเรียนเดียวกัน จะข้ามข้อมูลเดิม และกำหนดหมายเลขรายการใหม่</p><form method="post" class="filter-bar"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="archive" value="<?= htmlspecialchars($preview['name']) ?>"><div class="form-group"><label for="restore-confirm">พิมพ์คำว่า กู้คืน</label><input id="restore-confirm" name="confirmation" required autocomplete="off"></div><button class="btn btn-primary">ยืนยันกู้คืนข้อมูลและเอกสาร</button></form></section><?php endif; ?>
<section class="detail-card"><h2>ไฟล์สำรองที่พร้อมดาวน์โหลด</h2><p>เก็บในพื้นที่ส่วนตัวของเซิร์ฟเวอร์ ดาวน์โหลดได้เฉพาะเจ้าหน้าที่</p><div class="backup-list"><?php $files = glob(backupDirectory() . '/sena_*.zip'); rsort($files); if (!$files): ?><p>ยังไม่มีไฟล์สำรอง</p><?php endif; foreach ($files as $file): ?><a href="?download=<?= rawurlencode(basename($file)) ?>"><?= htmlspecialchars(basename($file)) ?> · <?= number_format(filesize($file)/1048576, 2) ?> MB</a><form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="preview_restore"><input type="hidden" name="archive" value="<?= htmlspecialchars(basename($file)) ?>"><button class="btn btn-secondary btn-sm">ตรวจสอบและกู้คืนไฟล์นี้</button></form><?php endforeach; ?></div></section>
</main></body></html>
