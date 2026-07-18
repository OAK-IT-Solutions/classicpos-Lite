<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.pgsql.database' => 'classicpos_testing']);
        $this->app['env'] = 'testing';
    }
}
