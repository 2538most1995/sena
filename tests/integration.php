<?php
require __DIR__.'/../config.php'; require __DIR__.'/../admin/backup_service.php';
function check($condition,$message) { if (!$condition) throw new RuntimeException($message); }
$conn=new mysqli(DB_HOST,DB_USER,DB_PASS,'',DB_PORT);
$db='sena_test_'.bin2hex(random_bytes(6));$archives=[];$fixture=null;
putenv('SENA_BACKUP_DIR='.sys_get_temp_dir().'/'.$db);
check($conn->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4"),'Create test database');$conn->select_db($db);$conn->set_charset('utf8mb4');
try {
    check($conn->query('CREATE TABLE registrations LIKE '.DB_NAME.'.registrations'),'Copy schema');
    check($conn->query('CREATE TABLE settings LIKE '.DB_NAME.'.settings'),'Copy settings schema');
    $conn->query('ALTER TABLE registrations DROP INDEX uk_semester_id, DROP COLUMN semester, ADD UNIQUE KEY uk_id_card (id_card_number)');
    $insert="INSERT INTO registrations (education_level,subdistrict_center,title,first_name,last_name,birth_date,id_card_number) VALUES ('test','test','test','before','test','2000-01-01','1234567890123')";
    check($conn->query($insert),'Create legacy applicant'); ensureSemesterSchema($conn);
    check($conn->query('SELECT semester FROM registrations')->fetch_assoc()['semester']==='legacy','Legacy migration label');
    $conn->query("UPDATE registrations SET semester='1/2569'");
    $insert="INSERT INTO registrations (education_level,subdistrict_center,title,first_name,last_name,birth_date,id_card_number,semester) VALUES ('test','test','test','before','test','2000-01-01','1234567890123','2/2569')";
    check($conn->query($insert),'Cross-semester uniqueness');check(!$conn->query($insert),'Same-semester uniqueness');
    $id=(int)$conn->insert_id;
    $id=(int)$conn->query("SELECT id FROM registrations WHERE semester='2/2569'")->fetch_assoc()['id'];
    $conn->query("UPDATE registrations SET photo_file='test_missing.jpg' WHERE id=$id");
    $failed=false;try { backupAndClear($conn,null,$id,true); } catch (Throwable $e) { $failed=true; }
    check($failed && $conn->query('SELECT id FROM registrations')->num_rows===2,'Missing file prevents deletion');
    ensureUploadDirs();$filename='test_'.bin2hex(random_bytes(8)).'.png';$fixture=UPLOAD_PHOTOS.$filename;
    file_put_contents($fixture,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN2kAAAAASUVORK5CYII='));
    $stmt=$conn->prepare('UPDATE registrations SET photo_file=? WHERE id=?');$stmt->bind_param('si',$filename,$id);$stmt->execute();
    $archives[]=backupAndClear($conn,null,$id,false);
    check($conn->query('SELECT id FROM registrations')->num_rows===2,'Backup alone preserves records');
    $stale=hash('sha256',json_encode($conn->query("SELECT * FROM registrations WHERE id=$id")->fetch_assoc()));
    $archives[]=backupAndClear($conn,null,$id,false,null,['first_name'=>'after']);
    $failed=false;try{backupAndClear($conn,null,$id,false,null,['first_name'=>'stale'],$stale);}catch(Throwable $e){$failed=true;}check($failed,'Stale edit rejected');
    check($conn->query("SELECT first_name FROM registrations WHERE id=$id")->fetch_assoc()['first_name']==='after','Edit transaction');
    $failed=false;try {backupAndClear($conn,null,$id,false,'1/2569');}catch(Throwable $e){$failed=true;}
    check($failed && $conn->query("SELECT semester FROM registrations WHERE id=$id")->fetch_assoc()['semester']==='2/2569','Conflicting move rolls back');
    $archives[]=backupAndClear($conn,'2/2569',null,true);$restore=end($archives);
    check($conn->query('SELECT id FROM registrations')->num_rows===1,'Semester clear isolation');check(cleanupArchivedFiles($conn,$restore)['removed']===1,'Clear backed-up source attachment');
    $result=restoreBackup($conn,$restore);check($result['restored']===1 && is_file($fixture),'Restore row and attachment');
    check(restoreBackup($conn,$restore)['skipped']===1,'Repeated restore skips duplicate');
    $newId=(int)$conn->query("SELECT id FROM registrations WHERE semester='2/2569'")->fetch_assoc()['id'];
    check($newId!==$id,'Restoration assigns fresh ID');
    $zip=new ZipArchive();$zip->open(backupDirectory().'/'.$restore);$zip->addFromString('registrations.json','corrupted');$zip->close();
    $failed=false;try{restoreBackup($conn,$restore);}catch(Throwable $e){$failed=true;}
    check($failed && $conn->query('SELECT id FROM registrations')->num_rows===2,'Corrupt archive rejected without writes');
    $failed=false;try{inspectBackup('../escape.zip');}catch(Throwable $e){$failed=true;}check($failed,'Unsafe backup path rejected');
    echo "PASS: legacy migration; semester uniqueness; missing-document rollback; backup-only; backed-up edit; conflicting move rollback; semester isolation; restore data/files; duplicate restore; new IDs; corruption rejection; unsafe path rejection\n";
} finally {
    foreach($archives as $name) if(is_file(backupDirectory().'/'.$name)) unlink(backupDirectory().'/'.$name);
    if($fixture && is_file($fixture)) unlink($fixture);
    $conn->query("DROP DATABASE `$db`");
    foreach(glob(backupDirectory().'/sena_*.zip') as $file) unlink($file); rmdir(backupDirectory());
}
