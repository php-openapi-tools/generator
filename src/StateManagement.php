<?php

declare(strict_types=1);

namespace OpenAPITools\Generator;

use OpenAPITools\Configuration\Configuration;
use OpenAPITools\Configuration\Package;
use OpenAPITools\Utils\State;
use RuntimeException;
use Safe\Exceptions\FilesystemException;

use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_string;
use function mkdir;
use function strlen;

use const DIRECTORY_SEPARATOR;

final readonly class StateManagement
{
    public function __construct(
        private string $configurationLocation,
        private Configuration $configuration,
    ) {
    }

    public function load(Package $package): State
    {
        $fileName = $this->configurationLocation . $package->destination->root . DIRECTORY_SEPARATOR . $this->configuration->state->file;

        if (file_exists($fileName)) {
            $json = file_get_contents($fileName);

            if (! is_string($json)) {
                throw new RuntimeException('Could not read state file: ' . $fileName);
            }

            return State::deserialize($json);
        }

        return State::initialize();
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

        $jsonState    = State::serialize($state);
        $bytesWritten = file_put_contents($fileName, $jsonState);
        if ($bytesWritten !== strlen($jsonState)) {
            throw new RuntimeException('An error occurred while writing state file, written ' . $bytesWritten . ' out of ' . strlen($jsonState) . ': ' . $fileName);
        }
    }
}
