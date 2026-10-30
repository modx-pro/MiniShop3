<?php

/**
 * Basenames excluded from the core file vehicle (#781).
 *
 * xPDOFileVehicle::_compilePayload() copies the source tree without exclude
 * options, so the build stages a filtered tree first. Names are matched with
 * copyTree's copy_exclude_items (basename only at each directory level).
 *
 * @return list<string>
 */
function ms3BuildCoreExcludeItems(): array
{
    return [
        'tests',
        'scripts',
        '.phpunit.cache',
        'phpunit.xml',
        'phpunit.xml.dist',
        'phpunit.modx.xml',
        'composer.json',
        'composer.lock',
        '.gitignore',
    ];
}
