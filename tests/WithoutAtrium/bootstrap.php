<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

/*
 * The WithoutAtrium suite's bootstrap: Composer's autoloader without
 * Atrium. It removes the RefactorCircus\Atrium\ PSR-4 prefix (and any
 * classmap entries under it) from every registered Composer loader, so any
 * Impex code reaching for an Atrium class finds none, as in an application
 * that never installed it.
 *
 * Pest loads tests/WithoutAtrium/Pest.php before PHPUnit's bootstrap, so that
 * file requires this one first; it runs once. It refuses to run once an
 * Atrium class is loaded, since removing the prefix then proves nothing.
 *
 * It cannot unload Composer `files` autoloads or classes loaded another way;
 * the without-atrium CI job, which uninstalls Atrium, is the authoritative
 * proof.
 */

require_once dirname(__DIR__, 2).'/vendor/autoload.php';

(static function (string $prefix): void {
    $loaded = array_filter(
        [...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()],
        fn (string $class): bool => str_starts_with($class, $prefix),
    );

    if ($loaded !== []) {
        throw new RuntimeException('The WithoutAtrium bootstrap ran after Atrium classes were loaded: '.implode(', ', $loaded).'.');
    }

    foreach (ClassLoader::getRegisteredLoaders() as $loader) {
        (function () use ($prefix): void {
            unset($this->prefixDirsPsr4[$prefix], $this->prefixLengthsPsr4[$prefix[0]][$prefix]);

            foreach (array_keys($this->classMap) as $class) {
                if (str_starts_with($class, $prefix)) {
                    unset($this->classMap[$class]);
                }
            }
        })->call($loader);
    }
})('RefactorCircus\\Atrium\\');
