<?php
// This tracked loader contains no machine-specific database values.
// Keep each server's values in the ignored config.local.php or its environment.
$localConfigFile = __DIR__ . '/config.local.php';
$localConfig = is_file($localConfigFile) ? require $localConfigFile : [];
if (!is_array($localConfig)) $localConfig = []; // Also support files defining constants.

function senaConfig($env, $key, $default = null) {
    global $localConfig;
    $value = getenv($env);
    if ($value !== false) return $value;
    if (array_key_exists($env, $localConfig)) return $localConfig[$env];
    if (array_key_exists($key, $localConfig)) return $localConfig[$key];
    return $default;
}

$databaseKeys = [
    'DB_HOST' => 'db_host',
    'DB_USER' => 'db_user',
    'DB_PASS' => 'db_password',
    'DB_NAME' => 'db_name',
    'DB_PORT' => 'db_port',
];
$databaseValues = [];
foreach ($databaseKeys as $constant => $key) {
    // Preserve constants already defined by this server's bootstrap/config file.
    $value = defined($constant) ? constant($constant) : senaConfig($constant, $key);
    // Backward-compatible test/deployment alias; standard DB_NAME takes precedence.
    if ($constant === 'DB_NAME' && !defined($constant) && getenv('DB_NAME') === false && getenv('SENA_DB_NAME') !== false) {
        $value = getenv('SENA_DB_NAME');
    }
    if (!is_scalar($value) || ($constant !== 'DB_PASS' && trim((string)$value) === '')) {
        throw new RuntimeException('ยังไม่ได้ตั้งค่า ' . $constant . ' กรุณาตั้งค่าใน config.local.php ของเครื่องนี้ หรือ Environment Variable');
    }
    if ($constant === 'DB_PORT' && (!ctype_digit((string)$value) || (int)$value < 1 || (int)$value > 65535)) {
        throw new RuntimeException('DB_PORT ต้องเป็นเลขพอร์ตระหว่าง 1 ถึง 65535');
    }
    $databaseValues[$constant] = $constant === 'DB_PORT' ? (int)$value : (string)$value;
}
foreach ($databaseValues as $constant => $value) if (!defined($constant)) define($constant, $value);

if (!defined('SMTP_USER')) define('SMTP_USER', senaConfig('SMTP_USER', 'smtp_user', ''));
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', senaConfig('SMTP_PASSWORD', 'smtp_password', ''));
if (!defined('SMTP_FROM')) define('SMTP_FROM', senaConfig('SMTP_FROM', 'smtp_from', SMTP_USER));
