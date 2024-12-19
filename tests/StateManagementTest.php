<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Generator;

use OpenAPITools\Configuration\Configuration;
use OpenAPITools\Configuration\Gathering;
use OpenAPITools\Configuration\Package;
use OpenAPITools\Generator\StateManagement;
use OpenAPITools\Utils\Namespace_;
use OpenAPITools\Utils\State;
use WyriHaximus\TestUtilities\TestCase;

use function md5;
use function md5_file;
use function time;

use const DIRECTORY_SEPARATOR;

final class StateManagementTest extends TestCase
{
    private const STATE_JSON_FILE = 'etc/state.json';

    /** @test */
    public function initialize(): void
    {
        $package = $this->getPackage();
        $state   = $this->getStageManagement($package)->load($package);

        self::assertFileDoesNotExist($this->getTmpDir() . self::STATE_JSON_FILE);
        self::assertSame('', $state->specHash);
        self::assertSame([], $state->additionalFiles->files());
        self::assertSame([], $state->generatedFiles->files());
    }

    /** @test */
    public function saveEmpty(): void
    {
        $package = $this->getPackage();
        $this->getStageManagement($package)->save($package, State::initialize());

        self::assertJsonStringEqualsJsonFile(
            $this->getTmpDir() . $package->destination->root . DIRECTORY_SEPARATOR . self::STATE_JSON_FILE,
            State::serialize(State::initialize()),
        );
    }

    /** @test */
    public function loadFull(): void
    {
        $package         = $this->getPackage();
        $specHash        = md5((string) time());
        $state           = State::initialize();
        $state->specHash = $specHash;
        $state->additionalFiles->upsert(__FILE__, md5_file(__FILE__)); /** @phpstan-ignore-line */
        $state->generatedFiles->upsert(__FILE__, md5_file(__FILE__)); /** @phpstan-ignore-line */
        $this->getStageManagement($package)->save($package, $state);

        $storedState = $this->getStageManagement($package)->load($package);

        self::assertJsonStringEqualsJsonString(
            State::serialize($state),
            State::serialize($storedState),
        );
    }

    /** @test */
    public function saveFull(): void
    {
        $package         = $this->getPackage();
        $state           = State::initialize();
        $state->specHash = md5((string) time());
        $state->additionalFiles->upsert(__FILE__, md5_file(__FILE__)); /** @phpstan-ignore-line */
        $state->generatedFiles->upsert(__FILE__, md5_file(__FILE__)); /** @phpstan-ignore-line */

        $this->getStageManagement($package)->save($package, $state);
        self::assertJsonStringEqualsJsonFile(
            $this->getTmpDir() . $package->destination->root . DIRECTORY_SEPARATOR . self::STATE_JSON_FILE,
            State::serialize($state),
        );
    }

    private function getStageManagement(Package $package): StateManagement
    {
        $configuration = new Configuration(
            new \OpenAPITools\Configuration\State(
                self::STATE_JSON_FILE,
            ),
            new Gathering(
                'api.github.com.yaml',
                null,
                new Gathering\Schemas(
                    true,
                    true,
                ),
            ),
            [$package],
        );

        return new StateManagement($this->getTmpDir(), $configuration);
    }

    private function getPackage(): Package
    {
        return new Package(
            new Package\Metadata(
                'GitHub',
                'Fully type safe generated GitHub REST API client',
                [],
            ),
            'api-clients',
            'github',
            'git@github.com:php-api-clients/github.git',
            'v0.2.x',
            null,
            new Package\Templates(
                __DIR__ . '/templates',
                [],
            ),
            new Package\Destination(
                'github',
                'src',
                'tests',
            ),
            new Namespace_(
                'ApiClients\Client\GitHub',
                'ApiClients\Tests\Client\GitHub',
            ),
            new Package\QA(
                phpcs: new Package\QA\Tool(true, null),
                phpstan: new Package\QA\Tool(
                    true,
                    'etc/phpstan-extension.neon',
                ),
                psalm: new Package\QA\Tool(false, null),
            ),
            new Package\State(
                [
                    'composer.json',
                    'composer.lock',
                ],
            ),
            [],
        );
    }
}
