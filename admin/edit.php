<?php
require_once '../config.php'; require_once 'auth.php'; require_once 'registration_service.php';
$conn=getDBConnection(); $id=(int)($_GET['id'] ?? 0); $error='';
$stmt=$conn->prepare('SELECT * FROM registrations WHERE id=?'); $stmt->bind_param('i',$id); $stmt->execute(); $reg=$stmt->get_result()->fetch_assoc();
if (!$reg) { header('Location: index.php'); exit; }
$groups=[
'ข้อมูลการสมัคร'=>['semester'=>'ภาคเรียน','education_level'=>'ระดับที่สมัคร','subdistrict_center'=>'ศกร.ระดับตำบล','status'=>'สถานะ'],
'ข้อมูลส่วนตัว'=>['title'=>'คำนำหน้า','first_name'=>'ชื่อ','last_name'=>'นามสกุล','birth_date'=>'วันเกิด (ค.ศ.)','age'=>'อายุ','id_card_number'=>'เลขบัตรประชาชน','religion'=>'ศาสนา','nationality'=>'สัญชาติ','current_occupation'=>'อาชีพ','target_group'=>'กลุ่มเป้าหมาย'],
'ข้อมูลครอบครัว'=>['father_first_name'=>'ชื่อบิดา','father_last_name'=>'นามสกุลบิดา','mother_first_name'=>'ชื่อมารดา','mother_last_name'=>'นามสกุลมารดา'],
'ประวัติการศึกษา'=>['previous_education_level'=>'ระดับการศึกษาเดิม','graduation_year'=>'ปีที่จบ (พ.ศ.)','previous_school'=>'สถานศึกษาเดิม','previous_district'=>'อำเภอ','previous_province'=>'จังหวัด'],
'ที่อยู่ตามทะเบียนบ้าน'=>['reg_house_no'=>'บ้านเลขที่','reg_moo'=>'หมู่','reg_road'=>'ถนน','reg_sub_district'=>'ตำบล','reg_district'=>'อำเภอ','reg_province'=>'จังหวัด','reg_postal_code'=>'รหัสไปรษณีย์','reg_phone'=>'โทรศัพท์'],
'ที่อยู่ปัจจุบันและช่องทางติดต่อ'=>['cur_house_no'=>'บ้านเลขที่','cur_moo'=>'หมู่','cur_road'=>'ถนน','cur_sub_district'=>'ตำบล','cur_district'=>'อำเภอ','cur_province'=>'จังหวัด','cur_postal_code'=>'รหัสไปรษณีย์','cur_phone'=>'โทรศัพท์','facebook'=>'Facebook','line_id'=>'LINE ID']];
$docs=['photo_file'=>[UPLOAD_PHOTOS,'รูปถ่าย',ALLOWED_IMAGE_EXT],'id_card_file'=>[UPLOAD_ID_CARDS,'บัตรประชาชน',ALLOWED_DOC_EXT],'house_reg_file'=>[UPLOAD_HOUSE_REGS,'ทะเบียนบ้าน',ALLOWED_DOC_EXT],'certificate_file'=>[UPLOAD_CERTIFICATES,'วุฒิการศึกษาด้านหน้า',ALLOWED_DOC_EXT],'certificate_back_file'=>[UPLOAD_CERTIFICATES,'วุฒิการศึกษาด้านหลัง',ALLOWED_DOC_EXT]];
$revision=hash('sha256',json_encode($reg));
$required=['semester','education_level','subdistrict_center','title','first_name','last_name','birth_date','id_card_number'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $newFiles=[];
    try {
        if (!hash_equals(csrfToken(),$_POST['csrf'] ?? '')) throw new Exception('กรุณาโหลดหน้าใหม่');
        $updates=[];
        foreach ($groups as $fields) foreach ($fields as $field=>$label) {
            $value=trim($_POST[$field] ?? '');
            if (in_array($field,$required,true) && $value==='') throw new Exception('กรุณากรอก'.$label);
            $updates[$field]=$field==='age' && $value==='' ? null : $value;
        }
        if (!validSemester($updates['semester']) && $updates['semester'] !== 'legacy') throw new Exception('ภาคเรียนไม่ถูกต้อง');
        if (!in_array($updates['education_level'],['ประถม','ม.ต้น','ม.ปลาย'],true)) throw new Exception('ระดับการศึกษาไม่ถูกต้อง');
        if (!in_array($updates['status'],['pending','approved','rejected'],true)) throw new Exception('สถานะไม่ถูกต้อง');
        if (!preg_match('/^[0-9]{13}$/D',$updates['id_card_number'])) throw new Exception('เลขบัตรต้องเป็นตัวเลข 13 หลัก');
        $date=DateTime::createFromFormat('!Y-m-d',$updates['birth_date']);
        if (!$date || $date->format('Y-m-d')!==$updates['birth_date'] || $date>new DateTime('today')) throw new Exception('วันเกิดไม่ถูกต้อง');
        $updates['age']=$date->diff(new DateTime('today'))->y;
        foreach (['reg_postal_code','cur_postal_code'] as $field) if ($updates[$field]!=='' && !preg_match('/^[0-9]{5}$/D',$updates[$field])) throw new Exception('รหัสไปรษณีย์ต้องเป็น 5 หลัก');
        ensureUploadDirs();
        foreach ($docs as $field=>$doc) if (isset($_FILES[$field]) && $_FILES[$field]['error']!==UPLOAD_ERR_NO_FILE) {
            if ($_FILES[$field]['error']!==UPLOAD_ERR_OK) throw new Exception('อัปโหลด'.$doc[1].'ไม่สำเร็จ');
            $name=uploadFile($_FILES[$field],$doc[0],$doc[2]);
            if (!$name) throw new Exception($doc[1].': รองรับภาพหรือ PDF ขนาดไม่เกิน 5 MB');
            $newFiles[]=$doc[0].$name; $updates[$field]=$name;
        }
        updateRegistration($conn,$id,$updates,$_POST['revision'] ?? '');
        $_SESSION['registration_notice']='บันทึกการแก้ไขเรียบร้อย';
        header('Location: view.php?id='.$id); exit;
    } catch (Throwable $e) { foreach ($newFiles as $path) @unlink($path); $error=$e->getMessage(); $reg=array_merge($reg,array_intersect_key($_POST,$reg)); }
}
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>แก้ไขผู้สมัคร</title><link rel="stylesheet" href="../css/style.css?v=<?= assetVersion('css/style.css') ?>"></head><body><header class="admin-header"><h1>แก้ไขข้อมูลผู้สมัคร #<?= $id ?></h1><a href="view.php?id=<?= $id ?>" class="btn btn-secondary">กลับไปดูรายละเอียด</a></header><main class="admin-container">
<?php if ($error): ?><p class="notice error" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<div class="welcome-note"><strong>ตรวจข้อมูลให้ถูกต้องก่อนบันทึก</strong><p>บันทึกการแก้ไขได้โดยไม่สร้างไฟล์สำรองอัตโนมัติ หากต้องการสำรอง ให้ไปที่หน้าจัดการภาคเรียน</p></div>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="revision" value="<?= htmlspecialchars($revision) ?>"><input type="hidden" name="csrf" value="<?= csrfToken() ?>">
<?php foreach ($groups as $title=>$fields): ?><section class="detail-card"><h2><?= $title ?></h2><div class="form-grid"><?php foreach ($fields as $field=>$label): ?><div class="form-group"><label for="<?= $field ?>"><?= $label ?><?= in_array($field,$required,true) ? ' *' : '' ?></label>
<?php if ($field==='status'): ?><select name="status" id="status"><?php foreach (['pending'=>'รอดำเนินการ','approved'=>'อนุมัติ','rejected'=>'ไม่อนุมัติ'] as $value=>$text): ?><option value="<?= $value ?>" <?= $reg[$field]===$value ? 'selected' : '' ?>><?= $text ?></option><?php endforeach; ?></select>
<?php else: ?><input id="<?= $field ?>" name="<?= $field ?>" type="<?= $field==='birth_date' ? 'date' : ($field==='age' ? 'number' : 'text') ?>" value="<?= htmlspecialchars((string)($reg[$field] ?? '')) ?>" <?= in_array($field,$required,true) ? 'required' : '' ?> <?= $field==='age' ? 'readonly' : '' ?> <?= $field==='semester' ? 'list="term-options"' : '' ?>><?php endif; ?></div><?php endforeach; ?></div></section><?php endforeach; ?>
<section class="detail-card"><h2>แทนที่เอกสารแนบ</h2><p>เลือกเฉพาะเอกสารที่ต้องการเปลี่ยน ขนาดไม่เกิน 5 MB ต่อไฟล์</p><div class="upload-grid"><?php foreach ($docs as $field=>$doc): ?><div class="form-group"><label for="<?= $field ?>"><?= $doc[1] ?></label><p><?= empty($reg[$field]) ? 'ยังไม่มีเอกสาร' : 'มีเอกสารแล้ว' ?></p><input id="<?= $field ?>" type="file" name="<?= $field ?>" accept="<?= $field==='photo_file' ? '.jpg,.jpeg,.png,.gif,.webp' : '.jpg,.jpeg,.png,.gif,.webp,.pdf' ?>"><img class="attachment-preview edit-preview" alt="ตัวอย่างเอกสารใหม่" hidden></div><?php endforeach; ?></div></section>
<datalist id="term-options"><?php foreach (semesterOptions($conn) as $option): ?><option value="<?= htmlspecialchars($option) ?>"><?php endforeach; ?></datalist><div class="edit-toolbar"><a href="view.php?id=<?= $id ?>" class="btn btn-secondary">ยกเลิก</a><button class="btn btn-primary">บันทึกการแก้ไข</button></div></form></main><script>document.querySelectorAll('input[type=file]').forEach(input=>input.addEventListener('change',()=>{const img=input.parentElement.querySelector('img');img.hidden=true;const file=input.files[0];if(!file)return;if(file.size>5242880){input.value='';alert('ไฟล์ต้องไม่เกิน 5 MB');return;}if(file.type.startsWith('image/')){const url=URL.createObjectURL(file);img.onload=()=>URL.revokeObjectURL(url);img.src=url;img.hidden=false;}}));</script></body></html>
