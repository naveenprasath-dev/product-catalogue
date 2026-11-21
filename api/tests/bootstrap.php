<?php

declare(strict_types=1);

/*
 * PHPUnit bootstrap: generates a throw-away APP_KEY for this test run, so no key is ever
 * hard-coded in the repo (GitGuardian flagged the fixed test key that used to be in phpunit.xml).
 * It overrides whatever the container exports, like the other forced test settings.
 */

require __DIR__.'/../vendor/autoload.php';

$appKey = 'base64:'.base64_encode(random_bytes(32));

putenv("APP_KEY={$appKey}");
$_ENV['APP_KEY'] = $appKey;
$_SERVER['APP_KEY'] = $appKey;
