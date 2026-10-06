<?php
// Isolated config tests: no real credentials and no database connection.
$root=dirname(__DIR__);$temporary=sys_get_temp_dir().'/sena_config_'.bin2hex(random_bytes(6));mkdir($temporary,0700);
$keys=['DB_HOST','DB_USER','DB_PASS','DB_NAME','DB_PORT','SENA_DB_NAME','SMTP_USER','SMTP_PASSWORD','SMTP_FROM'];
$baseEnv=getenv();foreach($keys as $key)unset($baseEnv[$key]);
function configCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
function configRun($directory,$environment,$prefix=''){
    $code=$prefix.'require '.var_export($directory.'/runtime_config.php',true).'; echo json_encode([DB_HOST,DB_USER,DB_PASS,DB_NAME,DB_PORT]);';
    $process=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$environment);
    $out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    return [$exit,$out,$error];
}
try{
    copy($root.'/runtime_config.php',$temporary.'/runtime_config.php');
    [$exit,$out,$error]=configRun($temporary,$baseEnv);configCheck($exit!==0 && str_contains($error.$out,'DB_HOST'),'Missing settings must fail without MAMP fallback');
    $values=['DB_HOST'=>'production.example.invalid','DB_USER'=>'server_account','DB_PASS'=>'server_test_secret','DB_NAME'=>'server_database','DB_PORT'=>3307];
    $local='<?php return '.var_export($values,true).';';file_put_contents($temporary.'/config.local.php',$local);$hash=hash_file('sha256',$temporary.'/config.local.php');
    [$exit,$out]=configRun($temporary,$baseEnv);configCheck($exit===0 && json_decode($out,true)===array_values($values),'Private production settings load');
    copy($root.'/runtime_config.php',$temporary.'/runtime_config.php');
    [$exit,$out]=configRun($temporary,$baseEnv);configCheck($exit===0 && json_decode($out,true)===array_values($values) && hash_file('sha256',$temporary.'/config.local.php')===$hash,'Updating tracked loader preserves all five local values');
    $override=['DB_HOST'=>'env.example.invalid','DB_USER'=>'env_account','DB_PASS'=>'','DB_NAME'=>'env_database','DB_PORT'=>'3308'];
    [$exit,$out]=configRun($temporary,array_merge($baseEnv,$override));$expected=array_values($override);$expected[4]=3308;configCheck($exit===0 && json_decode($out,true)===$expected,'All standard environment names, including empty password');
    $prefix='';foreach($values as $key=>$value)$prefix.='define('.var_export($key,true).','.var_export($value,true).');';
    [$exit,$out]=configRun($temporary,array_merge($baseEnv,$override),$prefix);configCheck($exit===0 && json_decode($out,true)===array_values($values),'Predefined server constants preserved over environment');
    [$exit,$out]=configRun($temporary,array_merge($baseEnv,['SENA_DB_NAME'=>'test_database']));$expected=array_values($values);$expected[3]='test_database';configCheck($exit===0 && json_decode($out,true)===$expected,'SENA_DB_NAME alias compatible');
    file_put_contents($temporary.'/config.local.php','<?php '.$prefix);
    [$exit,$out]=configRun($temporary,$baseEnv);configCheck($exit===0 && json_decode($out,true)===array_values($values),'Private config may define server constants directly');
    file_put_contents($temporary.'/config.local.php',$local);
    [$exit]=configRun($temporary,array_merge($baseEnv,['DB_PORT'=>'0']));configCheck($exit!==0,'Invalid port rejected');
    echo "PASS: missing config fails; all five production values preserved after code update; environment values; predefined constants; legacy alias; constant-style private config; invalid port rejected\n";
}finally{foreach(glob($temporary.'/*') as $file)unlink($file);rmdir($temporary);}
