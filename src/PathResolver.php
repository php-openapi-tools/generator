<?php

declare(strict_types=1);

namespace OpenAPITools\Generator;

use OpenAPITools\Configuration\Package;
use OpenAPITools\Utils\File;
use RuntimeException;

use function array_pop;
use function count;
use function end;
use function explode;
use function getcwd;
use function implode;
use function is_dir;
use function is_string;
use function ltrim;
use function preg_match;
use function realpath;
use function rtrim;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;

use const DIRECTORY_SEPARATOR;

final class PathResolver
{
    public static function configurationLocation(string $location): string
    {
        if ($location === '') {
            throw new RuntimeException('Configuration location must not be empty.');
        }

        $location = str_replace('\\', DIRECTORY_SEPARATOR, $location);

        if (! self::isAbsolute($location)) {
            $cwd      = getcwd();
            $location = rtrim(str_replace('\\', DIRECTORY_SEPARATOR, $cwd), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . ltrim($location, DIRECTORY_SEPARATOR);
        }

        if (! is_dir($location)) {
            throw new RuntimeException('Configuration location does not exist or is not a directory: ' . $location);
        }

        return rtrim(realpath($location), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public static function packageOutputRoot(string $configurationLocation, Package $package): string
    {
        return self::normalize($configurationLocation . $package->destination->root);
    }

    public static function generatedFile(string $configurationLocation, Package $package, File $file): string
    {
        if (trim($file->fqcn) === '') {
            throw new RuntimeException('Generated file FQCN must not be empty.');
        }

        $relative  = $file->pathPrefix . ($file->pathPrefix === '' ? '' : DIRECTORY_SEPARATOR);
        $relative .= trim(str_replace('\\', DIRECTORY_SEPARATOR, $file->fqcn), DIRECTORY_SEPARATOR);
        $relative .= is_string($file->contents) && ! str_contains($file->contents, '<?php') ? '' : '.php';

        return self::packageFile($configurationLocation, $package, $relative);
    }

    public static function packageFile(string $configurationLocation, Package $package, string $relativePath): string
    {
        $path = self::normalize($configurationLocation . $package->destination->root . DIRECTORY_SEPARATOR . $relativePath);

        if (! self::isWithin(self::packageOutputRoot($configurationLocation, $package), $path)) {
            throw new RuntimeException('File path escapes package output directory: ' . $path);
        }

        return $path;
    }

    /**
     * Resolve a path lexically, without touching the filesystem.
     *
     * Two spellings of the same path compare unequal as strings, and the stale
     * file sweep compares them as strings. Left alone, a state written with one
     * spelling makes the sweep delete the very files the run just wrote.
     */
    public static function normalize(string $path): string
    {
        $path   = str_replace('\\', DIRECTORY_SEPARATOR, $path);
        $prefix = '';

        if (preg_match('#^([A-Za-z]:)(.*)$#', $path, $matches) === 1) {
            $prefix = $matches[1] . DIRECTORY_SEPARATOR;
            $path   = ltrim($matches[2], DIRECTORY_SEPARATOR);
        } elseif (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $prefix = DIRECTORY_SEPARATOR;
            $path   = ltrim($path, DIRECTORY_SEPARATOR);
        }

        $segments = [];

        foreach (explode(DIRECTORY_SEPARATOR, $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..' && count($segments) > 0 && end($segments) !== '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return $prefix . implode(DIRECTORY_SEPARATOR, $segments);
    }

    public static function isWithin(string $root, string $path): bool
    {
        $root = rtrim(str_replace('\\', DIRECTORY_SEPARATOR, self::normalize($root)), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $path = str_replace('\\', DIRECTORY_SEPARATOR, self::normalize($path));

        if (DIRECTORY_SEPARATOR === '\\') {
            $root = strtolower($root);
            $path = strtolower($path);
        }

        return str_starts_with($path, $root);
    }

    private static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }
}
