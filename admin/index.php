<?php
require_once '../config.php';
require_once 'auth.php';
$conn = getDBConnection();

$filterSemester = $_GET['semester'] ?? currentSemester($conn);
if (!validSemester($filterSemester) && $filterSemester !== 'legacy') $filterSemester = currentSemester($conn);
// Get filter parameters
$filterLevel = $_GET['level'] ?? '';
$filterCenter = $_GET['center'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

// Build query
$where = ['semester = ?'];
$params = [$filterSemester];
$types = 's';

if ($filterLevel) {
    $where[] = "education_level = ?";
    $params[] = $filterLevel;
    $types .= 's';
}
if ($filterCenter) {
    $where[] = "subdistrict_center = ?";
    $params[] = $filterCenter;
    $types .= 's';
}
if ($filterStatus) {
    $where[] = "status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}
if ($search !== '') {
    if (preg_match('/^[0-9]{13}$/D', $search)) {
        $where[] = 'id_card_number = ?'; $params[] = $search; $types .= 's';
    } else {
        $where[] = '(first_name LIKE ? OR last_name LIKE ? OR id_card_number LIKE ?)';
        $searchParam = "%$search%";
        array_push($params, $searchParam, $searchParam, $searchParam); $types .= 'sss';
    }
}

// Fetch only fields rendered by the list, not full addresses and family records.
$sql = 'SELECT id,title,first_name,last_name,education_level,subdistrict_center,id_card_number,created_at,status,photo_file,id_card_file,house_reg_file FROM registrations WHERE ' . implode(' AND ', $where);

// Statistics scoped to the selected semester; also reuse this count without filters.
$stats = $conn->prepare("SELECT COUNT(*) total, COALESCE(SUM(status='pending'),0) pending, COALESCE(SUM(status='approved'),0) approved, COALESCE(SUM(status='rejected'),0) rejected FROM registrations WHERE semester=?");
$stats->bind_param('s', $filterSemester); $stats->execute();
extract($stats->get_result()->fetch_assoc());
if (count($where) === 1) { $filteredTotal=(int)$total; } else {

$countStmt=$conn->prepare('SELECT COUNT(*) total FROM registrations WHERE '.implode(' AND ',$where));
$countStmt->bind_param($types,...$params);$countStmt->execute();$filteredTotal=(int)$countStmt->get_result()->fetch_assoc()['total'];
}
$perPage=20;$pages=max(1,(int)ceil($filteredTotal/$perPage));$page=max(1,min($pages,(int)($_GET['page'] ?? 1)));$offset=($page-1)*$perPage;
$sql .= " ORDER BY created_at DESC, id DESC LIMIT $perPage OFFSET $offset";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$registrations = $result->fetch_all(MYSQLI_ASSOC);

$thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้สมัคร | สกร.ระดับอำเภอเสนา</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= assetVersion('css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <div class="admin-header">
        <h1><i class="fas fa-user-gear" style="color:var(--primary-light)"></i> จัดการผู้สมัครเรียน</h1>
        <div style="display:flex;gap:8px;">
            <?php
            $exportQuery = htmlspecialchars(http_build_query(array_merge($_GET, ['semester' => $filterSemester])));
            ?>
            <a href="export.php?<?= $exportQuery ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> นำออก Excel</a>
            <a href="settings.php" class="btn btn-info btn-sm"><i class="fas fa-cog"></i> ตั้งค่า</a>
            <a href="../" class="btn btn-secondary btn-sm"><i class="fas fa-plus"></i> สมัครใหม่</a>
            <a href="logout.php" class="btn btn-danger btn-sm"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
        </div>
    </div>

    <div class="admin-container">
        <div class="page-intro"><p>ทะเบียนรับสมัคร</p><h2>ภาคเรียน <?= htmlspecialchars(semesterLabel($filterSemester)) ?></h2><span>ตรวจสอบข้อมูลและเอกสารของผู้สมัครในภาคเรียนที่เลือก</span><a class="btn btn-secondary btn-sm" href="semesters.php">จัดการภาคเรียน / สำรองและล้างข้อมูล</a></div>
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $total ?></div>
                <div class="stat-label">ผู้สมัครทั้งหมด</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="background:linear-gradient(135deg, var(--warning), #d97706);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"><?= $pending ?></div>
                <div class="stat-label">รอดำเนินการ</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="background:linear-gradient(135deg, var(--success), #059669);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"><?= $approved ?></div>
                <div class="stat-label">อนุมัติแล้ว</div>
            </div>
        </div>

<p class="result-count">พบ <?= $filteredTotal ?> รายการ · หน้า <?= $page ?> / <?= $pages ?></p>
        <!-- Filter Bar -->
        <details class="registration-filters" open><summary><i class="fas fa-filter" aria-hidden="true"></i> ค้นหาและกรองผู้สมัคร <span>เลือกภาคเรียน / สถานะ</span></summary>
        <form class="filter-bar" method="GET">
            <div class="form-group"><label for="semester">ภาคเรียน</label><select name="semester" id="semester"><?php foreach (semesterOptions($conn) as $term): ?><option value="<?= htmlspecialchars($term) ?>" <?= $term === $filterSemester ? 'selected' : '' ?>><?= htmlspecialchars(semesterLabel($term)) ?></option><?php endforeach; ?></select></div>
            <div class="form-group">
                <label>ค้นหา</label>
                <input type="text" name="search" placeholder="ชื่อ, นามสกุล, เลขบัตร..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="form-group">
                <label>ระดับ</label>
                <select name="level">
                    <option value="">ทั้งหมด</option>
                    <option value="ประถม" <?= $filterLevel === 'ประถม' ? 'selected' : '' ?>>ประถม</option>
                    <option value="ม.ต้น" <?= $filterLevel === 'ม.ต้น' ? 'selected' : '' ?>>ม.ต้น</option>
                    <option value="ม.ปลาย" <?= $filterLevel === 'ม.ปลาย' ? 'selected' : '' ?>>ม.ปลาย</option>
                </select>
            </div>
            <div class="form-group">
                <label>ศกร.ตำบล</label>
                <select name="center">
                    <option value="">ทั้งหมด</option>
                    <option value="ศกร.ระดับตำบลสามกอ" <?= $filterCenter === 'ศกร.ระดับตำบลสามกอ' ? 'selected' : '' ?>>สามกอ</option>
                    <option value="ศกร.ระดับตำบลบ้านหลวง" <?= $filterCenter === 'ศกร.ระดับตำบลบ้านหลวง' ? 'selected' : '' ?>>บ้านหลวง</option>
                    <option value="ศกร.ระดับตำบลบ้านแพน" <?= $filterCenter === 'ศกร.ระดับตำบลบ้านแพน' ? 'selected' : '' ?>>บ้านแพน</option>
                    <option value="ศกร.ระดับตำบลบางนมโค" <?= $filterCenter === 'ศกร.ระดับตำบลบางนมโค' ? 'selected' : '' ?>>บางนมโค</option>
                    <option value="ศกร.ระดับตำบลสามตุ่ม" <?= $filterCenter === 'ศกร.ระดับตำบลสามตุ่ม' ? 'selected' : '' ?>>สามตุ่ม</option>
                    <option value="ศกร.ระดับตำบลบ้านกระทุ่ม" <?= $filterCenter === 'ศกร.ระดับตำบลบ้านกระทุ่ม' ? 'selected' : '' ?>>บ้านกระทุ่ม</option>
                    <option value="ศกร.ระดับตำบลหัวเวียง" <?= $filterCenter === 'ศกร.ระดับตำบลหัวเวียง' ? 'selected' : '' ?>>หัวเวียง</option>
                    <option value="ศกร.ระดับตำบลมารวิชัย" <?= $filterCenter === 'ศกร.ระดับตำบลมารวิชัย' ? 'selected' : '' ?>>มารวิชัย</option>
                    <option value="ศกร.ระดับตำบลชายนา" <?= $filterCenter === 'ศกร.ระดับตำบลชายนา' ? 'selected' : '' ?>>ชายนา</option>
                    <option value="ศกร.ระดับตำบลรางจระเข้" <?= $filterCenter === 'ศกร.ระดับตำบลรางจระเข้' ? 'selected' : '' ?>>รางจระเข้</option>
                    <option value="ศกร.ระดับตำบลดอนทอง" <?= $filterCenter === 'ศกร.ระดับตำบลดอนทอง' ? 'selected' : '' ?>>ดอนทอง</option>
                    <option value="ศกร.ระดับตำบลบ้านแถว" <?= $filterCenter === 'ศกร.ระดับตำบลบ้านแถว' ? 'selected' : '' ?>>บ้านแถว</option>
                    <option value="ศกร.ระดับตำบลเจ้าเจ็ด" <?= $filterCenter === 'ศกร.ระดับตำบลเจ้าเจ็ด' ? 'selected' : '' ?>>เจ้าเจ็ด</option>
                    <option value="ศกร.ระดับตำบลเจ้าเสด็จ" <?= $filterCenter === 'ศกร.ระดับตำบลเจ้าเสด็จ' ? 'selected' : '' ?>>เจ้าเสด็จ</option>
                    <option value="ศกร.ระดับตำบลลาดงา" <?= $filterCenter === 'ศกร.ระดับตำบลลาดงา' ? 'selected' : '' ?>>ลาดงา</option>
                    <option value="ศกร.ระดับตำบลบ้านโพธิ์" <?= $filterCenter === 'ศกร.ระดับตำบลบ้านโพธิ์' ? 'selected' : '' ?>>บ้านโพธิ์</option>
                    <option value="ศกร.ระดับตำบลเสนา" <?= $filterCenter === 'ศกร.ระดับตำบลเสนา' ? 'selected' : '' ?>>เสนา</option>
                    <option value="ศูนย์อำเภอ" <?= $filterCenter === 'ศูนย์อำเภอ' ? 'selected' : '' ?>>ศูนย์อำเภอ</option>
                </select>
            </div>
            <div class="form-group">
                <label>สถานะ</label>
                <select name="status">
                    <option value="">ทั้งหมด</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>รอดำเนินการ</option>
                    <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>อนุมัติ</option>
                    <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>ไม่อนุมัติ</option>
                </select>
            </div>
            <div class="form-group" style="flex:0;">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> ค้นหา</button>
            </div>
        </form></details>
        <script>const filterPanel=document.querySelector('.registration-filters');const phoneFilters=matchMedia('(max-width:767px)');filterPanel.open=!phoneFilters.matches;phoneFilters.addEventListener('change',event=>{filterPanel.open=!event.matches;});</script>

        <!-- Data Table -->
        <div class="data-table-wrapper">
            <?php if (empty($registrations)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <p>ไม่พบข้อมูลผู้สมัคร</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>รูป</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>ระดับ</th>
                            <th>ศกร.ตำบล</th>
                            <th>เลขบัตร</th>
                            <th>วันที่สมัคร</th>
                            <th>เอกสารหลัก</th><th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $idx => $reg): ?>
                            <tr>
                                <td data-label="ลำดับ" class="applicant-order"><?= $offset + $idx + 1 ?></td>
                                <td data-label="รูปถ่าย" class="applicant-photo">
                                    <?php if ($reg['photo_file']): ?>
                                        <img src="document.php?id=<?= $reg['id'] ?>&amp;field=photo_file" class="avatar" alt="รูปถ่ายผู้สมัคร" loading="lazy" decoding="async" width="52" height="64">
                                    <?php else: ?>
                                        <div class="avatar" style="background:var(--card-bg);display:flex;align-items:center;justify-content:center;font-size:16px;">👤</div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="ชื่อ-นามสกุล" class="applicant-name"><strong><?= htmlspecialchars($reg['title'] . $reg['first_name'] . ' ' . $reg['last_name']) ?></strong></td>
                                <td data-label="ระดับ"><?= htmlspecialchars($reg['education_level']) ?></td>
                                <td data-label="ศกร.ตำบล" style="font-size:0.75rem;"><?= htmlspecialchars(str_replace('ศกร.ระดับตำบล', '', $reg['subdistrict_center'])) ?></td>
                                <td data-label="เลขบัตร" style="font-family:monospace;font-size:0.8rem;"><?= htmlspecialchars($reg['id_card_number']) ?></td>
                                <td data-label="วันที่สมัคร" style="font-size:0.8rem;">
                                    <?php
                                    $dt = new DateTime($reg['created_at']);
                                    $d = intval($dt->format('d'));
                                    $m = intval($dt->format('m'));
                                    $y = intval($dt->format('Y')) + 543;
                                    echo "$d {$thaiMonths[$m]} $y";
                                    ?>
                                </td>
                                <td data-label="เอกสารหลัก"><?php $complete=0;foreach (['photo_file'=>'photos','id_card_file'=>'id_cards','house_reg_file'=>'house_registrations'] as $field=>$folder) if (!empty($reg[$field]) && is_file(UPLOAD_DIR.$folder.'/'.basename($reg[$field]))) $complete++; ?><span class="badge <?= $complete===3 ? 'badge-approved' : 'badge-pending' ?>"><?= $complete ?>/3</span></td>
                                <td data-label="สถานะ">
                                    <?php
                                    $statusClass = 'badge-' . $reg['status'];
                                    $statusText = ['pending' => 'รอดำเนินการ', 'approved' => 'อนุมัติ', 'rejected' => 'ไม่อนุมัติ'];
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= $statusText[$reg['status']] ?? $reg['status'] ?></span>
                                </td>
                                <td data-label="จัดการ" class="applicant-actions">
                                    <a href="edit.php?id=<?= $reg['id'] ?>" class="btn-icon" title="แก้ไขข้อมูล" aria-label="แก้ไขข้อมูล"><i class="fas fa-pen" aria-hidden="true"></i><span class="action-label">แก้ไข</span></a>
                                    <a href="view.php?id=<?= $reg['id'] ?>" class="btn-icon" title="ดูรายละเอียด" aria-label="ดูรายละเอียด"><i class="fas fa-eye" aria-hidden="true"></i><span class="action-label">ดูรายละเอียด</span></a>
                                    <?php if ($reg['status'] === 'pending'): ?>
                                        <button class="btn-icon" onclick="updateStatus(<?= $reg['id'] ?>, 'approved')" title="อนุมัติ" style="color:var(--success);"><i class="fas fa-check" aria-hidden="true"></i><span class="action-label">อนุมัติ</span></button>
                                        <button class="btn-icon" onclick="updateStatus(<?= $reg['id'] ?>, 'rejected')" title="ไม่อนุมัติ" style="color:var(--danger);"><i class="fas fa-times" aria-hidden="true"></i><span class="action-label">ไม่อนุมัติ</span></button>
                                    <?php endif; ?>
                                    <button class="btn-icon" onclick="deleteRegistration(<?= $reg['id'] ?>)" title="ลบ" aria-label="ลบผู้สมัคร" style="color:var(--danger);"><i class="fas fa-trash" aria-hidden="true"></i><span class="action-label">ลบ</span></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <nav class="pagination admin-container" aria-label="หน้ารายการผู้สมัคร"><?php if ($page>1): ?><a class="btn btn-secondary btn-sm" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET,['semester'=>$filterSemester,'page'=>$page-1]))) ?>">หน้าก่อนหน้า</a><?php endif; ?><span>หน้า <?= $page ?> จาก <?= $pages ?></span><?php if ($page<$pages): ?><a class="btn btn-secondary btn-sm" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET,['semester'=>$filterSemester,'page'=>$page+1]))) ?>">หน้าถัดไป</a><?php endif; ?></nav>
    <div class="footer">
        &copy; <?= date('Y') + 543 ?> สกร.ระดับอำเภอเสนา
    </div>

    <script>
        function updateStatus(id, status) {
            const statusText = status === 'approved' ? 'อนุมัติ' : 'ไม่อนุมัติ';
            Swal.fire({
                title: `ยืนยัน${statusText}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: status === 'approved' ? '#10b981' : '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: `ใช่, ${statusText}`,
                cancelButtonText: 'ยกเลิก',
                background: '#ffffff',
                color: '#203c35'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('action.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `action=update_status&id=${id}&status=${status}&csrf=<?= csrfToken() ?>`
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                        icon: 'success',
                                        title: 'สำเร็จ',
                                        text: data.message,
                                        background: '#ffffff',
                                        color: '#203c35',
                                        confirmButtonColor: '#126454'
                                    })
                                    .then(() => location.reload());
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ผิดพลาด',
                                    text: data.message,
                                    background: '#ffffff',
                                    color: '#203c35',
                                    confirmButtonColor: '#126454'
                                });
                            }
                        }).catch(()=>Swal.fire('เชื่อมต่อไม่สำเร็จ','กรุณาลองใหม่','error'));
                }
            });
        }

        function deleteRegistration(id) {
            Swal.fire({
                title: 'ลบข้อมูลผู้สมัคร?',
                text: 'เลือกวิธีลบข้อมูลผู้สมัครรายนี้ ไฟล์แนบต้นฉบับยังเก็บไว้บนเซิร์ฟเวอร์',
                showDenyButton: true,
                denyButtonText: 'ลบและสำรอง',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'ลบโดยไม่สำรอง',
                cancelButtonText: 'ยกเลิก',
                background: '#ffffff',
                color: '#203c35'
            }).then((result) => {
                if (result.isConfirmed || result.isDenied) {
                    fetch('action.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `action=delete&id=${id}&delete_mode=${result.isDenied ? 'backup' : 'delete'}&csrf=<?= csrfToken() ?>`
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                        icon: 'success',
                                        title: 'ลบข้อมูลสำเร็จ',
                                        text: data.message,
                                        background: '#ffffff',
                                        color: '#203c35',
                                        confirmButtonColor: '#126454'
                                    })
                                    .then(() => location.reload());
                            } else { Swal.fire('ดำเนินการไม่สำเร็จ', data.message, 'error'); }
                        }).catch(() => Swal.fire('เชื่อมต่อไม่สำเร็จ', 'กรุณาลองใหม่', 'error'));
                }
            });
        }
    </script>
</body>

</html>