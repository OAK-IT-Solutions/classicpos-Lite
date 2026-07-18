<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sync:process-queue')->everyMinute();
Schedule::command('sync:process-payments')->everyMinute();
Schedule::command('inventory:check-low-stock --notify')->hourly();
Schedule::command('audit:prune')->weekly();
