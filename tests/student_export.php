<?php
require __DIR__.'/../admin/export_service.php';
function exportCheck($ok,$message) { if (!$ok) throw new RuntimeException($message); }
$row=['id'=>7,'first_name'=>'ทดสอบ','last_name'=>'นักศึกษา','subdistrict_center'=>'ตำบลเสนา','semester'=>'2/2569','id_card_number'=>'0123456789012','photo_file'=>'original.png','certificate_file'=>'certificate.pdf','facebook'=>'=formula','status'=>'pending'];
$path=null;$doc=null;
try {
    $path=createStudentExport([$row],fn($folder,$file)=>$folder==='photos' ? 'image contents' : 'pdf contents');
    $zip=new ZipArchive();exportCheck($zip->open($path)===true,'ZIP opens');
    exportCheck($zip->numFiles===4,'Only CSV Word and two attachments');
    $base=studentExportName($row);
    for($i=0;$i<$zip->numFiles;$i++) exportCheck(!str_contains($zip->getNameIndex($i),'/'),'Flat ZIP');
    exportCheck($zip->getFromName($base.'_รูปถ่าย.png')==='image contents','Photo preserved');
    exportCheck($zip->getFromName($base.'_วุฒิการศึกษาด้านหน้า.pdf')==='pdf contents','PDF preserved');
    $csv=$zip->getFromName($base.'.csv');exportCheck(str_contains($csv,"'0123456789012") && str_contains($csv,"'=formula"),'CSV identifiers and formulas protected');
    $doc=tempnam(sys_get_temp_dir(),'sena_doc_test_');file_put_contents($doc,$zip->getFromName($base.'.docx'));$zip->close();
    $word=new ZipArchive();exportCheck($word->open($doc)===true,'Real DOCX opens');
    $xml=$word->getFromName('word/document.xml');exportCheck(simplexml_load_string($xml)!==false,'Word XML valid');
    exportCheck(str_contains($xml,'ทดสอบ') && str_contains($xml,'เลขบัตรประชาชน') && str_contains($xml,$base.'_รูปถ่าย.png'),'Word contains applicant data and matching attachments');$word->close();
    exportCheck(!str_contains(studentExportName(['first_name'=>'../bad/','last_name'=>"\r\n",'subdistrict_center'=>'x:y']),'/'),'Filename sanitization');
    unlink($path);$path=createStudentExport([$row,array_merge($row,['id'=>8])],fn()=> 'contents');
    $zip=new ZipArchive();$zip->open($path);exportCheck($zip->numFiles===8,'Same names do not collide in semester export');$zip->close();
    unlink($path);$path=null;
    $failed=false;try { createStudentExport([$row],fn()=>false); } catch(Throwable $e) { $failed=true; }exportCheck($failed,'Missing attachments reject incomplete export');
    echo "PASS: flat ZIP; student and center filenames; CSV protection; DOCX data; all attachments; collision protection; missing files\n";
} finally { if($path)@unlink($path);if($doc)@unlink($doc); }
