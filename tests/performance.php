<?php
require __DIR__.'/../config.php';
function performanceCheck($ok,$message) { if (!$ok) throw new RuntimeException($message); }
$conn=new mysqli(DB_HOST,DB_USER,DB_PASS,'',DB_PORT);
$db='sena_perf_'.bin2hex(random_bytes(6));
performanceCheck($conn->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4"),'Create isolated database');
$conn->select_db($db);$conn->set_charset('utf8mb4');
try {
    performanceCheck($conn->query('CREATE TABLE registrations LIKE '.DB_NAME.'.registrations'),'Copy registration schema');
    performanceCheck($conn->query('CREATE TABLE settings LIKE '.DB_NAME.'.settings'),'Copy settings schema');
    ensureApplicationSchema($conn);
    $stmt=$conn->prepare("INSERT INTO registrations (semester,id_card_number,education_level,subdistrict_center,title,first_name,last_name,birth_date,status) VALUES (?,?,'ม.ปลาย','ศกร.ระดับตำบลบ้านหลวง','นาย','ทดสอบ','ความเร็ว','2000-01-01',?)");
    $conn->begin_transaction();
    for($i=0;$i<3000;$i++) {
        $term=($i%2+1).'/'.(2560+(int)(($i%20)/2));$card=str_pad((string)$i,13,'0',STR_PAD_LEFT);$status=['pending','approved','rejected'][$i%3];
        $stmt->bind_param('sss',$term,$card,$status);performanceCheck($stmt->execute(),'Insert synthetic row');
    }
    $conn->commit();$conn->query('ANALYZE TABLE registrations');
    $plans=[];
    foreach([
        'latest'=>"SELECT id,title,first_name FROM registrations WHERE semester='2/2569' ORDER BY created_at DESC,id DESC LIMIT 20",
        'status'=>"SELECT id FROM registrations WHERE semester='2/2569' AND status='approved' ORDER BY created_at DESC,id DESC LIMIT 20",
        'id_card'=>"SELECT id FROM registrations WHERE semester='2/2569' AND id_card_number='0000000000019'"
    ] as $name=>$sql) {
        $plan=$conn->query('EXPLAIN '.$sql)->fetch_assoc();
        performanceCheck(!empty($plan['key']),$name.' uses an index');
        performanceCheck(!str_contains($plan['Extra'] ?? '', 'Using filesort'),$name.' avoids filesort');
        $plans[$name]=$plan['key'];
    }
    performanceCheck((int)$conn->query("SELECT COUNT(*) n FROM registrations WHERE semester='2/2569'")->fetch_assoc()['n']===150,'Semester count correctness');
    $before=$conn->query("SHOW SESSION STATUS LIKE 'Com_alter_table'")->fetch_assoc()['Value'];
    ensureApplicationSchema($conn);ensureApplicationSchema($conn);
    $after=$conn->query("SHOW SESSION STATUS LIKE 'Com_alter_table'")->fetch_assoc()['Value'];
    performanceCheck($before===$after,'Repeat requests do not alter schema');
    echo 'PASS: 3000 synthetic applicants; indexed sorting/filtering/exact ID lookup; correct term counts; migrations run once. Plans: '.json_encode($plans)."\n";
} finally { $conn->query("DROP DATABASE `$db`"); }
