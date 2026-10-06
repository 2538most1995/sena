<?php require_once 'config.php'; $termConn = getDBConnection(); $currentTerm = currentSemester($termConn); $admissionsOpen = registrationIsOpen($termConn); $termConn->close(); ?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบรับสมัครนักศึกษาออนไลน์ | สกร.ระดับอำเภอเสนา</title>
    <meta name="description" content="ระบบรับสมัครนักศึกษาออนไลน์ สกร.ระดับอำเภอเสนา จังหวัดพระนครศรีอยุธยา">
    <link rel="stylesheet" href="css/style.css?v=<?= assetVersion('css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Staff Login Button (Floating) -->
    <button class="staff-login-btn" onclick="staffLogin()" title="สำหรับเจ้าหน้าที่">
        <i class="fas fa-user-shield"></i>
        <span>เจ้าหน้าที่</span>
    </button>

    <!-- Hidden file input for logo upload -->
    <input type="file" id="logoFileInput" accept="image/*" style="display:none;" onchange="handleLogoUpload(this)">

    <!-- Header -->
    <div class="header">
        <?php
        // Check if uploaded logo exists
        $logoFile = '';
        $logoGlob = glob(__DIR__ . '/uploads/logo/logo.*');
        if (!empty($logoGlob)) {
            $logoFile = 'uploads/logo/' . basename($logoGlob[0]) . '?t=' . filemtime($logoGlob[0]);
        }
        ?>
        <div class="header-icon logo-clickable" onclick="logoClick()" title="คลิกเพื่อเปลี่ยนโลโก้ (สำหรับเจ้าหน้าที่)">
            <?php if ($logoFile): ?>
                <img src="<?= $logoFile ?>" alt="โลโก้" id="headerLogo" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">
            <?php else: ?>
                <span id="headerLogoEmoji">🎓</span>
            <?php endif; ?>
        </div>
        <h1>ระบบรับสมัครนักศึกษาออนไลน์</h1>
        <p>สกร.ระดับอำเภอเสนา · จังหวัดพระนครศรีอยุธยา</p><div class="term-pill"><?= $admissionsOpen ? 'เปิดรับสมัคร' : 'ปิดรับสมัคร' ?> ภาคเรียน <?= htmlspecialchars($currentTerm) ?></div>
    </div>

    <div class="container">
        <div class="welcome-note"><strong>เริ่มต้นเส้นทางการเรียนรู้ของคุณ</strong><p>กรอกข้อมูลให้ครบทั้ง 5 ขั้นตอน และเตรียมรูปถ่าย บัตรประชาชน ทะเบียนบ้าน และวุฒิการศึกษา</p></div>
        <?php if (!$admissionsOpen): ?><p class="notice error" role="status">ขณะนี้ปิดรับสมัคร สามารถติดต่อ สกร.ระดับอำเภอเสนา เพื่อสอบถามรอบถัดไป</p><?php endif; ?>
        <!-- Step Indicator -->
        <div class="step-indicator" id="stepIndicator">
            <div class="step-item active" data-step="1">
                <div class="step-circle">1<span class="step-label">ข้อมูลส่วนตัว</span></div>
            </div>
            <div class="step-line"></div>
            <div class="step-item" data-step="2">
                <div class="step-circle">2<span class="step-label">ข้อมูลครอบครัว</span></div>
            </div>
            <div class="step-line"></div>
            <div class="step-item" data-step="3">
                <div class="step-circle">3<span class="step-label">ประวัติการศึกษา</span></div>
            </div>
            <div class="step-line"></div>
            <div class="step-item" data-step="4">
                <div class="step-circle">4<span class="step-label">ที่อยู่/ติดต่อ</span></div>
            </div>
            <div class="step-line"></div>
            <div class="step-item" data-step="5">
                <div class="step-circle">5<span class="step-label">แนบเอกสาร</span></div>
            </div>
        </div>

        <form id="registrationForm" enctype="multipart/form-data" novalidate><fieldset class="admission-fields" <?= !$admissionsOpen ? 'disabled' : '' ?>>
            <input type="hidden" name="semester" value="<?= htmlspecialchars($currentTerm) ?>">
            <!-- ===== Step 1: Personal Information ===== -->
            <div class="form-card step-content" id="step1">
                <div class="section-title">
                    <div class="icon"><i class="fas fa-user"></i></div>
                    <span>ข้อมูลส่วนตัว</span>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>ระดับที่สมัครเรียน <span class="required">*</span></label>
                        <select name="education_level" id="education_level" required>
                            <option value="">-- เลือกระดับ --</option>
                            <option value="ประถม">ประถม</option>
                            <option value="ม.ต้น">ม.ต้น</option>
                            <option value="ม.ปลาย">ม.ปลาย</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>ศกร.ระดับตำบล <span class="required">*</span></label>
                        <select name="subdistrict_center" id="subdistrict_center" required>
                            <option value="">-- เลือก ศกร.ระดับตำบล --</option>
                            <option value="ศกร.ระดับตำบลสามกอ">ศกร.ระดับตำบลสามกอ</option>
                            <option value="ศกร.ระดับตำบลบ้านหลวง">ศกร.ระดับตำบลบ้านหลวง</option>
                            <option value="ศกร.ระดับตำบลบ้านแพน">ศกร.ระดับตำบลบ้านแพน</option>
                            <option value="ศกร.ระดับตำบลบางนมโค">ศกร.ระดับตำบลบางนมโค</option>
                            <option value="ศกร.ระดับตำบลสามตุ่ม">ศกร.ระดับตำบลสามตุ่ม</option>
                            <option value="ศกร.ระดับตำบลบ้านกระทุ่ม">ศกร.ระดับตำบลบ้านกระทุ่ม</option>
                            <option value="ศกร.ระดับตำบลหัวเวียง">ศกร.ระดับตำบลหัวเวียง</option>
                            <option value="ศกร.ระดับตำบลมารวิชัย">ศกร.ระดับตำบลมารวิชัย</option>
                            <option value="ศกร.ระดับตำบลชายนา">ศกร.ระดับตำบลชายนา</option>
                            <option value="ศกร.ระดับตำบลรางจระเข้">ศกร.ระดับตำบลรางจระเข้</option>
                            <option value="ศกร.ระดับตำบลดอนทอง">ศกร.ระดับตำบลดอนทอง</option>
                            <option value="ศกร.ระดับตำบลบ้านแถว">ศกร.ระดับตำบลบ้านแถว</option>
                            <option value="ศกร.ระดับตำบลเจ้าเจ็ด">ศกร.ระดับตำบลเจ้าเจ็ด</option>
                            <option value="ศกร.ระดับตำบลเจ้าเสด็จ">ศกร.ระดับตำบลเจ้าเสด็จ</option>
                            <option value="ศกร.ระดับตำบลลาดงา">ศกร.ระดับตำบลลาดงา</option>
                            <option value="ศกร.ระดับตำบลบ้านโพธิ์">ศกร.ระดับตำบลบ้านโพธิ์</option>
                            <option value="ศกร.ระดับตำบลเสนา">ศกร.ระดับตำบลเสนา</option>
                            <option value="ศูนย์อำเภอ">ศูนย์อำเภอ</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>คำนำหน้าชื่อ <span class="required">*</span></label>
                        <select name="title" id="title" required>
                            <option value="">-- เลือกคำนำหน้า --</option>
                            <option value="เด็กชาย">เด็กชาย</option>
                            <option value="เด็กหญิง">เด็กหญิง</option>
                            <option value="นาย">นาย</option>
                            <option value="นางสาว">นางสาว</option>
                            <option value="นาง">นาง</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <!-- spacer for layout -->
                        <label>&nbsp;</label>
                        <div></div>
                    </div>
                    <div class="form-group">
                        <label>ชื่อ <span class="required">*</span></label>
                        <input type="text" name="first_name" id="first_name" placeholder="กรอกชื่อ" required>
                    </div>
                    <div class="form-group">
                        <label>นามสกุล <span class="required">*</span></label>
                        <input type="text" name="last_name" id="last_name" placeholder="กรอกนามสกุล" required>
                    </div>
                    <div class="form-group full-width">
                        <label>วัน/เดือน/ปีเกิด (พ.ศ.) <span class="required">*</span></label>
                        <div class="date-group">
                            <select name="birth_day" id="birth_day" required>
                                <option value="">วัน</option>
                                <?php for ($i = 1; $i <= 31; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="birth_month" id="birth_month" required>
                                <option value="">เดือน</option>
                                <?php
                                $thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                                foreach ($thaiMonths as $idx => $month): ?>
                                    <option value="<?= $idx + 1 ?>"><?= $month ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="birth_year" id="birth_year" required>
                                <option value="">ปี พ.ศ.</option>
                                <?php
                                $currentBEYear = date('Y') + 543;
                                for ($y = $currentBEYear; $y >= $currentBEYear - 80; $y--): ?>
                                    <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>อายุ</label>
                        <input type="text" name="age" id="age" placeholder="คำนวณอัตโนมัติ" readonly>
                    </div>
                    <div class="form-group">
                        <label>เลขบัตรประจำตัวประชาชน <span class="required">*</span></label>
                        <input type="text" name="id_card_number" id="id_card_number" class="id-card-input" placeholder="X-XXXX-XXXXX-XX-X" maxlength="13" required>
                    </div>
                    <div class="form-group">
                        <label>ศาสนา</label>
                        <input type="text" name="religion" id="religion" placeholder="เช่น พุทธ">
                    </div>
                    <div class="form-group">
                        <label>สัญชาติ</label>
                        <input type="text" name="nationality" id="nationality" placeholder="เช่น ไทย" value="ไทย">
                    </div>
                    <div class="form-group">
                        <label>อาชีพปัจจุบัน</label>
                        <input type="text" name="current_occupation" id="current_occupation" placeholder="กรอกอาชีพ">
                    </div>
                    <div class="form-group">
                        <label>กลุ่มเป้าหมาย <span class="required">*</span></label>
                        <select name="target_group" id="target_group" required>
                            <option value="">-- เลือกกลุ่มเป้าหมาย --</option>
                            <option value="รับราชการ">รับราชการ</option>
                            <option value="พนักงานรัฐวิสาหกิจ">พนักงานรัฐวิสาหกิจ</option>
                            <option value="ผู้สูงอายุ">ผู้สูงอายุ</option>
                            <option value="ค้าขาย-ธุรกิจส่วนตัว">ค้าขาย-ธุรกิจส่วนตัว</option>
                            <option value="คนพิการ">คนพิการ</option>
                            <option value="เกษตรกร">เกษตรกร</option>
                            <option value="ผู้นำท้องถิ่น">ผู้นำท้องถิ่น</option>
                            <option value="รับจ้าง">รับจ้าง</option>
                            <option value="ผู้ใช้แรงงาน">ผู้ใช้แรงงาน</option>
                            <option value="แรงงานต่างด้าว">แรงงานต่างด้าว</option>
                            <option value="อสม.">อสม.</option>
                            <option value="พระ">พระ</option>
                            <option value="อื่นๆ">อื่นๆ</option>
                        </select>
                    </div>
                </div>
                <div class="button-group">
                    <div></div>
                    <button type="button" class="btn btn-primary" onclick="nextStep(2)">
                        ถัดไป <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- ===== Step 2: Family Information ===== -->
            <div class="form-card step-content hidden" id="step2">
                <div class="section-title">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <span>ข้อมูลครอบครัว</span>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>ชื่อบิดา</label>
                        <input type="text" name="father_first_name" id="father_first_name" placeholder="กรอกชื่อบิดา">
                    </div>
                    <div class="form-group">
                        <label>นามสกุลบิดา</label>
                        <input type="text" name="father_last_name" id="father_last_name" placeholder="กรอกนามสกุลบิดา">
                    </div>
                    <div class="form-group">
                        <label>ชื่อมารดา</label>
                        <input type="text" name="mother_first_name" id="mother_first_name" placeholder="กรอกชื่อมารดา">
                    </div>
                    <div class="form-group">
                        <label>นามสกุลมารดา</label>
                        <input type="text" name="mother_last_name" id="mother_last_name" placeholder="กรอกนามสกุลมารดา">
                    </div>
                </div>
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="prevStep(1)">
                        <i class="fas fa-arrow-left"></i> ย้อนกลับ
                    </button>
                    <button type="button" class="btn btn-primary" onclick="nextStep(3)">
                        ถัดไป <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- ===== Step 3: Previous Education ===== -->
            <div class="form-card step-content hidden" id="step3">
                <div class="section-title">
                    <div class="icon"><i class="fas fa-graduation-cap"></i></div>
                    <span>ประวัติการศึกษาเดิม</span>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>ความรู้เดิมจบชั้น</label>
                        <input type="text" name="previous_education_level" id="previous_education_level" placeholder="เช่น ม.3">
                    </div>
                    <div class="form-group">
                        <label>จบเมื่อ พ.ศ.</label>
                        <select name="graduation_year" id="graduation_year">
                            <option value="">-- เลือกปี พ.ศ. --</option>
                            <?php
                            for ($y = $currentBEYear; $y >= $currentBEYear - 50; $y--): ?>
                                <option value="<?= $y ?>"><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>จากสถานศึกษา</label>
                        <input type="text" name="previous_school" id="previous_school" placeholder="ชื่อสถานศึกษา">
                    </div>
                    <div class="form-group">
                        <label>อำเภอ</label>
                        <input type="text" name="previous_district" id="previous_district" placeholder="อำเภอ">
                    </div>
                    <div class="form-group">
                        <label>จังหวัด</label>
                        <input type="text" name="previous_province" id="previous_province" placeholder="จังหวัด">
                    </div>
                </div>
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="prevStep(2)">
                        <i class="fas fa-arrow-left"></i> ย้อนกลับ
                    </button>
                    <button type="button" class="btn btn-primary" onclick="nextStep(4)">
                        ถัดไป <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- ===== Step 4: Address & Contact ===== -->
            <div class="form-card step-content hidden" id="step4">
                <div class="section-title">
                    <div class="icon"><i class="fas fa-map-marker-alt"></i></div>
                    <span>ที่อยู่และการติดต่อ</span>
                </div>

                <!-- Registered Address -->
                <h3 style="font-size:0.95rem; margin-bottom:16px; color:var(--primary-light);">
                    <i class="fas fa-home"></i> ที่อยู่ตามทะเบียนบ้าน
                </h3>
                <div class="form-grid" style="margin-bottom:28px;">
                    <div class="form-group">
                        <label>บ้านเลขที่</label>
                        <input type="text" name="reg_house_no" id="reg_house_no" placeholder="บ้านเลขที่">
                    </div>
                    <div class="form-group">
                        <label>หมู่</label>
                        <input type="text" name="reg_moo" id="reg_moo" placeholder="หมู่">
                    </div>
                    <div class="form-group">
                        <label>ถนน</label>
                        <input type="text" name="reg_road" id="reg_road" placeholder="ถนน">
                    </div>
                    <div class="form-group">
                        <label>ตำบล</label>
                        <input type="text" name="reg_sub_district" id="reg_sub_district" placeholder="ตำบล">
                    </div>
                    <div class="form-group">
                        <label>อำเภอ</label>
                        <input type="text" name="reg_district" id="reg_district" placeholder="อำเภอ">
                    </div>
                    <div class="form-group">
                        <label>จังหวัด</label>
                        <input type="text" name="reg_province" id="reg_province" placeholder="จังหวัด">
                    </div>
                    <div class="form-group">
                        <label>รหัสไปรษณีย์</label>
                        <input type="text" name="reg_postal_code" id="reg_postal_code" placeholder="รหัสไปรษณีย์" maxlength="5">
                    </div>
                    <div class="form-group">
                        <label>เบอร์โทร</label>
                        <input type="text" name="reg_phone" id="reg_phone" placeholder="เบอร์โทรศัพท์">
                    </div>
                </div>

                <!-- Current Address -->
                <h3 style="font-size:0.95rem; margin-bottom:12px; color:var(--primary-light);">
                    <i class="fas fa-location-dot"></i> ที่อยู่ปัจจุบันที่สามารถติดต่อได้
                </h3>
                <div class="copy-address">
                    <input type="checkbox" id="copyAddress" onchange="copyRegisteredAddress()">
                    <label for="copyAddress">คัดลอกที่อยู่จากทะเบียนบ้าน</label>
                </div>
                <div class="form-grid" style="margin-bottom:28px;">
                    <div class="form-group">
                        <label>บ้านเลขที่</label>
                        <input type="text" name="cur_house_no" id="cur_house_no" placeholder="บ้านเลขที่">
                    </div>
                    <div class="form-group">
                        <label>หมู่</label>
                        <input type="text" name="cur_moo" id="cur_moo" placeholder="หมู่">
                    </div>
                    <div class="form-group">
                        <label>ถนน</label>
                        <input type="text" name="cur_road" id="cur_road" placeholder="ถนน">
                    </div>
                    <div class="form-group">
                        <label>ตำบล</label>
                        <input type="text" name="cur_sub_district" id="cur_sub_district" placeholder="ตำบล">
                    </div>
                    <div class="form-group">
                        <label>อำเภอ</label>
                        <input type="text" name="cur_district" id="cur_district" placeholder="อำเภอ">
                    </div>
                    <div class="form-group">
                        <label>จังหวัด</label>
                        <input type="text" name="cur_province" id="cur_province" placeholder="จังหวัด">
                    </div>
                    <div class="form-group">
                        <label>รหัสไปรษณีย์</label>
                        <input type="text" name="cur_postal_code" id="cur_postal_code" placeholder="รหัสไปรษณีย์" maxlength="5">
                    </div>
                    <div class="form-group">
                        <label>เบอร์โทร</label>
                        <input type="text" name="cur_phone" id="cur_phone" placeholder="เบอร์โทรศัพท์">
                    </div>
                </div>

                <!-- Social Media -->
                <h3 style="font-size:0.95rem; margin-bottom:16px; color:var(--primary-light);">
                    <i class="fas fa-share-nodes"></i> โซเชียลมีเดีย
                </h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Facebook</label>
                        <input type="text" name="facebook" id="facebook" placeholder="ชื่อ Facebook">
                    </div>
                    <div class="form-group">
                        <label>ID Line</label>
                        <input type="text" name="line_id" id="line_id" placeholder="ID Line">
                    </div>
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="prevStep(3)">
                        <i class="fas fa-arrow-left"></i> ย้อนกลับ
                    </button>
                    <button type="button" class="btn btn-primary" onclick="nextStep(5)">
                        ถัดไป <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- ===== Step 5: Document Upload ===== -->
            <div class="form-card step-content hidden" id="step5">
                <div class="section-title">
                    <div class="icon"><i class="fas fa-paperclip"></i></div>
                    <span>แนบเอกสารหลักฐาน</span>
                </div>

                <!-- Photo upload -->
                <div style="margin-bottom:28px; text-align:center;">
                    <label style="display:block; font-size:0.85rem; font-weight:500; color:var(--text-secondary); margin-bottom:12px;">
                        5.1 รูปถ่ายหน้าตรง <span class="required">*</span>
                    </label>
                    <div class="photo-upload-area" id="photoArea">
                        <i class="fas fa-camera" style="font-size:1.5rem; color:var(--text-muted); margin-bottom:6px;" id="photoIcon"></i>
                        <span style="font-size:0.75rem; color:var(--text-muted);" id="photoText">คลิกเพื่ออัพโหลด</span>
                        <input type="file" name="photo_file" id="photo_file" accept="image/*" onchange="previewPhoto(this)">
                    </div>
                </div>

                <!-- Other documents -->
                <div class="upload-grid">
                    <div class="form-group">
                        <label>5.2 สำเนาบัตรประชาชน <span class="required">*</span></label>
                        <div class="file-upload-area" id="idCardArea">
                            <span class="upload-icon">📄</span>
                            <span class="upload-text">คลิกหรือลากไฟล์มาวาง</span>
                            <span class="upload-hint">รองรับ: รูปภาพ, PDF (ไม่เกิน 5MB)</span>
                            <input type="file" name="id_card_file" id="id_card_file" accept="image/*,.pdf" onchange="showFileName(this, 'idCardArea')">
                            <div class="file-name" id="idCardFileName" style="display:none;"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>5.3 สำเนาทะเบียนบ้าน <span class="required">*</span></label>
                        <div class="file-upload-area" id="houseRegArea">
                            <span class="upload-icon">📄</span>
                            <span class="upload-text">คลิกหรือลากไฟล์มาวาง</span>
                            <span class="upload-hint">รองรับ: รูปภาพ, PDF (ไม่เกิน 5MB)</span>
                            <input type="file" name="house_reg_file" id="house_reg_file" accept="image/*,.pdf" onchange="showFileName(this, 'houseRegArea')">
                            <div class="file-name" id="houseRegFileName" style="display:none;"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>5.4 วุฒิการศึกษาเดิม (ด้านหน้า)</label>
                        <div class="file-upload-area" id="certArea">
                            <span class="upload-icon">📄</span>
                            <span class="upload-text">คลิกหรือลากไฟล์มาวาง</span>
                            <span class="upload-hint">รองรับ: รูปภาพ, PDF (ไม่เกิน 5MB)</span>
                            <input type="file" name="certificate_file" id="certificate_file" accept="image/*,.pdf" onchange="showFileName(this, 'certArea')">
                            <div class="file-name" id="certFileName" style="display:none;"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>5.5 วุฒิการศึกษาเดิม (ด้านหลัง)</label>
                        <div class="file-upload-area" id="certBackArea">
                            <span class="upload-icon">📄</span>
                            <span class="upload-text">คลิกหรือลากไฟล์มาวาง</span>
                            <span class="upload-hint">รองรับ: รูปภาพ, PDF (ไม่เกิน 5MB)</span>
                            <input type="file" name="certificate_back_file" id="certificate_back_file" accept="image/*,.pdf" onchange="showFileName(this, 'certBackArea')">
                            <div class="file-name" id="certBackFileName" style="display:none;"></div>
                        </div>
                    </div>
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="prevStep(4)">
                        <i class="fas fa-arrow-left"></i> ย้อนกลับ
                    </button>
                    <button type="button" class="btn btn-success" onclick="submitForm()">
                        <i class="fas fa-paper-plane"></i> ส่งใบสมัคร
                    </button>
                </div>
            </div>
        </fieldset></form>
    </div>

    <!-- Footer -->
    <div class="footer">
        &copy; <?= date('Y') + 543 ?> สกร.ระดับอำเภอเสนา
    </div>

    <script>
        // ===== STEP NAVIGATION =====
        let currentStep = 1;
        const totalSteps = 5;

        function goToStep(step) {
            // Hide all steps
            document.querySelectorAll('.step-content').forEach(el => el.classList.add('hidden'));
            // Show target step
            document.getElementById('step' + step).classList.remove('hidden');

            // Update step indicator
            document.querySelectorAll('.step-item').forEach((el, idx) => {
                el.classList.remove('active', 'completed');
                if (idx + 1 < step) el.classList.add('completed');
                if (idx + 1 === step) el.classList.add('active');
            });

            currentStep = step;
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        function nextStep(step) {
            // Validate current step before proceeding
            if (!validateStep(currentStep)) return;
            goToStep(step);
        }

        function prevStep(step) {
            goToStep(step);
        }

        // ===== VALIDATION =====
        function validateStep(step) {
            let valid = true;
            let messages = [];

            if (step === 1) {
                if (!document.getElementById('education_level').value) {
                    valid = false;
                    messages.push('กรุณาเลือกระดับที่สมัครเรียน');
                }
                if (!document.getElementById('subdistrict_center').value) {
                    valid = false;
                    messages.push('กรุณาเลือก ศกร.ระดับตำบล');
                }
                if (!document.getElementById('title').value) {
                    valid = false;
                    messages.push('กรุณาเลือกคำนำหน้าชื่อ');
                }
                if (!document.getElementById('first_name').value.trim()) {
                    valid = false;
                    messages.push('กรุณากรอกชื่อ');
                }
                if (!document.getElementById('last_name').value.trim()) {
                    valid = false;
                    messages.push('กรุณากรอกนามสกุล');
                }
                if (!document.getElementById('birth_day').value || !document.getElementById('birth_month').value || !document.getElementById('birth_year').value) {
                    valid = false;
                    messages.push('กรุณาเลือกวัน/เดือน/ปีเกิด');
                }

                const idCard = document.getElementById('id_card_number').value.trim();
                if (!idCard) {
                    valid = false;
                    messages.push('กรุณากรอกเลขบัตรประชาชน');
                } else if (idCard.length !== 13 || !/^\d{13}$/.test(idCard)) {
                    valid = false;
                    messages.push('เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก');
                }

                if (!document.getElementById('target_group').value) {
                    valid = false;
                    messages.push('กรุณาเลือกกลุ่มเป้าหมาย');
                }
            }

            if (step === 5) {
                if (!document.getElementById('photo_file').files.length) {
                    valid = false;
                    messages.push('กรุณาอัพโหลดรูปถ่ายหน้าตรง');
                }
                if (!document.getElementById('id_card_file').files.length) {
                    valid = false;
                    messages.push('กรุณาอัพโหลดสำเนาบัตรประชาชน');
                }
                if (!document.getElementById('house_reg_file').files.length) {
                    valid = false;
                    messages.push('กรุณาอัพโหลดสำเนาทะเบียนบ้าน');
                }
            }

            if (!valid) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณากรอกข้อมูลให้ครบ',
                    html: messages.map(m => '• ' + m).join('<br>'),
                    confirmButtonColor: '#126454',
                    background: '#ffffff',
                    color: '#203c35'
                });
            }

            return valid;
        }

        // ===== AGE CALCULATION =====
        function calculateAge() {
            const day = document.getElementById('birth_day').value;
            const month = document.getElementById('birth_month').value;
            const year = document.getElementById('birth_year').value;

            if (day && month && year) {
                const ceYear = parseInt(year) - 543;
                const birthDate = new Date(ceYear, parseInt(month) - 1, parseInt(day));
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const mDiff = today.getMonth() - birthDate.getMonth();
                if (mDiff < 0 || (mDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                document.getElementById('age').value = age + ' ปี';
            }
        }

        document.getElementById('birth_day').addEventListener('change', calculateAge);
        document.getElementById('birth_month').addEventListener('change', calculateAge);
        document.getElementById('birth_year').addEventListener('change', calculateAge);

        // ===== COPY ADDRESS =====
        function copyRegisteredAddress() {
            const checked = document.getElementById('copyAddress').checked;
            const fields = ['house_no', 'moo', 'road', 'sub_district', 'district', 'province', 'postal_code', 'phone'];

            fields.forEach(f => {
                const curField = document.getElementById('cur_' + f);
                if (checked) {
                    curField.value = document.getElementById('reg_' + f).value;
                    curField.setAttribute('readonly', true);
                } else {
                    curField.removeAttribute('readonly');
                }
            });
        }

        // ===== FILE PREVIEW =====
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.size > 5 * 1024 * 1024 || !/\.(jpe?g|png|gif|webp)$/i.test(file.name)) {
                    input.value = ''; document.querySelector('.photo-preview')?.remove();
                    document.getElementById('photoIcon').style.display = '';
                    document.getElementById('photoText').style.display = '';
                    Swal.fire('เลือกรูปใหม่', 'รองรับ JPG, PNG, GIF หรือ WEBP ไม่เกิน 5 MB', 'warning'); return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    const area = document.getElementById('photoArea');
                    document.getElementById('photoIcon').style.display = 'none';
                    document.getElementById('photoText').style.display = 'none';

                    let img = area.querySelector('.photo-preview');
                    if (!img) {
                        img = document.createElement('img');
                        img.className = 'photo-preview';
                        area.insertBefore(img, area.querySelector('input'));
                    }
                    img.src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function showFileName(input, areaId) {
            const area = document.getElementById(areaId);
            const nameDiv = area.querySelector('.file-name');

            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.size > 5 * 1024 * 1024 || !/\.(jpe?g|png|gif|webp|pdf)$/i.test(file.name)) {
                    input.value = ''; nameDiv.textContent = 'กรุณาเลือก JPG, PNG, GIF, WEBP หรือ PDF ไม่เกิน 5 MB'; nameDiv.style.display = 'block';
                    area.querySelector('.attachment-preview')?.remove(); return;
                }
                area.querySelector('.attachment-preview')?.remove();
                if (file.type.startsWith('image/')) {
                    const preview = document.createElement('img'); preview.className = 'attachment-preview'; preview.alt = 'ตัวอย่างเอกสาร ' + file.name;
                    const url = URL.createObjectURL(file); preview.onload = () => URL.revokeObjectURL(url); preview.src = url; area.appendChild(preview);
                }
                nameDiv.textContent = file.name + ' · ' + (file.size / 1048576).toFixed(2) + ' MB';
                nameDiv.style.display = 'flex';
                area.style.borderColor = 'var(--success)';
            }
        }

        // ===== DRAG & DROP =====
        document.querySelectorAll('.file-upload-area').forEach(area => {
            area.addEventListener('dragover', (e) => {
                e.preventDefault();
                area.classList.add('dragover');
            });
            area.addEventListener('dragleave', () => {
                area.classList.remove('dragover');
            });
            area.addEventListener('drop', (e) => {
                e.preventDefault();
                area.classList.remove('dragover');
                const input = area.querySelector('input[type="file"]');
                if (e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files;
                    input.dispatchEvent(new Event('change'));
                }
            });
        });

        // ===== ID CARD FORMATTING =====
        document.getElementById('id_card_number').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 13);
        });

        // ===== FORM SUBMISSION =====
        function submitForm() {
            if (!validateStep(5)) return;

            Swal.fire({
                title: 'ยืนยันการส่งใบสมัคร?',
                text: 'กรุณาตรวจสอบข้อมูลให้ถูกต้องก่อนส่ง',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fas fa-check"></i> ยืนยันส่ง',
                cancelButtonText: 'ตรวจสอบอีกครั้ง',
                background: '#ffffff',
                color: '#203c35'
            }).then((result) => {
                if (result.isConfirmed) {
                    doSubmit();
                }
            });
        }

        function doSubmit() {
            const form = document.getElementById('registrationForm');
            const formData = new FormData(form);

            // Show loading
            document.getElementById('loadingOverlay').classList.add('active');

            fetch('process.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(text => {
                    document.getElementById('loadingOverlay').classList.remove('active');

                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        // PHP returned non-JSON (probably an error page)
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาดจากเซิร์ฟเวอร์',
                            html: '<div style="text-align:left;font-size:0.8rem;max-height:200px;overflow:auto;background:rgba(0,0,0,0.3);padding:12px;border-radius:8px;margin-top:8px;">' + text.substring(0, 500) + '</div>',
                            confirmButtonColor: '#126454',
                            background: '#ffffff',
                            color: '#203c35'
                        });
                        return;
                    }

                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'สมัครเรียนสำเร็จ!',
                            html: `<p>ขอบคุณที่สมัครเรียนกับเรา</p><p style="margin-top:8px;font-size:0.9rem;color:#94a3b8;">หมายเลขสมัคร: <strong style="color:#6366f1;">#${data.registration_id}</strong></p>`,
                            confirmButtonColor: '#126454',
                            background: '#ffffff',
                            color: '#203c35',
                            allowOutsideClick: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: data.message || 'กรุณาลองใหม่อีกครั้ง',
                            confirmButtonColor: '#126454',
                            background: '#ffffff',
                            color: '#203c35'
                        });
                    }
                })
                .catch(error => {
                    document.getElementById('loadingOverlay').classList.remove('active');
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้: ' + error.message,
                        confirmButtonColor: '#126454',
                        background: '#ffffff',
                        color: '#203c35'
                    });
                });
        }

        // ===== STEP CIRCLE CLICK =====
        document.querySelectorAll('.step-circle').forEach((circle, idx) => {
            circle.addEventListener('click', () => {
                const targetStep = idx + 1;
                if (targetStep < currentStep) {
                    goToStep(targetStep);
                } else if (targetStep > currentStep) {
                    // Validate all steps before target
                    let allValid = true;
                    for (let s = currentStep; s < targetStep; s++) {
                        if (!validateStep(s)) {
                            allValid = false;
                            break;
                        }
                    }
                    if (allValid) goToStep(targetStep);
                }
            });
        });
        // ===== STAFF LOGIN =====
        function staffLogin() {
            Swal.fire({
                title: '<i class="fas fa-user-shield" style="color:#818cf8"></i> เข้าสู่ระบบเจ้าหน้าที่',
                input: 'password',
                inputLabel: 'กรุณากรอกรหัสผ่าน',
                inputPlaceholder: 'รหัสผ่าน',
                inputAttributes: {
                    autocapitalize: 'off',
                    autocorrect: 'off'
                },
                showCancelButton: true,
                confirmButtonColor: '#126454',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ',
                cancelButtonText: 'ยกเลิก',
                background: '#ffffff',
                color: '#203c35',
                preConfirm: (password) => {
                    if (!password) {
                        Swal.showValidationMessage('กรุณากรอกรหัสผ่าน');
                        return false;
                    }
                    
                    const formData = new FormData();
                    formData.append('code', password);
                    
                    return fetch('verify_staff.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'รหัสผ่านไม่ถูกต้อง');
                        }
                        return true;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(error.message);
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        icon: 'success',
                        title: 'เข้าสู่ระบบสำเร็จ',
                        text: 'กำลังไปหน้าจัดการ...',
                        timer: 1200,
                        showConfirmButton: false,
                        background: '#ffffff',
                        color: '#203c35'
                    }).then(() => {
                        window.location.href = 'admin/index.php';
                    });
                }
            });
        }
        // ===== LOGO UPLOAD (Staff Only) =====
        let staffLogoPassword = null; // cached after first successful auth

        function logoClick() {
            if (staffLogoPassword) {
                // Already authenticated, go straight to file picker
                document.getElementById('logoFileInput').click();
                return;
            }

            Swal.fire({
                title: '<i class="fas fa-image" style="color:#818cf8"></i> เปลี่ยนโลโก้',
                input: 'password',
                inputLabel: 'กรุณากรอกรหัสเจ้าหน้าที่',
                inputPlaceholder: 'รหัสผ่าน',
                inputAttributes: {
                    autocapitalize: 'off',
                    autocorrect: 'off'
                },
                showCancelButton: true,
                confirmButtonColor: '#126454',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fas fa-check"></i> ยืนยัน',
                cancelButtonText: 'ยกเลิก',
                background: '#ffffff',
                color: '#203c35',
                preConfirm: (password) => {
                    if (!password) {
                        Swal.showValidationMessage('กรุณากรอกรหัสผ่าน');
                        return false;
                    }

                    const formData = new FormData();
                    formData.append('code', password);

                    return fetch('verify_staff.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'รหัสผ่านไม่ถูกต้อง');
                        }
                        return password;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(error.message);
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    staffLogoPassword = result.value;
                    document.getElementById('logoFileInput').click();
                }
            });
        }

        function handleLogoUpload(input) {
            if (!input.files || !input.files[0]) return;

            const file = input.files[0];
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ไฟล์ใหญ่เกินไป',
                    text: 'ขนาดไฟล์ต้องไม่เกิน 5MB',
                    background: '#ffffff',
                    color: '#203c35',
                    confirmButtonColor: '#126454'
                });
                input.value = '';
                return;
            }

            const formData = new FormData();
            formData.append('logo', file);
            formData.append('password', staffLogoPassword);

            Swal.fire({
                title: 'กำลังอัพโหลดโลโก้...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
                background: '#ffffff',
                color: '#203c35'
            });

            fetch('upload_logo.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'อัพโหลดโลโก้สำเร็จ!',
                            text: 'โลโก้ถูกเปลี่ยนแล้ว',
                            background: '#ffffff',
                            color: '#203c35',
                            confirmButtonColor: '#126454'
                        }).then(() => {
                            // Update logo on page without reload
                            const iconDiv = document.querySelector('.header-icon');
                            iconDiv.innerHTML = '<img src="' + data.logo_url + '" alt="โลโก้" id="headerLogo" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">';
                        });
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
                })
                .catch(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'ผิดพลาด',
                        text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้',
                        background: '#ffffff',
                        color: '#203c35',
                        confirmButtonColor: '#126454'
                    });
                });

            input.value = '';
        }
    </script>

    <style>
        .logo-clickable {
            cursor: pointer;
            position: relative;
        }

        .logo-clickable::after {
            content: '\f030';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            bottom: -4px;
            right: -4px;
            width: 24px;
            height: 24px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: white;
            opacity: 0;
            transition: opacity 0.3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .logo-clickable:hover::after {
            opacity: 1;
        }

        .logo-clickable:hover {
            transform: scale(1.05);
        }
    </style>
</body>

</html>