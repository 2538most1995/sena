<?php
// Ensure we always return JSON, even on PHP fatal errors
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't output errors to browser

header('Content-Type: application/json; charset=utf-8');

// Global error handler
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Global exception handler (catches uncaught exceptions/errors)
set_exception_handler(function ($e) {
    ob_end_clean();
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
    exit;
});

require_once 'config.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$uploadedPaths = [];
try {
    $conn = getDBConnection();
    ensureUploadDirs();

    // ===== Collect form data =====
    $education_level     = trim($_POST['education_level'] ?? '');
    $subdistrict_center  = trim($_POST['subdistrict_center'] ?? '');
    $title               = trim($_POST['title'] ?? '');
    $first_name          = trim($_POST['first_name'] ?? '');
    $last_name           = trim($_POST['last_name'] ?? '');
    $id_card_number      = trim($_POST['id_card_number'] ?? '');
    $religion            = trim($_POST['religion'] ?? '');
    $nationality         = trim($_POST['nationality'] ?? '');
    $current_occupation  = trim($_POST['current_occupation'] ?? '');
    $target_group        = trim($_POST['target_group'] ?? '');

    // Date of birth (convert Thai Buddhist year to CE)
    $birth_day   = intval($_POST['birth_day'] ?? 0);
    $birth_month = intval($_POST['birth_month'] ?? 0);
    $birth_year  = intval($_POST['birth_year'] ?? 0);
    $birth_year_ce = $birth_year - 543;
    if (!checkdate($birth_month, $birth_day, $birth_year_ce)) throw new Exception('วันเกิดไม่ถูกต้อง');
    $birth_date = sprintf('%04d-%02d-%02d', $birth_year_ce, $birth_month, $birth_day);

    // Calculate age
    $birthDateObj = new DateTime($birth_date);
    $today = new DateTime();
    if ($birthDateObj > $today) throw new Exception('วันเกิดต้องไม่เป็นวันในอนาคต');
    $age = $today->diff($birthDateObj)->y;

    // Family
    $father_first_name  = trim($_POST['father_first_name'] ?? '');
    $father_last_name   = trim($_POST['father_last_name'] ?? '');
    $mother_first_name  = trim($_POST['mother_first_name'] ?? '');
    $mother_last_name   = trim($_POST['mother_last_name'] ?? '');

    // Previous education
    $previous_education_level = trim($_POST['previous_education_level'] ?? '');
    $graduation_year          = trim($_POST['graduation_year'] ?? '');
    $previous_school          = trim($_POST['previous_school'] ?? '');
    $previous_district        = trim($_POST['previous_district'] ?? '');
    $previous_province        = trim($_POST['previous_province'] ?? '');

    // Registered address
    $reg_house_no     = trim($_POST['reg_house_no'] ?? '');
    $reg_moo          = trim($_POST['reg_moo'] ?? '');
    $reg_road         = trim($_POST['reg_road'] ?? '');
    $reg_sub_district = trim($_POST['reg_sub_district'] ?? '');
    $reg_district     = trim($_POST['reg_district'] ?? '');
    $reg_province     = trim($_POST['reg_province'] ?? '');
    $reg_postal_code  = trim($_POST['reg_postal_code'] ?? '');
    $reg_phone        = trim($_POST['reg_phone'] ?? '');

    // Current address
    $cur_house_no     = trim($_POST['cur_house_no'] ?? '');
    $cur_moo          = trim($_POST['cur_moo'] ?? '');
    $cur_road         = trim($_POST['cur_road'] ?? '');
    $cur_sub_district = trim($_POST['cur_sub_district'] ?? '');
    $cur_district     = trim($_POST['cur_district'] ?? '');
    $cur_province     = trim($_POST['cur_province'] ?? '');
    $cur_postal_code  = trim($_POST['cur_postal_code'] ?? '');
    $cur_phone        = trim($_POST['cur_phone'] ?? '');

    // Social
    $facebook = trim($_POST['facebook'] ?? '');
    $line_id  = trim($_POST['line_id'] ?? '');

    if (!registrationIsOpen($conn)) throw new Exception('ขณะนี้ปิดรับสมัคร กรุณาติดต่อเจ้าหน้าที่');
    $semester = currentSemester($conn);
    if (($_POST['semester'] ?? '') !== $semester) throw new Exception('ภาคเรียนรับสมัครเปลี่ยนแล้ว กรุณาโหลดหน้าใหม่');

    // ===== Validation =====
    $errors = [];
    if (empty($education_level)) $errors[] = 'กรุณาเลือกระดับที่สมัครเรียน';
    if (empty($subdistrict_center)) $errors[] = 'กรุณาเลือก ศกร.ระดับตำบล';
    if (empty($title)) $errors[] = 'กรุณาเลือกคำนำหน้าชื่อ';
    if (empty($first_name)) $errors[] = 'กรุณากรอกชื่อ';
    if (empty($last_name)) $errors[] = 'กรุณากรอกนามสกุล';
    if (strlen($id_card_number) !== 13 || !ctype_digit($id_card_number)) {
        $errors[] = 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก';
    }

    // Check duplicate ID card
    if (empty($errors)) {
        $checkStmt = $conn->prepare("SELECT id FROM registrations WHERE id_card_number = ? AND semester = ?");
        $checkStmt->bind_param("ss", $id_card_number, $semester);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $errors[] = 'เลขบัตรประชาชนนี้ได้สมัครเรียนแล้ว';
        }
        $checkStmt->close();
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }

    // Validate and upload the complete document set; clean up on any failure.
    $photo_file = $id_card_file = $house_reg_file = $certificate_file = $certificate_back_file = '';
    $uploadMap = [
        'photo_file' => [UPLOAD_PHOTOS, ALLOWED_IMAGE_EXT, 'รูปถ่าย', true],
        'id_card_file' => [UPLOAD_ID_CARDS, ALLOWED_DOC_EXT, 'บัตรประชาชน', true],
        'house_reg_file' => [UPLOAD_HOUSE_REGS, ALLOWED_DOC_EXT, 'ทะเบียนบ้าน', true],
        'certificate_file' => [UPLOAD_CERTIFICATES, ALLOWED_DOC_EXT, 'วุฒิด้านหน้า', false],
        'certificate_back_file' => [UPLOAD_CERTIFICATES, ALLOWED_DOC_EXT, 'วุฒิด้านหลัง', false],
    ];
    foreach ($uploadMap as $field => $rule) {
        $file = $_FILES[$field] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            if ($rule[3]) throw new Exception('กรุณาแนบ' . $rule[2]);
            continue;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('อัปโหลด' . $rule[2] . 'ไม่สำเร็จ');
        $name = uploadFile($file, $rule[0], $rule[1]);
        if (!$name) throw new Exception($rule[2] . ': ไฟล์ไม่ถูกต้องหรือเกิน 5 MB');
        ${$field} = $name; $uploadedPaths[] = $rule[0] . $name;
    }

    // ===== Insert into database =====
    $sql = "INSERT INTO registrations (
        semester, education_level, subdistrict_center, title, first_name, last_name,
        birth_date, age, id_card_number, religion, nationality,
        current_occupation, target_group,
        father_first_name, father_last_name, mother_first_name, mother_last_name,
        previous_education_level, graduation_year, previous_school, previous_district, previous_province,
        reg_house_no, reg_moo, reg_road, reg_sub_district, reg_district, reg_province, reg_postal_code, reg_phone,
        cur_house_no, cur_moo, cur_road, cur_sub_district, cur_district, cur_province, cur_postal_code, cur_phone,
        facebook, line_id,
        photo_file, id_card_file, house_reg_file, certificate_file, certificate_back_file
    ) VALUES (
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?,
        ?, ?, ?, ?, ?
    )";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssisssssssssssssssssssssssssssssssssssss",
        $semester,
        $education_level,
        $subdistrict_center,
        $title,
        $first_name,
        $last_name,
        $birth_date,
        $age,
        $id_card_number,
        $religion,
        $nationality,
        $current_occupation,
        $target_group,
        $father_first_name,
        $father_last_name,
        $mother_first_name,
        $mother_last_name,
        $previous_education_level,
        $graduation_year,
        $previous_school,
        $previous_district,
        $previous_province,
        $reg_house_no,
        $reg_moo,
        $reg_road,
        $reg_sub_district,
        $reg_district,
        $reg_province,
        $reg_postal_code,
        $reg_phone,
        $cur_house_no,
        $cur_moo,
        $cur_road,
        $cur_sub_district,
        $cur_district,
        $cur_province,
        $cur_postal_code,
        $cur_phone,
        $facebook,
        $line_id,
        $photo_file,
        $id_card_file,
        $house_reg_file,
        $certificate_file,
        $certificate_back_file
    );

    if ($stmt->execute()) {
        $registrationId = $stmt->insert_id;

        // Send email notification (non-blocking — errors are logged, not shown to user)
        try {
            sendRegistrationNotification([
                'semester' => $semester,
                'title' => $title,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'education_level' => $education_level,
                'subdistrict_center' => $subdistrict_center,
                'id_card_number' => $id_card_number,
                'reg_phone' => $reg_phone,
                'cur_phone' => $cur_phone,
            ]);
        } catch (\Throwable $emailErr) {
            error_log('[Email] Exception in sendRegistrationNotification: ' . $emailErr->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'สมัครเรียนสำเร็จ',
            'registration_id' => $registrationId
        ]);
    } else {
        throw new Exception('บันทึกข้อมูลไม่ได้ อาจมีการสมัครซ้ำในภาคเรียนนี้');
    }

    $stmt->close();
    $conn->close();
} catch (\Throwable $e) {
    foreach ($uploadedPaths as $path) if (is_file($path)) @unlink($path);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}

