<?php
putenv("APP_ENV=testing");
echo "getenv: " . getenv("APP_ENV") . PHP_EOL;
echo "_ENV: " . ($_ENV["APP_ENV"] ?? "NOT SET") . PHP_EOL;
echo "_SERVER: " . ($_SERVER["APP_ENV"] ?? "NOT SET") . PHP_EOL;
