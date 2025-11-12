<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\PhpCsFixer\Preset\Standard;
use Cline\PhpCsFixer\ConfigurationFactory;

$config = ConfigurationFactory::createFromPreset(
    new Standard(),
);

// Disable mb_string_functions rule because we need substr() for binary data
$config->setRules(array_merge(
    $config->getRules(),
    ['mb_str_functions' => false]
));

/** @var PhpCsFixer\Finder $finder */
$finder = $config->getFinder();
$finder->in([__DIR__.'/src', __DIR__.'/tests']);

return $config;
