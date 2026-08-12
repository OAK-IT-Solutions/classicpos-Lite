<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugTest14 extends TestCase
{
    use RefreshDatabase;

    public function test_env_difference()
    {
        print "\n[DEBUG14]\n";
        print "getenv(APP_ENV): " . getenv('APP_ENV') . "\n";
        print "\$_ENV[APP_ENV]: " . ($_ENV['APP_ENV'] ?? 'NOT SET') . "\n";
        print "\$_SERVER[APP_ENV]: " . ($_SERVER['APP_ENV'] ?? 'NOT SET') . "\n";
        print "app()->environment(): " . app()->environment() . "\n";

        // Use $kernel->call to check the environment inside an artisan command
        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $kernel->call('env', [], $output);
        print "php artisan env output: " . $output->fetch() . "\n";

        $this->assertTrue(true);
    }
}
