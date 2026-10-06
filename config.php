<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load this machine's private settings without overwriting server constants.
require_once __DIR__ . '/runtime_config.php';

// Upload paths
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_PHOTOS', UPLOAD_DIR . 'photos/');
define('UPLOAD_ID_CARDS', UPLOAD_DIR . 'id_cards/');
define('UPLOAD_HOUSE_REGS', UPLOAD_DIR . 'house_registrations/');
define('UPLOAD_CERTIFICATES', UPLOAD_DIR . 'certificates/');

// Max file size (5MB)
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// Allowed file extensions
define('ALLOWED_IMAGE_EXT', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOC_EXT', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf']);

// Disable mysqli exception mode (PHP 8 default) to handle errors manually
mysqli_report(MYSQLI_REPORT_OFF);

// Create database connection
function getDBConnection()
{
    // Try connecting to the database directly
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        // Try connecting without database name to auto-create it
        $conn2 = @new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
        if ($conn2->connect_error) {
            throw new Exception("ไม่สามารถเชื่อมต่อ MySQL ได้: " . $conn2->connect_error);
        }
        $conn2->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn2->close();

        // Reconnect with the new database
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            throw new Exception("ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . $conn->connect_error);
        }

        // Auto-create table
        $conn->set_charset("utf8mb4");
        autoCreateTable($conn);
        ensureSemesterSchema($conn);
        return $conn;
    }
    $conn->set_charset("utf8mb4");

    // Auto-create tables if they don't exist
    $result = $conn->query("SHOW TABLES LIKE 'registrations'");
    $result2 = $conn->query("SHOW TABLES LIKE 'settings'");
    if (($result && $result->num_rows === 0) || ($result2 && $result2->num_rows === 0)) {
        autoCreateTable($conn);
    }

    ensureSemesterSchema($conn);
    return $conn;
}

