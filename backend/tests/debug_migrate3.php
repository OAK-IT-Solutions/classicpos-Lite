<?php
putenv('DB_HOST=db');
putenv('DB_PORT=5444');
putenv('DB_DATABASE=classicpos_testing');
putenv('DB_USERNAME=classicpos');
putenv('DB_PASSWORD=secret');
putenv('LANDLORD_DB_HOST=db');
putenv('LANDLORD_DB_PORT=5444');
putenv('LANDLORD_DB_DATABASE=classicpos_testing');
putenv('LANDLORD_DB_USERNAME=classicpos');
putenv('LANDLORD_DB_PASSWORD=secret');
putenv('APP_ENV=testing');
putenv('CLASSICPOS_SELF_HOSTED=true');

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Default connection: " . config('database.default') . "\n";
echo "DB database: " . config('database.connections.pgsql.database') . "\n";
echo "Landlord DB: " . config('database.connections.landlord.database') . "\n";

$defaultPath = $app->databasePath('migrations');
echo "Default migration path: $defaultPath\n";
echo "Exists: " . (is_dir($defaultPath) ? 'yes' : 'no') . "\n";

// Run migrate:status
$artisan = $app->make(Illuminate\Contracts\Console\Kernel::class);
$exitCode = $artisan->call('migrate:status');
echo "migrate:status exit code: $exitCode\n";
echo $artisan->output() . "\n";

// Now run migrate:fresh
$artisan2 = $app->make(Illuminate\Contracts\Console\Kernel::class);
$exitCode2 = $artisan2->call('migrate:fresh', ['--force' => true]);
echo "migrate:fresh exit code: $exitCode2\n";
echo $artisan2->output() . "\n";
