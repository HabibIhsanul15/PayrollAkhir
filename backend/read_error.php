<?php
$log = file_get_contents('storage/logs/laravel.log');
$parts = explode('[2026-', $log);
echo "[2026-" . end($parts);
