<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$migrator = $app['migrator'];
echo "Migrator paths:\n";
foreach ($migrator->paths() as $p) {
    echo "  - $p\n";
}
echo "\ndatabase/migrations exists: " . (is_dir('/var/www/html/database/migrations') ? 'yes' : 'no') . "\n";
echo "migrate command paths:\n";
$cmd = $app->make(Illuminate\Database\Console\Migrations\MigrateCommand::class);
$r = new ReflectionMethod($cmd, 'getMigrationPaths');
$r->setAccessible(true);
foreach ($r->invoke($cmd) as $p) {
    echo "  - $p\n";
}
