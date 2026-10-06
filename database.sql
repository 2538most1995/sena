-- Database: phaisali_registration
CREATE DATABASE IF NOT EXISTS phaisali_registration DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE phaisali_registration;

-- Table: registrations
CREATE TABLE IF NOT EXISTS `registrations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,

-- Section 1: Personal Information
`semester` VARCHAR(30) NOT NULL DEFAULT 'legacy',
`education_level` VARCHAR(50) NOT NULL COMMENT 'ระดับที่สมัครเรียน',
`subdistrict_center` VARCHAR(100) NOT NULL COMMENT 'ศกร.ระดับตำบล',
`title` VARCHAR(20) NOT NULL COMMENT 'คำนำหน้าชื่อ',
`first_name` VARCHAR(100) NOT NULL COMMENT 'ชื่อ',
`last_name` VARCHAR(100) NOT NULL COMMENT 'นามสกุล',
`birth_date` DATE NOT NULL COMMENT 'วันเดือนปีเกิด',
`age` INT(3) DEFAULT NULL COMMENT 'อายุ',
`id_card_number` VARCHAR(13) NOT NULL COMMENT 'เลขบัตรประชาชน 13 หลัก',
`religion` VARCHAR(50) DEFAULT NULL COMMENT 'ศาสนา',
`nationality` VARCHAR(50) DEFAULT NULL COMMENT 'สัญชาติ',
`current_occupation` VARCHAR(100) DEFAULT NULL COMMENT 'อาชีพปัจจุบัน',
`target_group` VARCHAR(100) DEFAULT NULL COMMENT 'กลุ่มเป้าหมาย',

-- Section 2: Family Information
`father_first_name` VARCHAR(100) DEFAULT NULL COMMENT 'ชื่อบิดา',
`father_last_name` VARCHAR(100) DEFAULT NULL COMMENT 'นามสกุลบิดา',
`mother_first_name` VARCHAR(100) DEFAULT NULL COMMENT 'ชื่อมารดา',
`mother_last_name` VARCHAR(100) DEFAULT NULL COMMENT 'นามสกุลมารดา',

-- Section 3: Previous Education
`previous_education_level` VARCHAR(100) DEFAULT NULL COMMENT 'ความรู้เดิมจบชั้น',
`graduation_year` VARCHAR(4) DEFAULT NULL COMMENT 'จบเมื่อ พ.ศ.',
`previous_school` VARCHAR(200) DEFAULT NULL COMMENT 'จากสถานศึกษา',
`previous_district` VARCHAR(100) DEFAULT NULL COMMENT 'อำเภอ',
`previous_province` VARCHAR(100) DEFAULT NULL COMMENT 'จังหวัด',

-- Section 4: Registered Address
`reg_house_no` VARCHAR(20) DEFAULT NULL COMMENT 'บ้านเลขที่ (ทะเบียนบ้าน)',
`reg_moo` VARCHAR(10) DEFAULT NULL COMMENT 'หมู่ (ทะเบียนบ้าน)',
`reg_road` VARCHAR(100) DEFAULT NULL COMMENT 'ถนน (ทะเบียนบ้าน)',
`reg_sub_district` VARCHAR(100) DEFAULT NULL COMMENT 'ตำบล (ทะเบียนบ้าน)',
`reg_district` VARCHAR(100) DEFAULT NULL COMMENT 'อำเภอ (ทะเบียนบ้าน)',
`reg_province` VARCHAR(100) DEFAULT NULL COMMENT 'จังหวัด (ทะเบียนบ้าน)',
`reg_postal_code` VARCHAR(5) DEFAULT NULL COMMENT 'รหัสไปรษณีย์ (ทะเบียนบ้าน)',
`reg_phone` VARCHAR(20) DEFAULT NULL COMMENT 'เบอร์โทร (ทะเบียนบ้าน)',

-- Section 4: Current Address
`cur_house_no` VARCHAR(20) DEFAULT NULL COMMENT 'บ้านเลขที่ (ปัจจุบัน)',
`cur_moo` VARCHAR(10) DEFAULT NULL COMMENT 'หมู่ (ปัจจุบัน)',
`cur_road` VARCHAR(100) DEFAULT NULL COMMENT 'ถนน (ปัจจุบัน)',
`cur_sub_district` VARCHAR(100) DEFAULT NULL COMMENT 'ตำบล (ปัจจุบัน)',
`cur_district` VARCHAR(100) DEFAULT NULL COMMENT 'อำเภอ (ปัจจุบัน)',
`cur_province` VARCHAR(100) DEFAULT NULL COMMENT 'จังหวัด (ปัจจุบัน)',
`cur_postal_code` VARCHAR(5) DEFAULT NULL COMMENT 'รหัสไปรษณีย์ (ปัจจุบัน)',
`cur_phone` VARCHAR(20) DEFAULT NULL COMMENT 'เบอร์โทร (ปัจจุบัน)',

-- Social Media
`facebook` VARCHAR(200) DEFAULT NULL COMMENT 'Facebook',
`line_id` VARCHAR(100) DEFAULT NULL COMMENT 'Line ID',

-- Section 5: Uploaded Documents
`photo_file` VARCHAR(255) DEFAULT NULL COMMENT 'รูปถ่ายหน้าตรง',
`id_card_file` VARCHAR(255) DEFAULT NULL COMMENT 'สำเนาบัตรประชาชน',
`house_reg_file` VARCHAR(255) DEFAULT NULL COMMENT 'สำเนาทะเบียนบ้าน',
`certificate_file` VARCHAR(255) DEFAULT NULL COMMENT 'วุฒิการศึกษาเดิม',

-- Meta
`status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' COMMENT 'สถานะ',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_semester_id` (`semester`, `id_card_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;