<?php
require_once '../config.php';
require_once 'auth.php';
$conn = getDBConnection();

$filterSemester = $_GET['semester'] ?? currentSemester($conn);
if (!validSemester($filterSemester) && $filterSemester !== 'legacy') $filterSemester = currentSemester($conn);
// Get filter parameters (same as index.php)
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
if ($search) {
    $where[] = "(first_name LIKE ? OR last_name LIKE ? OR id_card_number LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

$sql = "SELECT * FROM registrations";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$registrations = $result->fetch_all(MYSQLI_ASSOC);

// File headers for CSV download
$filename = "Sena_VSD_Registrations_" . date('Y-m-d_H-i') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Write BOM for Excel compatibility with Thai characters
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Headers
fputcsv($output, [
    'ลำดับ',
    'ภาคเรียน',
    'วันที่สมัคร',
    'สถานะ',
    'ระดับที่สมัคร',
    'ศกร.ระดับตำบล',
    'คำนำหน้า',
    'ชื่อ',
    'นามสกุล',
    'เลขบัตรประชาชน',
    'วันเกิด',
    'อายุ',
    'เบอร์โทร (ตามทะเบียนบ้าน)',
    'เบอร์โทร (ติดต่อได้)',
    'Facebook',
    'Line ID',
    'กลุ่มเป้าหมาย'
]);

function csvSafe($value) { $value=(string)($value ?? ''); return preg_match('/^[=+@\-\t\r]/',$value) ? "'".$value : $value; }
// CSV Data
foreach ($registrations as $idx => $reg) {
    $statusText = ['pending' => 'รอดำเนินการ', 'approved' => 'อนุมัติ', 'rejected' => 'ไม่อนุมัติ'];
    
    fputcsv($output, array_map('csvSafe', [
        $idx + 1,
        semesterLabel($reg['semester']),
        $reg['created_at'],
        $statusText[$reg['status']] ?? $reg['status'],
        $reg['education_level'],
        $reg['subdistrict_center'],
        $reg['title'],
        $reg['first_name'],
        $reg['last_name'],
        "\t" . $reg['id_card_number'], // Add tab to prevent Excel from scientific notation
        $reg['birth_date'],
        $reg['age'],
        $reg['reg_phone'],
        $reg['cur_phone'],
        $reg['facebook'],
        $reg['line_id'],
        $reg['target_group']
    ]));
}

fclose($output);
exit;
