<?php

$root = dirname(__DIR__);
chdir($root);
if (! is_file($root.'/vendor/autoload.php')) {
    exit("Run composer install before starting the application.\n");
}
if (! is_file($root.'/.env')) {
    $environment = file_get_contents($root.'/.env.example');
    $environment = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=base64:'.base64_encode(random_bytes(32)), $environment);
    file_put_contents($root.'/.env', $environment);
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    if (! $app->environment('local')) {
        throw new RuntimeException('This setup helper is for local demonstrations only.');
    }
    $config = config('database.connections.mysql');
    $database = $config['database'];
    if ($database !== 'fiji_employment_platform_v2') {
        throw new RuntimeException('Use the separate fiji_employment_platform_v2 database for this submission.');
    }
    $pdo = new PDO('mysql:host='.$config['host'].';port='.$config['port'].';charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS fiji_employment_platform_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $code = Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    echo Illuminate\Support\Facades\Artisan::output();
    if ($code !== 0) { exit($code); }
    if (! App\Models\User::exists()) {
        $code = Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        echo Illuminate\Support\Facades\Artisan::output();
        if ($code !== 0) { exit($code); }
    }
    echo "\nReady. Sample sign-ins (password: password):\n";
    echo "Admin: admin@example.com\nEmployer: employer@example.com\nJobseeker: jobseeker@example.com\nMentor: mentor1@example.com\n";
    echo "These fictional sample accounts are for local assessment only.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Setup failed: ".$exception->getMessage()."\nStart MySQL in XAMPP and check the database settings in .env.\n");
    exit(1);
}
