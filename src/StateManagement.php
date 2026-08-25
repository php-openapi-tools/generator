<?php

declare(strict_types=1);

namespace OpenAPITools\Generator;

use OpenAPITools\Configuration\Configuration;
use OpenAPITools\Configuration\Package;
use OpenAPITools\Utils\State;
use RuntimeException;

use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_string;
use function mkdir;
use function strlen;

/**
 * Loads and persists generation state for a package.
 *
 * @api
 */
final readonly class StateManagement
{
    public function __construct(
        private string $configurationLocation,
        private Configuration $configuration,
    ) {
    }

    /** Load persisted state for the given package, or return an empty initial state when none exists. */
    public function load(Package $package): State
    {
        $fileName = PathResolver::packageFile($this->configurationLocation, $package, $this->configuration->state->file);

        if (! file_exists($fileName)) {
            return State::initialize();
        }

        $json = file_get_contents($fileName);
        if (! is_string($json)) {
            throw new RuntimeException('Could not read state file: ' . $fileName);
        }

        return State::deserialize($json);
    }

    /** Persist generation state for the given package. */
    public function save(Package $package, State $state): void
    {
        $fileName  = PathResolver::packageFile($this->configurationLocation, $package, $this->configuration->state->file);
        $directory = dirname($fileName);

        if (! is_dir($directory)) {
            mkdir($directory, 0744, true);
        }

        $jsonState    = State::serialize($state);
        $bytesWritten = file_put_contents($fileName, $jsonState);
        if ($bytesWritten !== strlen($jsonState)) {
            throw new RuntimeException('An error occurred while writing state file, written ' . $bytesWritten . ' out of ' . strlen($jsonState) . ': ' . $fileName);
        }
    }
}
