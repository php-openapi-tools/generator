<?php

declare(strict_types=1);

namespace OpenAPITools\Generator;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use OpenAPITools\Configuration\Configuration;
use OpenAPITools\Configuration\Package;
use OpenAPITools\Gatherer\Gatherer;
use OpenAPITools\Representation\Representation;
use OpenAPITools\Utils\File;
use OpenAPITools\Utils\State;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;

use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function is_dir;
use function md5;
use function mkdir;
use function realpath;
use function str_contains;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function usleep;

use const DIRECTORY_SEPARATOR;

final class Generator
{
    public static function generate(Configuration $configuration, string $configurationLocation): void
    {
        $configurationLocation = PathResolver::configurationLocation($configurationLocation);
        $stateManagement       = new StateManagement($configurationLocation, $configuration);
        $specYaml              = self::readSpec($configuration, $configurationLocation);
        $specYamlHash          = self::hash($specYaml);
        $representation        = Gatherer::gather(self::parseSpec($specYaml), $configuration->gathering);
        $fileStringyfier       = new FileStringyfier(new Standard());

        foreach ($configuration->packages as $package) {
            if (! ($package instanceof Package)) {
                continue;
            }

            self::generatePackage(
                $configurationLocation,
                $stateManagement,
                $package,
                $specYamlHash,
                $representation,
                $fileStringyfier,
            );
        }
    }

    private static function generatePackage(
        string $configurationLocation,
        StateManagement $stateManagement,
        Package $package,
        string $specYamlHash,
        Representation $representation,
        FileStringyfier $fileStringyfier,
    ): void {
        $outputRoot  = PathResolver::packageOutputRoot($configurationLocation, $package);
        $loadedState = $stateManagement->load($package);
        $state       = new State($specYamlHash, $loadedState->generatedFiles, $loadedState->additionalFiles);

        $existingFiles = [];
        foreach ($state->generatedFiles->files() as $existingFile) {
            $existingFiles[PathResolver::normalize($existingFile->name)] = $existingFile->name;
        }

        $namespacedRepresentation = $representation->namespace($package->namespace);

        foreach ($package->generators as $generator) {
            foreach ($generator->generate($package, $namespacedRepresentation) as $file) {
                $fileName = PathResolver::generatedFile($configurationLocation, $package, $file);
                self::writeGeneratedFile($fileName, $fileStringyfier->toString($file), $state);
                unset($existingFiles[$fileName]);

                if ($file->loadOnWrite === File::DO_NOT_LOAD_ON_WRITE) {
                    continue;
                }

                include_once $fileName;
            }
        }

        foreach ($existingFiles as $normalizedName => $recordedName) {
            $state->generatedFiles->remove($recordedName);

            if (! file_exists($normalizedName) || ! PathResolver::isWithin($outputRoot, $normalizedName)) {
                continue;
            }

            unlink($normalizedName);
        }

        foreach ($state->additionalFiles->files() as $file) {
            $state->additionalFiles->remove($file->name);
        }

        foreach ($package->state->additionalFiles ?? [] as $additionalFile) {
            $path = PathResolver::packageFile($configurationLocation, $package, $additionalFile);
            $state->additionalFiles->upsert(
                $additionalFile,
                file_exists($path) ? self::hash((string) file_get_contents($path)) : '',
            );
        }

        $stateManagement->save($package, $state);
    }

    private static function writeGeneratedFile(string $fileName, string $contents, State $state): void
    {
        $hash = md5($contents);

        if ($state->generatedFiles->has($fileName) && $state->generatedFiles->get($fileName)->hash === $hash) {
            return;
        }

        $directory = dirname($fileName);
        if (! is_dir($directory)) {
            mkdir($directory, 0744, true);
        }

        if (file_put_contents($fileName, $contents) === false) {
            throw new RuntimeException('Could not write generated file: ' . $fileName);
        }

        $state->generatedFiles->upsert($fileName, $hash);

        // @codeCoverageIgnoreStart
        while (! file_exists($fileName) || $hash !== md5((string) file_get_contents($fileName))) {
            usleep(100);
        }
        // @codeCoverageIgnoreEnd
    }

    private static function readSpec(Configuration $configuration, string $configurationLocation): string
    {
        $specLocation = $configuration->gathering->spec;
        if (! str_contains($specLocation, '://')) {
            $specLocation = realpath($configurationLocation . $specLocation);
        }

        $specYaml = file_get_contents($specLocation);
        if ($specYaml === false) {
            throw new RuntimeException('Could not read spec: ' . $specLocation);
        }

        return $specYaml;
    }

    private static function parseSpec(string $specYaml): OpenApi
    {
        $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid() . '.yaml';
        try {
            if (file_put_contents($tmpFile, $specYaml) === false) {
                throw new RuntimeException('Could not write temporary spec file: ' . $tmpFile);
            }

            return Reader::readFromYamlFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    private static function hash(string $contents): string
    {
        return hash('sha3-512', $contents);
    }
}
