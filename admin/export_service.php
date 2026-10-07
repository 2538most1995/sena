<?php
function studentExportName($row) {
    $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) . '_' . ($row['subdistrict_center'] ?? 'ไม่ระบุตำบล');
    $name = preg_replace('/[\x00-\x1f\x7f\\\\\/:*?"<>|]/u', '_', $name);
    return trim($name, " ._") ?: 'นักศึกษา';
}
function exportXml($text) {
    return htmlspecialchars(preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/u', '', (string)$text), ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
function studentWord($row, $path) {
    $groups = require __DIR__.'/export_fields.php';
    $paragraph = function($text, $style = '') {
        return '<w:p><w:pPr>'.($style ? '<w:pStyle w:val="'.$style.'"/>' : '').'</w:pPr><w:r><w:t xml:space="preserve">'.exportXml($text).'</w:t></w:r></w:p>';
    };
    $body = $paragraph('ข้อมูลผู้สมัครเรียน', 'Title');
    $body .= $paragraph(($row['title'] ?? '').($row['first_name'] ?? '').' '.($row['last_name'] ?? ''));
    $body .= $paragraph('ข้อมูลการสมัคร ส่วนตัว ครอบครัว การศึกษา และช่องทางติดต่อของนักศึกษา');
    $handled = [];
    foreach ($groups as $title => $fields) {
        $body .= $paragraph($title, 'Heading1');
        foreach ($fields as $field => $label) {
            $handled[$field] = true;
            $value = $row[$field] ?? '';
            if ($field === 'status') $value = ['pending'=>'รอดำเนินการ','approved'=>'อนุมัติ','rejected'=>'ไม่อนุมัติ'][$value] ?? $value;
            $body .= $paragraph($label.' : '.($value === '' || $value === null ? '-' : $value));
        }
    }
    $body .= $paragraph('ข้อมูลระบบและไฟล์แนบ', 'Heading1');
    $labels = ['id'=>'เลขทะเบียน','created_at'=>'วันที่สมัคร','updated_at'=>'แก้ไขล่าสุด','photo_file'=>'รูปถ่าย','id_card_file'=>'บัตรประชาชน','house_reg_file'=>'ทะเบียนบ้าน','certificate_file'=>'วุฒิการศึกษาด้านหน้า','certificate_back_file'=>'วุฒิการศึกษาด้านหลัง'];
    foreach ($row as $field=>$value) if (!isset($handled[$field])) $body .= $paragraph(($labels[$field] ?? $field).' : '.($value === '' || $value === null ? '-' : $value));
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new Exception('สร้าง Word ไม่สำเร็จ');
    try {
        $parts = [
            '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>',
            '_rels/.rels'=>'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            'word/_rels/document.xml.rels'=>'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'word/styles.xml'=>'<?xml version="1.0" encoding="UTF-8"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Tahoma" w:hAnsi="Tahoma" w:eastAsia="Tahoma" w:cs="Tahoma"/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="th-TH"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="100"/></w:pPr></w:pPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style><w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:rPr><w:b/><w:sz w:val="36"/></w:rPr></w:style><w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="240" w:after="120"/><w:outlineLvl w:val="0"/></w:pPr><w:rPr><w:b/><w:sz w:val="28"/></w:rPr></w:style></w:styles>',
            'word/document.xml'=>'<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr></w:body></w:document>'
        ];
        foreach ($parts as $name=>$content) if (!$zip->addFromString($name,$content)) throw new Exception('เขียน Word ไม่สำเร็จ');
    } finally { if (!$zip->close()) throw new Exception('บันทึก Word ไม่สำเร็จ'); }
}
// Downloads are temporary exports, never persistent recovery backups.
function createStudentExport($rows, $attachmentReader = null) {
    if (!$rows) throw new Exception('ไม่พบข้อมูลผู้สมัคร');
    if (!class_exists('ZipArchive')) throw new Exception('ไม่พบส่วนขยาย ZIP');
    $path = tempnam(sys_get_temp_dir(), 'sena_export_');
    if ($path === false) throw new Exception('สร้างไฟล์ชั่วคราวไม่ได้');
    $zip = new ZipArchive(); $wordFiles = [];
    try {
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) throw new Exception('สร้าง ZIP ไม่สำเร็จ');
        $map = ['photo_file'=>['photos','รูปถ่าย'],'id_card_file'=>['id_cards','บัตรประชาชน'],'house_reg_file'=>['house_registrations','ทะเบียนบ้าน'],'certificate_file'=>['certificates','วุฒิการศึกษาด้านหน้า'],'certificate_back_file'=>['certificates','วุฒิการศึกษาด้านหลัง']];
        foreach ($rows as $row) {
            $base = studentExportName($row).(count($rows)>1 ? '_'.$row['id'] : '');
            $exportRow = $row;
            foreach ($map as $field=>[$folder,$label]) {
                if (empty($row[$field])) continue;
                $entry = $base.'_'.$label.'.'.strtolower(pathinfo($row[$field],PATHINFO_EXTENSION));
                $exportRow[$field] = $entry;
                if ($attachmentReader) {
                    $content = $attachmentReader($folder,basename($row[$field]));
                    if ($content === false || !$zip->addFromString($entry,$content)) throw new Exception('อ่านไฟล์แนบไม่สำเร็จ');
                } else {
                    $file = UPLOAD_DIR.$folder.'/'.basename($row[$field]);
                    if (!is_file($file) || !is_readable($file)) throw new Exception('ไม่พบไฟล์แนบ '.$label.' ของ '.$base);
                    if (!$zip->addFile($file,$entry)) throw new Exception('เพิ่มไฟล์แนบไม่สำเร็จ');
                }
            }
            $stream = fopen('php://temp','w+');
            fwrite($stream,"\xEF\xBB\xBF"); fputcsv($stream,array_keys($exportRow));
            fputcsv($stream,array_map(function($value) {
                $text=(string)($value ?? '');
                return preg_match('/^[=+@\-\t\r]/',$text) || preg_match('/^0[0-9]+$/D',$text) || preg_match('/^[0-9]{13}$/D',$text) ? "'".$text : $text;
            },array_values($exportRow)));
            rewind($stream);$csv=stream_get_contents($stream);fclose($stream);
            if (!$zip->addFromString($base.'.csv',$csv)) throw new Exception('เพิ่ม CSV ไม่สำเร็จ');
            $word = tempnam(sys_get_temp_dir(),'sena_word_');
            if ($word === false) throw new Exception('สร้าง Word ชั่วคราวไม่ได้');
            $wordFiles[]=$word;studentWord($exportRow,$word);
            if (!$zip->addFile($word,$base.'.docx')) throw new Exception('เพิ่ม Word ไม่สำเร็จ');
        }
        if (!$zip->close()) throw new Exception('บันทึก ZIP ไม่สำเร็จ');
        return $path;
    } catch(Throwable $e) {
        try { $zip->close(); } catch(Throwable $ignored) {}
        @unlink($path); throw $e;
    } finally { foreach ($wordFiles as $word) @unlink($word); }
}
function sendStudentExport($path, $name) {
    header('Content-Type: application/zip');header('Cache-Control: private, no-store');
    header('Content-Disposition: attachment; filename="students.zip"; filename*=UTF-8\'\''.rawurlencode($name));
    header('Content-Length: '.filesize($path));readfile($path);
}
