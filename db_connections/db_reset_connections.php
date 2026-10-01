<?php

$host        = 'localhost';
$dbName      = 'RAW_HockeyPool_pre_prod';
$dbUser      = 'Ian';
$dbPassword  = 'howdydoody';
$dsn         = "pgsql:host={$host};dbname={$dbName}";

// These are static, trusted paths so we don’t quote them via escapeshellarg()
$backupDir   = '/Users/Ian/Sites/raw_hockeypool/schema_backups';
$psqlPath    = '/opt/homebrew/opt/postgresql@16/bin/psql';

?>