<?php
require_once '../config.php';
require_once 'auth.php';
$conn = getDBConnection();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM registrations WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$reg = $stmt->get_result()->fetch_assoc();

if (!$reg) {
    header('Location: index.php');
    exit;
}

$thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

// Format birth date
$bd = new DateTime($reg['birth_date']);
$birthStr = intval($bd->format('d')) . ' ' . $thaiMonths[intval($bd->format('m'))] . ' ' . (intval($bd->format('Y')) + 543);

// Format registration date
$rd = new DateTime($reg['created_at']);
$regDateStr = intval($rd->format('d')) . ' ' . $thaiMonths[intval($rd->format('m'))] . ' ' . (intval($rd->format('Y')) + 543) . ' ' . $rd->format('H:i') . ' น.';

$statusText = ['pending' => 'รอดำเนินการ', 'approved' => 'อนุมัติ', 'rejected' => 'ไม่อนุมัติ'];
$statusClass = 'badge-' . $reg['status'];
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดผู้สมัคร #<?= $reg['id'] ?> | สกร.ระดับอำเภอเสนา</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= assetVersion('css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <div class="admin-header">
        <h1><i class="fas fa-user-circle" style="color:var(--primary-light)"></i> รายละเอียดผู้สมัคร #<?= $reg['id'] ?></h1>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="index.php?semester=<?= rawurlencode($reg['semester']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> กลับ</a>
            <a href="edit.php?id=<?= $reg['id'] ?>" class="btn btn-secondary btn-sm">แก้ไขข้อมูล / ภาคเรียน</a>
            <a href="download_all.php?id=<?= $reg['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-file-zipper"></i> ดาวน์โหลดข้อมูลและเอกสารทั้งหมด</a>
            <a href="logout.php" class="btn btn-danger btn-sm"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
            <?php if ($reg['status'] === 'pending'): ?>
                <button class="btn btn-sm" style="background:var(--success);color:white;" onclick="updateStatus(<?= $reg['id'] ?>, 'approved')"><i class="fas fa-check"></i> อนุมัติ</button>
                <button class="btn btn-sm" style="background:var(--danger);color:white;" onclick="updateStatus(<?= $reg['id'] ?>, 'rejected')"><i class="fas fa-times"></i> ไม่อนุมัติ</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-container">
        <?php if (!empty($_SESSION['registration_notice'])): ?><p class="notice success" role="status"><?= htmlspecialchars($_SESSION['registration_notice']) ?></p><?php unset($_SESSION['registration_notice']); endif; ?>
        <!-- Status and Photo -->
        <div style="display:flex;gap:24px;margin-bottom:24px;flex-wrap:wrap;align-items:start;">
            <?php if ($reg['photo_file']): ?>
                <img src="document.php?id=<?= $reg['id'] ?>&amp;field=photo_file" class="profile-photo" alt="รูปถ่ายผู้สมัคร">
            <?php else: ?>
                <div class="profile-photo" style="background:var(--card-bg);display:flex;align-items:center;justify-content:center;font-size:3rem;">👤</div>
            <?php endif; ?>
            <div>
                <h2 style="font-size:1.4rem;margin-bottom:4px;"><?= htmlspecialchars($reg['title'] . $reg['first_name'] . ' ' . $reg['last_name']) ?></h2>
                <p style="color:var(--text-secondary);font-size:0.9rem;margin-bottom:8px;"><?= htmlspecialchars($reg['education_level']) ?> | <?= htmlspecialchars($reg['subdistrict_center']) ?></p>
                <span class="badge <?= $statusClass ?>"><?= $statusText[$reg['status']] ?? $reg['status'] ?></span>
                <p style="color:var(--text-muted);font-size:0.8rem;margin-top:8px;"><i class="fas fa-clock"></i> สมัครเมื่อ: <?= $regDateStr ?></p>
            </div>
        </div>

        <!-- Section 1: Personal Info -->
        <div class="detail-card">
            <div class="detail-title"><i class="fas fa-user" style="color:var(--primary-light)"></i> ข้อมูลส่วนตัว</div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">ระดับที่สมัคร</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['education_level']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">ศกร.ระดับตำบล</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['subdistrict_center']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">ชื่อ-นามสกุล</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['title'] . $reg['first_name'] . ' ' . $reg['last_name']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">วันเกิด</div>
                    <div class="detail-value"><?= $birthStr ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">อายุ</div>
                    <div class="detail-value"><?= $reg['age'] ?> ปี</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">เลขบัตรประชาชน</div>
                    <div class="detail-value" style="font-family:monospace;"><?= htmlspecialchars($reg['id_card_number']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">ศาสนา</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['religion'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">สัญชาติ</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['nationality'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">อาชีพ</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['current_occupation'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">กลุ่มเป้าหมาย</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['target_group'] ?: '-') ?></div>
                </div>
            </div>
        </div>

        <!-- Section 2: Family -->
        <div class="detail-card">
            <div class="detail-title"><i class="fas fa-users" style="color:var(--accent)"></i> ข้อมูลครอบครัว</div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">ชื่อบิดา</div>
                    <div class="detail-value"><?= htmlspecialchars(($reg['father_first_name'] ?? '') . ' ' . ($reg['father_last_name'] ?? '')) ?: '-' ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">ชื่อมารดา</div>
                    <div class="detail-value"><?= htmlspecialchars(($reg['mother_first_name'] ?? '') . ' ' . ($reg['mother_last_name'] ?? '')) ?: '-' ?></div>
                </div>
            </div>
        </div>

        <!-- Section 3: Education -->
        <div class="detail-card">
            <div class="detail-title"><i class="fas fa-graduation-cap" style="color:var(--warning)"></i> ประวัติการศึกษาเดิม</div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">จบชั้น</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['previous_education_level'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">จบเมื่อ พ.ศ.</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['graduation_year'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">สถานศึกษา</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['previous_school'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">อำเภอ</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['previous_district'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">จังหวัด</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['previous_province'] ?: '-') ?></div>
                </div>
            </div>
        </div>

        <!-- Section 4: Address -->
        <div class="detail-card">
            <div class="detail-title"><i class="fas fa-map-marker-alt" style="color:var(--secondary)"></i> ที่อยู่และการติดต่อ</div>
            <h4 style="font-size:0.85rem;color:var(--primary-light);margin-bottom:12px;">ที่อยู่ตามทะเบียนบ้าน</h4>
            <div class="detail-grid" style="margin-bottom:20px;">
                <div class="detail-item">
                    <div class="detail-label">บ้านเลขที่</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['reg_house_no'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">หมู่</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['reg_moo'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">ถนน</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['reg_road'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">ตำบล</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['reg_sub_district'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">อำเภอ</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['reg_district'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">จังหวัด</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['reg_province'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">รหัสไปรษณีย์</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['reg_postal_code'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">เบอร์โทร</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['reg_phone'] ?: '-') ?></div>
                </div>
            </div>
            <h4 style="font-size:0.85rem;color:var(--primary-light);margin-bottom:12px;">ที่อยู่ปัจจุบัน</h4>
            <div class="detail-grid" style="margin-bottom:20px;">
                <div class="detail-item">
                    <div class="detail-label">บ้านเลขที่</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['cur_house_no'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">หมู่</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['cur_moo'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">ถนน</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['cur_road'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">ตำบล</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['cur_sub_district'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">อำเภอ</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['cur_district'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">จังหวัด</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['cur_province'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">รหัสไปรษณีย์</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['cur_postal_code'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">เบอร์โทร</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['cur_phone'] ?: '-') ?></div>
                </div>
            </div>
            <h4 style="font-size:0.85rem;color:var(--primary-light);margin-bottom:12px;">โซเชียลมีเดีย</h4>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Facebook</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['facebook'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Line ID</div>
                    <div class="detail-value"><?= htmlspecialchars($reg['line_id'] ?: '-') ?></div>
                </div>
            </div>
        </div>

        <p class="term-pill">ภาคเรียน <?= htmlspecialchars(semesterLabel($reg['semester'])) ?></p>
        <!-- Section 5: Documents -->
        <div class="detail-card">
            <div class="detail-title"><i class="fas fa-paperclip" style="color:var(--success)"></i> เอกสารแนบ</div>
            <div class="document-gallery">
            <?php foreach (['photo_file'=>['photos','รูปถ่าย'], 'id_card_file'=>['id_cards','บัตรประชาชน'], 'house_reg_file'=>['house_registrations','ทะเบียนบ้าน'], 'certificate_file'=>['certificates','วุฒิการศึกษา ด้านหน้า'], 'certificate_back_file'=>['certificates','วุฒิการศึกษา ด้านหลัง']] as $field => $doc): ?>
                <article class="document-tile"><h3><?= $doc[1] ?></h3>
                <?php if (!empty($reg[$field])): $url = 'document.php?id=' . $reg['id'] . '&field=' . $field; ?>
                    <?php if (strtolower(pathinfo($reg[$field], PATHINFO_EXTENSION)) !== 'pdf'): ?>
                    <button class="document-thumbnail" type="button" data-document-url="<?= htmlspecialchars($url) ?>" data-type="image" data-label="<?= $doc[1] ?>" aria-label="<?= $doc[1] ?>"><img src="<?= htmlspecialchars($url) ?>" loading="lazy" alt="<?= $doc[1] ?>" onerror="this.replaceWith(document.createTextNode('ไม่พบไฟล์ภาพ'))"></button>
                    <?php else: ?><button type="button" class="pdf-placeholder document-thumbnail" data-document-url="<?= htmlspecialchars($url) ?>" data-type="pdf" data-label="<?= $doc[1] ?>"><i class="fas fa-file-pdf"></i><p>เอกสาร PDF</p></button><?php endif; ?>
                    <a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener">เปิดดูขนาดเต็ม</a>
                    <a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($url) ?>&amp;download=1">ดาวน์โหลด</a>
                <?php else: ?><p class="missing-document">ยังไม่ได้แนบเอกสาร</p><?php endif; ?>
                </article>
            <?php endforeach; ?>
            </div>
        </div>
    </div>

<dialog id="documentViewer" class="document-viewer" aria-labelledby="viewerTitle"><header class="viewer-header"><h2 id="viewerTitle">เอกสารแนบ</h2><button type="button" class="btn btn-secondary btn-sm" data-viewer-action="close" aria-label="ปิดตัวแสดงเอกสาร">ปิด ×</button></header><div class="viewer-toolbar"><button type="button" class="btn btn-secondary btn-sm" data-image-tool data-viewer-action="out" aria-label="ย่อภาพ">−</button><span class="viewer-scale" data-image-tool>100%</span><button type="button" class="btn btn-secondary btn-sm" data-image-tool data-viewer-action="in" aria-label="ขยายภาพ">+</button><button type="button" class="btn btn-secondary btn-sm" data-image-tool data-viewer-action="rotate">หมุนภาพ</button><button type="button" class="btn btn-secondary btn-sm" data-image-tool data-viewer-action="fit">พอดีหน้าจอ</button><button type="button" class="btn btn-secondary btn-sm" data-pdf-tool data-viewer-action="previous" hidden>หน้าก่อน</button><span class="pdf-page-label" data-pdf-tool hidden></span><button type="button" class="btn btn-secondary btn-sm" data-pdf-tool data-viewer-action="next" hidden>หน้าถัดไป</button><a class="viewer-original btn btn-secondary btn-sm" target="_blank" rel="noopener">เปิดแท็บใหม่</a><a class="viewer-download btn btn-primary btn-sm">ดาวน์โหลด</a></div><div class="viewer-stage"><div class="viewer-canvas"><img alt="เอกสารที่เลือก"></div><p class="pdf-status" role="status" hidden></p><canvas class="pdf-canvas" aria-label="หน้าเอกสาร PDF ที่เลือก" hidden></canvas></div></dialog>
<script src="../js/document-viewer.js?v=<?= assetVersion('js/document-viewer.js') ?>" defer></script>

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
                            } else { Swal.fire('ดำเนินการไม่สำเร็จ',data.message,'error'); }
                        }).catch(()=>Swal.fire('เชื่อมต่อไม่สำเร็จ','กรุณาลองใหม่','error'));
                }
            });
        }
    </script>
</body>

</html>