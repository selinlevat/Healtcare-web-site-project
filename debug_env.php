<?php
header('Content-Type: text/plain; charset=utf-8');

$keys = ['MYSQLHOST','MYSQLUSER','MYSQLPASSWORD','MYSQLDATABASE','MYSQLPORT','DB_HOST','DB_USER','DB_PASS','DB_NAME','DB_PORT'];
foreach ($keys as $k) {
    $v = getenv($k);
    echo $k . " = " . ($v === false ? "NOT SET" : ($k === 'MYSQLPASSWORD' || $k === 'DB_PASS' ? '***SET***' : $v)) . PHP_EOL;
}
