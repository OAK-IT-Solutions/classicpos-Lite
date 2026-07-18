<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->useEnvironmentPath('/var/www/html');
$app->loadEnvironmentFrom('.env.testing');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Set testing DB
config(['database.connections.pgsql.database' => 'classicpos_testing']);
config(['database.default' => 'pgsql']);

echo "Default connection: " . config('database.default') . "\n";
echo "DB database: " . config('database.connections.pgsql.database') . "\n";
echo "Landlord DB: " . config('database.connections.landlord.database') . "\n";

// Check the default migration path
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
