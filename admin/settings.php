<?php
require_once '../config.php';
require_once 'auth.php';
$conn = getDBConnection();

// Load current email settings
$emailResult = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'notification_email'");
$currentEmail = ($emailResult && $emailResult->num_rows > 0) ? $emailResult->fetch_assoc()['setting_value'] : '';

$enabledResult = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'notification_enabled'");
$notificationEnabled = ($enabledResult && $enabledResult->num_rows > 0) ? $enabledResult->fetch_assoc()['setting_value'] : '1';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าระบบ | สกร.ระดับอำเภอเสนา</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #cbd5e1;
            border-radius: 28px;
            transition: all 0.3s ease;
        }
        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 22px;
            height: 22px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .toggle-switch input:checked + .toggle-slider {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
        }
        .toggle-switch input:checked + .toggle-slider::before {
            transform: translateX(24px);
        }
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
        }
        .toggle-label {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.95rem;
        }
        .toggle-desc {
            color: #64748b;
            font-size: 0.82rem;
            margin-top: 2px;
        }
        .email-hint {
            color: #94a3b8;
            font-size: 0.78rem;
            margin-top: 4px;
        }
        .btn-test {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-test:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .settings-divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 28px 0;
        }
    </style>
</head>

<body>

    <div class="admin-header">
        <h1><i class="fas fa-cog" style="color:var(--primary-light)"></i> ตั้งค่าระบบ</h1>
        <div style="display:flex;gap:8px;">
            <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> กลับหน้าจัดการ</a>
            <a href="logout.php" class="btn btn-danger btn-sm"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
        </div>
    </div>

    <div class="admin-container">

        <!-- Email Notification Settings -->
        <div class="form-card" style="max-width: 600px; margin: 20px auto;">
            <div class="section-title">
                <div class="icon"><i class="fas fa-envelope"></i></div>
                <span>ตั้งค่าแจ้งเตือน Email</span>
            </div>

            <form id="emailSettingsForm">
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">เปิดใช้งานแจ้งเตือน Email</div>
                        <div class="toggle-desc">ส่ง Email อัตโนมัติเมื่อมีนักศึกษาสมัครเรียนใหม่</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="notification_enabled" id="notificationEnabled" <?= $notificationEnabled === '1' ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="form-grid" style="grid-template-columns: 1fr; margin-top: 16px;">
                    <div class="form-group">
                        <label>Email ผู้รับแจ้งเตือน</label>
                        <input type="text" name="notification_email" id="notificationEmail" 
                               placeholder="example@email.com" 
                               value="<?= htmlspecialchars($currentEmail) ?>">
                        <div class="email-hint">สามารถกรอกหลายอีเมล คั่นด้วยเครื่องหมาย , (comma)</div>
                    </div>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap;">
                    <button type="button" class="btn-test" onclick="testEmail()">
                        <i class="fas fa-paper-plane"></i> ทดสอบส่ง Email
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> บันทึกการตั้งค่า
                    </button>
                </div>
            </form>
        </div>

        <hr class="settings-divider" style="max-width: 600px; margin: 0 auto;">

        <!-- Password Settings -->
        <div class="form-card" style="max-width: 600px; margin: 20px auto;">
            <div class="section-title">
                <div class="icon"><i class="fas fa-lock"></i></div>
                <span>เปลี่ยนรหัสผ่านเจ้าหน้าที่</span>
            </div>

            <form id="passwordForm">
                <div class="form-grid" style="grid-template-columns: 1fr;">
                    <div class="form-group">
                        <label>รหัสผ่านเดิม <span class="required">*</span></label>
                        <input type="password" name="current_password" required placeholder="ป้อนรหัสผ่านปัจจุบัน">
                    </div>
                    <div class="form-group">
                        <label>รหัสผ่านใหม่ <span class="required">*</span></label>
                        <input type="password" name="new_password" id="new_password" required placeholder="ป้อนรหัสผ่านใหม่">
                    </div>
                    <div class="form-group">
                        <label>ยืนยันรหัสผ่านใหม่ <span class="required">*</span></label>
                        <input type="password" name="confirm_password" required placeholder="ป้อนรหัสผ่านใหม่อีกครั้ง">
                    </div>
                </div>

                <div style="margin-top: 24px; text-align: right;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> บันทึกการเปลี่ยนแปลง
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // === Email Settings Form ===
        document.getElementById('emailSettingsForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const email = document.getElementById('notificationEmail').value.trim();
            const enabled = document.getElementById('notificationEnabled').checked ? '1' : '0';

            Swal.fire({
                title: 'กำลังบันทึก...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch('action_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=save_email_settings&notification_email=${encodeURIComponent(email)}&notification_enabled=${enabled}`
            })
            .then(r => r.json())
            .then(data => {
                Swal.fire({
                    icon: data.success ? 'success' : 'error',
                    title: data.success ? 'สำเร็จ!' : 'ผิดพลาด',
                    text: data.message,
                    confirmButtonColor: '#126454'
                });
            })
            .catch(() => {
                Swal.fire({
                    icon: 'error',
                    title: 'ผิดพลาด',
                    text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้',
                    confirmButtonColor: '#126454'
                });
            });
        });

        // === Test Email ===
        function testEmail() {
            const email = document.getElementById('notificationEmail').value.trim();
            if (!email) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณากรอก Email',
                    text: 'กรุณากรอกอีเมลผู้รับก่อนทดสอบ',
                    confirmButtonColor: '#126454'
                });
                return;
            }

            Swal.fire({
                title: 'กำลังส่งอีเมลทดสอบ...',
                html: 'อาจใช้เวลาสักครู่',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch('action_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=test_email&test_email=${encodeURIComponent(email)}`
            })
            .then(r => r.json())
            .then(data => {
                Swal.fire({
                    icon: data.success ? 'success' : 'error',
                    title: data.success ? 'ส่งสำเร็จ!' : 'ส่งไม่สำเร็จ',
                    text: data.message,
                    confirmButtonColor: '#126454'
                });
            })
            .catch(() => {
                Swal.fire({
                    icon: 'error',
                    title: 'ผิดพลาด',
                    text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้',
                    confirmButtonColor: '#126454'
                });
            });
        }

        // === Password Change Form ===
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            if (formData.get('new_password') !== formData.get('confirm_password')) {
                Swal.fire({
                    icon: 'error',
                    title: 'ผิดพลาด',
                    text: 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน',
                    confirmButtonColor: '#126454'
                });
                return;
            }

            Swal.fire({
                title: 'กำลังบันทึก...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            formData.append('action', 'change_password');

            fetch('action_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ!',
                        text: data.message,
                        confirmButtonColor: '#126454'
                    }).then(() => {
                        this.reset();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'ผิดพลาด',
                        text: data.message,
                        confirmButtonColor: '#126454'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'ผิดพลาด',
                    text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้',
                    confirmButtonColor: '#126454'
                });
            });
        });
    </script>
</body>

</html>
