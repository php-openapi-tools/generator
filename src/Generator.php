<?php

declare(strict_types=1);

namespace OpenAPITools\Generator;

use cebe\openapi\Reader;
use EventSauce\ObjectHydrator\ObjectMapperUsingReflection;
use OpenAPITools\Configuration\Configuration;
use OpenAPITools\Configuration\Package;
use OpenAPITools\Gatherer\Gatherer;
use OpenAPITools\Utils\State\File;
use PhpParser\PrettyPrinter\Standard;
use Safe\Exceptions\FilesystemException;

use function array_map;
use function dirname;
use function file_exists;
use function hash;
use function is_string;
use function md5;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\mkdir;
use function Safe\realpath;
use function Safe\unlink;
use function str_replace;
use function strpos;
use function sys_get_temp_dir;
use function trim;
use function uniqid;

use const DIRECTORY_SEPARATOR;

final class Generator
{
    public static function generate(Configuration $configuration, string $configurationLocation): void
    {
        $stateManagement = new StateManagement($configurationLocation, $configuration, new ObjectMapperUsingReflection());

        $specLocation = $configuration->gathering->spec;
        if (strpos($specLocation, '://') === false) {
            $specLocation = realpath($configurationLocation . $specLocation);
        }

        $specYaml     = file_get_contents($specLocation);
        $specYamlHash = self::hash($specYaml);
        $tmpFile      = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid() . '.yaml';
        try {
            file_put_contents($tmpFile, $specYaml);
            $spec = Reader::readFromYamlFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }

        $representation  = Gatherer::gather($spec, $configuration->gathering);
        $fileStringyfier = new FileStringyfier(new Standard());

        foreach ($configuration->packages as $package) {
            if (! ($package instanceof Package)) {
                continue;
            }

            $namespacedRepresentation = $representation->namespace($package->namespace);
            $state                    = $stateManagement->load($package);
            $state->specHash          = $specYamlHash;
            $existingFiles            = array_map(
                static fn (File $file): string => $file->name,
                $state->generatedFiles->files(),
            );

            foreach ($package->generators as $generator) {
                foreach ($generator->generate($package, $namespacedRepresentation) as $file) {
                    $fileName         = $configurationLocation . $package->destination->root . DIRECTORY_SEPARATOR;
                    $fileName        .= $file->pathPrefix . ($file->pathPrefix === '' ? '' : DIRECTORY_SEPARATOR);
                    $fileName        .= trim(str_replace('\\', DIRECTORY_SEPARATOR, $file->fqcn), DIRECTORY_SEPARATOR);
                    $fileName        .= (is_string($file->contents) && strpos($file->contents, '<?php') === false ? '' : '.php');
                    $fileContents     = $fileStringyfier->toString($file);
                    $fileContentsHash = md5($fileContents);
                    try {
                        /** @phpstan-ignore-next-line */
                        @mkdir(dirname($fileName), 0744, true);
                    } catch (FilesystemException) {
                        // @ignoreException
                    }

                    file_put_contents($fileName, $fileContents);
                    $state->generatedFiles->upsert($fileName, $fileContentsHash);

                    if ($file->loadOnWrite === \OpenAPITools\Utils\File::DO_NOT_LOAD_ON_WRITE) {
                        continue;
                    }

                    include_once $fileName;
                }
            }

            foreach ($existingFiles as $existingFile) {
                $state->generatedFiles->remove($existingFile);
                unlink($existingFile);
            }

            foreach ($state->additionalFiles->files() as $file) {
                $state->additionalFiles->remove($file->name);
            }

            foreach ($configuration->state->additionalFiles ?? [] as $additionalFile) {
                $state->additionalFiles->upsert(
                    $additionalFile,
                    file_exists($configurationLocation . $package->destination->root . DIRECTORY_SEPARATOR . $additionalFile) ? self::hash(file_get_contents($configurationLocation . $package->destination->root . DIRECTORY_SEPARATOR . $additionalFile)) : '',
                );
            }

            $stateManagement->save($package, $state);
        }
    }

    private static function hash(string $contents): string
    {
        return hash('sha3-512', $contents);
    }
}
