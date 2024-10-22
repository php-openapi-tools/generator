<?php

declare(strict_types=1);

namespace OpenAPITools\Generator;

use EventSauce\ObjectHydrator\ObjectMapperUsingReflection;
use OpenAPITools\Configuration\Configuration;
use OpenAPITools\Configuration\Package;
use OpenAPITools\Utils\State;
use Safe\Exceptions\FilesystemException;

use function dirname;
use function file_exists;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\mkdir;

use const DIRECTORY_SEPARATOR;
use const JSON_PRETTY_PRINT;

final readonly class StateManagement
{
    public function __construct(
        private string $configurationLocation,
        private Configuration $configuration,
        private ObjectMapperUsingReflection $genericObjectMapper,
    ) {
    }

    public function load(Package $package): State
    {
        $fileName = $this->configurationLocation . $package->destination->root . DIRECTORY_SEPARATOR . $this->configuration->state->file;

        return $this->genericObjectMapper->hydrateObject(
            State::class,
            /** @phpstan-ignore-next-line */
            file_exists($fileName) ? json_decode(
                file_get_contents(
                    $fileName,
                ),
                true,
            ) : [
                'specHash' => '',
                'generatedFiles' => [
                    'files' => [],
                ],
                'additionalFiles' => [
                    'files' => [],
                ],
            ],
        );
    }

    public function save(Package $package, State $state): void
    {
        $fileName = $this->configurationLocation . $package->destination->root . DIRECTORY_SEPARATOR . $this->configuration->state->file;

        try {
            /** @phpstan-ignore-next-line */
            @mkdir(dirname($fileName), 0744, true);
        } catch (FilesystemException) {
            // @ignoreException
        }

        file_put_contents(
            $fileName,
            json_encode(
                $this->genericObjectMapper->serializeObject(
                    $state,
                ),
                JSON_PRETTY_PRINT,
            ),
        );
    }
}
