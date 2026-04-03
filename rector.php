<?php

declare(strict_types=1);

use Rector\Configuration\RectorConfigBuilder;

/** @var RectorConfigBuilder $rectorConfig */
$rectorConfig = require 'vendor/justbetter/laravel-coding-standard/rector.php';

$rectorConfig->withPaths([
    __DIR__ . '/src',
    __DIR__ . '/tests',
]);

/** Define additional rules here
 * @see: https://getrector.com/find-rule?activeRectorSetGroup=laravel
 * @see: https://getrector.com/find-rule?activeRectorSetGroup=php
 * @see: https://getrector.com/find-rule?activeRectorSetGroup=core
 */
$rectorConfig->withPreparedSets(
    typeDeclarationDocblocks: true, // https://getrector.com/find-rule?rectorSet=core-type-declarations&activeRectorSetGroup=core
    typeDeclarations: true,     // https://getrector.com/find-rule?activeRectorSetGroup=core&rectorSet=core-type-declarations
    codeQuality: true,          // https://getrector.com/find-rule?activeRectorSetGroup=core&rectorSet=core-code-quality
    codingStyle: true,          // https://getrector.com/find-rule?activeRectorSetGroup=core&rectorSet=core-coding-style
    deadCode: true,             // https://getrector.com/find-rule?activeRectorSetGroup=core&rectorSet=core-dead-code
    instanceOf: true,       // https://getrector.com/find-rule?rectorSet=core-instanceof&activeRectorSetGroup=core
    earlyReturn: true,      // https://getrector.com/find-rule?rectorSet=core-early-return&activeRectorSetGroup=core
);

return $rectorConfig;
