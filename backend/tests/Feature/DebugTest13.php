<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugTest13 extends TestCase
{
    use RefreshDatabase;

    public function test_check_env()
    {
        print "\n[DEBUG13]\n";
        print "APP_ENV from env(): " . env('APP_ENV', 'NOT SET') . "\n";
        print "APP_ENV from \$_ENV: " . ($_ENV['APP_ENV'] ?? 'NOT SET') . "\n";
        print "APP_ENV from \$_SERVER: " . ($_SERVER['APP_ENV'] ?? 'NOT SET') . "\n";
        print "app()->environment(): " . app()->environment() . "\n";
        print "config(app.env): " . config('app.env') . "\n";
        print "CLASSICPOS_SELF_HOSTED: " . (env('CLASSICPOS_SELF_HOSTED', 'not set')) . "\n";

        $this->assertTrue(true);
    }
}