// Auto-create registrations table
function autoCreateTable($conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS `registrations` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `education_level` VARCHAR(50) NOT NULL,
        `subdistrict_center` VARCHAR(100) NOT NULL,
        `title` VARCHAR(20) NOT NULL,
        `first_name` VARCHAR(100) NOT NULL,
        `last_name` VARCHAR(100) NOT NULL,
        `birth_date` DATE NOT NULL,
        `age` INT(3) DEFAULT NULL,
        `id_card_number` VARCHAR(13) NOT NULL,
        `religion` VARCHAR(50) DEFAULT NULL,
        `nationality` VARCHAR(50) DEFAULT NULL,
        `current_occupation` VARCHAR(100) DEFAULT NULL,
        `target_group` VARCHAR(100) DEFAULT NULL,
        `father_first_name` VARCHAR(100) DEFAULT NULL,
        `father_last_name` VARCHAR(100) DEFAULT NULL,
        `mother_first_name` VARCHAR(100) DEFAULT NULL,
        `mother_last_name` VARCHAR(100) DEFAULT NULL,
        `previous_education_level` VARCHAR(100) DEFAULT NULL,
        `graduation_year` VARCHAR(4) DEFAULT NULL,
        `previous_school` VARCHAR(200) DEFAULT NULL,
        `previous_district` VARCHAR(100) DEFAULT NULL,
        `previous_province` VARCHAR(100) DEFAULT NULL,
        `reg_house_no` VARCHAR(20) DEFAULT NULL,
        `reg_moo` VARCHAR(10) DEFAULT NULL,
        `reg_road` VARCHAR(100) DEFAULT NULL,
        `reg_sub_district` VARCHAR(100) DEFAULT NULL,
        `reg_district` VARCHAR(100) DEFAULT NULL,
        `reg_province` VARCHAR(100) DEFAULT NULL,
        `reg_postal_code` VARCHAR(5) DEFAULT NULL,
        `reg_phone` VARCHAR(20) DEFAULT NULL,
        `cur_house_no` VARCHAR(20) DEFAULT NULL,
        `cur_moo` VARCHAR(10) DEFAULT NULL,
        `cur_road` VARCHAR(100) DEFAULT NULL,
        `cur_sub_district` VARCHAR(100) DEFAULT NULL,
        `cur_district` VARCHAR(100) DEFAULT NULL,
        `cur_province` VARCHAR(100) DEFAULT NULL,
        `cur_postal_code` VARCHAR(5) DEFAULT NULL,
        `cur_phone` VARCHAR(20) DEFAULT NULL,
        `facebook` VARCHAR(200) DEFAULT NULL,
        `line_id` VARCHAR(100) DEFAULT NULL,
        `photo_file` VARCHAR(255) DEFAULT NULL,
        `id_card_file` VARCHAR(255) DEFAULT NULL,
        `house_reg_file` VARCHAR(255) DEFAULT NULL,
        `certificate_file` VARCHAR(255) DEFAULT NULL,
        `certificate_back_file` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_id_card` (`id_card_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);

    // Create settings table
    $conn->query("CREATE TABLE IF NOT EXISTS `settings` (
        `setting_key` VARCHAR(50) NOT NULL,
        `setting_value` TEXT NOT NULL,
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed default staff password if not exists
    $check = $conn->query("SELECT 1 FROM settings WHERE setting_key = 'staff_password'");
    if ($check && $check->num_rows === 0) {
        $defaultHash = password_hash('1234', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('staff_password', ?)");
        $stmt->bind_param("s", $defaultHash);
        $stmt->execute();
        $stmt->close();
    }

    // Seed default notification settings if not exists
    $checkEmail = $conn->query("SELECT 1 FROM settings WHERE setting_key = 'notification_email'");
    if ($checkEmail && $checkEmail->num_rows === 0) {
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('notification_email', '')");
    }
    $checkEnabled = $conn->query("SELECT 1 FROM settings WHERE setting_key = 'notification_enabled'");
    if ($checkEnabled && $checkEnabled->num_rows === 0) {
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('notification_enabled', '1')");
    }

    // Create staff_codes table (no UNIQUE on staff_code because we store hashes)
    $conn->query("CREATE TABLE IF NOT EXISTS `staff_codes` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_code` VARCHAR(255) NOT NULL,
        `staff_name` VARCHAR(100) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Remove the UNIQUE KEY if it still exists from old schema
    $conn->query("ALTER TABLE `staff_codes` DROP INDEX `uk_staff_code`");
    // Ensure staff_code column is long enough for hashes
    $conn->query("ALTER TABLE `staff_codes` MODIFY `staff_code` VARCHAR(255) NOT NULL");
}

// ==========================================
// Staff Authentication Functions
// All authentication uses the `settings` table
// with key 'staff_password' storing a bcrypt hash
// ==========================================

// Verify staff code/password against database
function verifyStaffCode($password)
{
    if (empty($password)) return false;

    $conn = getDBConnection();
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'staff_password'");
    if ($result && $row = $result->fetch_assoc()) {
        $valid = password_verify($password, $row['setting_value']);
        $conn->close();
        return $valid;
    }
    $conn->close();

    // Fallback: if no password is set in DB, accept '1234'
    if ($password === '1234') {
        return true;
    }
    return false;
}

// Alias for verifyStaffCode
function verifyStaffPassword($password)
{
    return verifyStaffCode($password);
}

// Update staff password in database
function updateStaffPassword($newPassword)
{
    $conn = getDBConnection();
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Check if setting exists
    $check = $conn->query("SELECT 1 FROM settings WHERE setting_key = 'staff_password'");
    if ($check && $check->num_rows > 0) {
        // Update existing
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'staff_password'");
    } else {
        // Insert new
        $stmt = $conn->prepare("INSERT INTO settings (setting_value, setting_key) VALUES (?, 'staff_password')");
    }

    if (!$stmt) {
        $conn->close();
        return false;
    }

    $stmt->bind_param("s", $hash);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $result;
}

// Create upload directories if they don't exist
function ensureUploadDirs()
{
    $dirs = [UPLOAD_DIR, UPLOAD_PHOTOS, UPLOAD_ID_CARDS, UPLOAD_HOUSE_REGS, UPLOAD_CERTIFICATES];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

// ==========================================
// Email Notification Functions
// ==========================================

/**
 * Send registration notification email
 * @param array $data Registration data
 * @return bool Whether email was sent successfully
 */
function sendRegistrationNotification($data)
{
    // Load Composer autoload
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        error_log('[Email] vendor/autoload.php not found');
        return false;
    }
    require_once $autoloadPath;

    $conn = getDBConnection();

    // Check if notifications are enabled
    $enabledResult = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'notification_enabled'");
    if (!$enabledResult || $enabledResult->num_rows === 0) {
        $conn->close();
        return false;
    }
    $enabled = $enabledResult->fetch_assoc()['setting_value'];
    if ($enabled !== '1') {
        $conn->close();
        return false;
    }

    // Get notification email(s)
    $emailResult = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'notification_email'");
    if (!$emailResult || $emailResult->num_rows === 0) {
        $conn->close();
        return false;
    }
    $emailsStr = trim($emailResult->fetch_assoc()['setting_value']);
    $conn->close();

    if (empty($emailsStr)) {
        return false;
    }

    // Parse multiple emails (comma separated)
    $emails = array_filter(array_map('trim', explode(',', $emailsStr)));
    if (empty($emails)) {
        return false;
    }

    // Compose email content
    $fullName = ($data['title'] ?? '') . ($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '');
    $semester = $data['semester'] ?? '-';
    $educationLevel = $data['education_level'] ?? '-';
    $subdistrictCenter = $data['subdistrict_center'] ?? '-';
    $idCard = $data['id_card_number'] ?? '-';
    $phone = $data['reg_phone'] ?? ($data['cur_phone'] ?? '-');
    $createdAt = date('d/m/') . (date('Y') + 543) . ' ' . date('H:i') . ' น.';

    $subject = "📋 แจ้งเตือน: นักศึกษาสมัครเรียนใหม่ ภาคเรียน {$semester} - {$fullName}";

    $htmlBody = '
    <div style="font-family: \'Sarabun\', \'Noto Sans Thai\', Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f8fafc; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
        <div style="background: linear-gradient(135deg, #6366f1, #4f46e5); padding: 24px 32px; text-align: center;">
            <h1 style="color: #fff; margin: 0; font-size: 20px;">📋 แจ้งเตือนการสมัครเรียนใหม่</h1>
            <p style="color: #c7d2fe; margin: 8px 0 0;">ศูนย์ส่งเสริมการเรียนรู้ระดับอำเภอเสนา</p>
        </div>
        <div style="padding: 32px;">
            <p style="color: #475569; margin: 0 0 20px; font-size: 15px;">มีนักศึกษาสมัครเรียนใหม่ผ่านระบบออนไลน์ รายละเอียดดังนี้:</p>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <tr>
                    <td style="padding: 10px 12px; background: #eef2ff; font-weight: 600; color: #4338ca; border-bottom: 1px solid #e2e8f0; width: 35%;">ชื่อ-นามสกุล</td>
                    <td style="padding: 10px 12px; background: #fff; border-bottom: 1px solid #e2e8f0; color: #1e293b;">' . htmlspecialchars($fullName) . '</td>
                </tr>
                <tr>
                    <td style="padding: 10px 12px; background: #eef2ff; font-weight: 600; color: #4338ca; border-bottom: 1px solid #e2e8f0;">ระดับที่สมัคร</td>
                    <td style="padding: 10px 12px; background: #fff; border-bottom: 1px solid #e2e8f0; color: #1e293b;">' . htmlspecialchars($educationLevel) . '</td>
                </tr>
                <tr>
                    <td style="padding: 10px 12px; background: #eef2ff; font-weight: 600; color: #4338ca; border-bottom: 1px solid #e2e8f0;">ศกร.ระดับตำบล</td>
                    <td style="padding: 10px 12px; background: #fff; border-bottom: 1px solid #e2e8f0; color: #1e293b;">' . htmlspecialchars($subdistrictCenter) . '</td>
                </tr>
                <tr>
                    <td style="padding: 10px 12px; background: #eef2ff; font-weight: 600; color: #4338ca; border-bottom: 1px solid #e2e8f0;">เบอร์โทร</td>
                    <td style="padding: 10px 12px; background: #fff; border-bottom: 1px solid #e2e8f0; color: #1e293b;">' . htmlspecialchars($phone) . '</td>
                </tr>
                <tr>
                    <td style="padding: 10px 12px; background: #eef2ff; font-weight: 600; color: #4338ca; border-bottom: 1px solid #e2e8f0;">วันที่สมัคร</td>
                    <td style="padding: 10px 12px; background: #fff; border-bottom: 1px solid #e2e8f0; color: #1e293b;">' . $createdAt . '</td>
                </tr>
            </table>
            <div style="text-align: center; margin-top: 24px;">
                <p style="color: #94a3b8; font-size: 13px; margin: 0;">กรุณาเข้าสู่ระบบเพื่อตรวจสอบและอนุมัติการสมัคร</p>
            </div>
        </div>
        <div style="background: #f1f5f9; padding: 16px 32px; text-align: center; border-top: 1px solid #e2e8f0;">
            <p style="color: #94a3b8; font-size: 12px; margin: 0;">© ' . (date('Y') + 543) . ' สกร.ระดับอำเภอเสนา — อีเมลนี้ส่งอัตโนมัติจากระบบ</p>
        </div>
    </div>';

    // Send using PHPMailer
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_FROM, 'สกร.ระดับอำเภอเสนา');

        foreach ($emails as $email) {
            $mail->addAddress(trim($email));
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = "นักศึกษาสมัครเรียนใหม่: {$fullName} | ระดับ: {$educationLevel} | ศกร.: {$subdistrictCenter} | เลขบัตร: {$idCard}";

        $mail->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('[Email] Failed to send notification: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send a test email
 * @param string $toEmail Recipient email address
 * @return array ['success' => bool, 'message' => string]
 */
function sendTestEmail($toEmail)
{
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        return ['success' => false, 'message' => 'ไม่พบ PHPMailer library (vendor/autoload.php)'];
    }
    require_once $autoloadPath;

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_FROM, 'สกร.ระดับอำเภอเสนา');
        $mail->addAddress(trim($toEmail));

        $mail->isHTML(true);
        $mail->Subject = '✅ ทดสอบระบบแจ้งเตือน Email - สกร.ระดับอำเภอเสนา';
        $mail->Body = '
        <div style="font-family: \'Sarabun\', \'Noto Sans Thai\', Arial, sans-serif; max-width: 500px; margin: 0 auto; background: #f8fafc; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
            <div style="background: linear-gradient(135deg, #10b981, #059669); padding: 24px 32px; text-align: center;">
                <h1 style="color: #fff; margin: 0; font-size: 20px;">✅ ทดสอบสำเร็จ!</h1>
            </div>
            <div style="padding: 32px; text-align: center;">
                <p style="color: #475569; font-size: 15px;">ระบบส่งอีเมลแจ้งเตือนทำงานได้ปกติ</p>
                <p style="color: #94a3b8; font-size: 13px;">สกร.ระดับอำเภอเสนา — ระบบสมัครเรียนออนไลน์</p>
            </div>
        </div>';
        $mail->AltBody = 'ทดสอบระบบส่งอีเมลแจ้งเตือน — สกร.ระดับอำเภอเสนา';

        $mail->send();
        return ['success' => true, 'message' => 'ส่งอีเมลทดสอบสำเร็จ ไปที่ ' . htmlspecialchars($toEmail)];
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        return ['success' => false, 'message' => 'ส่งอีเมลไม่สำเร็จ: ' . $e->getMessage()];
    }
}

// Existing applications are explicitly marked as legacy; never guess their term.
function ensureSemesterSchema($conn) {
    $column = $conn->query("SHOW COLUMNS FROM registrations LIKE 'semester'");
    if ($column && !$column->num_rows) {
        if (!$conn->query("ALTER TABLE registrations ADD semester VARCHAR(30) NOT NULL DEFAULT 'legacy'")) throw new Exception('เพิ่มภาคเรียนไม่สำเร็จ');
    }
    $old = $conn->query("SHOW INDEX FROM registrations WHERE Key_name = 'uk_id_card'");
    if ($old && $old->num_rows && !$conn->query("ALTER TABLE registrations DROP INDEX uk_id_card, ADD UNIQUE KEY uk_semester_id (semester, id_card_number)")) throw new Exception('ปรับดัชนีภาคเรียนไม่สำเร็จ');
    $conn->query("INSERT IGNORE INTO settings VALUES ('current_semester', '2/2569'), ('registration_open', '1')");
    $conn->query("CREATE TABLE IF NOT EXISTS semesters (semester VARCHAR(30) PRIMARY KEY, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("INSERT IGNORE INTO semesters (semester) SELECT semester FROM registrations WHERE semester <> 'legacy'");
    $conn->query("INSERT IGNORE INTO semesters (semester) SELECT setting_value FROM settings WHERE setting_key='current_semester'");
    $conn->query("INSERT IGNORE INTO semesters (semester) VALUES ('1/2569')");
}
function currentSemester($conn) {
    $row = $conn->query("SELECT setting_value FROM settings WHERE setting_key='current_semester'")->fetch_assoc();
    return $row['setting_value'];
}
function validSemester($value) { return is_string($value) && preg_match('/^[12]\/[2-9][0-9]{3}$/D', $value); }
function semesterLabel($value) { return $value === 'legacy' ? 'ข้อมูลเดิม (ยังไม่ระบุภาคเรียน)' : $value; }
function semesterOptions($conn) {
    $terms = [currentSemester($conn)];
    $catalog=$conn->query('SELECT semester FROM semesters ORDER BY RIGHT(semester,4) DESC, LEFT(semester,1) DESC');
    while ($row=$catalog->fetch_assoc()) $terms[]=$row['semester'];
    $result = $conn->query("SELECT DISTINCT semester FROM registrations ORDER BY semester DESC");
    while ($row = $result->fetch_assoc()) $terms[] = $row['semester'];
    return array_unique($terms);
}
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

// ===== Helper: Upload File =====
function uploadFile($file, $targetDir, $allowedExt)
{
    $fileSize = $file['size'];
    $fileName = $file['name'];
    $fileTmp = $file['tmp_name'];

    // Check file size
    if ($fileSize <= 0 || $fileSize > MAX_FILE_SIZE) {
        return false;
    }

    // Check extension
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) {
        return false;
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($fileTmp);
    $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
    if (($mimeMap[$ext] ?? '') !== $mime) return false;

    // Generate unique filename
    $newFileName = uniqid('file_', true) . '.' . $ext;
    $targetPath = $targetDir . $newFileName;

    if (move_uploaded_file($fileTmp, $targetPath)) {
        return $newFileName;
    }

    return false;
}

function registrationIsOpen($conn) {
    $result=$conn->query("SELECT setting_value FROM settings WHERE setting_key='registration_open'");
    return !$result->num_rows || $result->fetch_assoc()['setting_value']==='1';
}
