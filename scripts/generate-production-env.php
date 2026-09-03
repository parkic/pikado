#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$templatePath = $root.'/deploy/env.production.example';
$targetPath = $root.'/.env.production';

if (is_file($targetPath)) {
    fwrite(STDERR, ".env.production already exists; refusing to overwrite it.\n");
    exit(1);
}

$template = file_get_contents($templatePath);

if ($template === false) {
    fwrite(STDERR, "Unable to read deploy/env.production.example.\n");
    exit(1);
}

$adminPassword = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
$reverbKey = bin2hex(random_bytes(16));

$environment = strtr($template, [
    'CHANGE_ME_APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'CHANGE_ME_DB_PASSWORD' => bin2hex(random_bytes(24)),
    'CHANGE_ME_DB_ROOT_PASSWORD' => bin2hex(random_bytes(24)),
    'CHANGE_ME_REVERB_ID' => (string) random_int(100000, 999999),
    'CHANGE_ME_REVERB_KEY' => $reverbKey,
    'CHANGE_ME_REVERB_SECRET' => bin2hex(random_bytes(32)),
    'CHANGE_ME_ADMIN_PASSWORD' => $adminPassword,
]);

if (file_put_contents($targetPath, $environment, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write .env.production.\n");
    exit(1);
}

chmod($targetPath, 0600);

fwrite(STDOUT, ".env.production created with mode 0600.\n");
fwrite(STDOUT, "Temporary superadmin password: {$adminPassword}\n");
fwrite(STDOUT, "Save it in your password manager and change it after the first login.\n");
