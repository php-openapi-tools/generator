<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Generator;

use OpenAPITools\Configuration\Configuration;
use OpenAPITools\Configuration\Gathering;
use OpenAPITools\Configuration\Package;
use OpenAPITools\Generator\StateManagement;
use OpenAPITools\Utils\Namespace_;
use OpenAPITools\Utils\State;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

use function md5;
use function md5_file;
use function time;

use const DIRECTORY_SEPARATOR;

final class StateManagementTest extends TestCase
{
    private const string STATE_JSON_FILE = 'etc/state.json';

    #[Test]
    public function initialize(): void
    {
        $package = $this->package();
        $state   = $this->stateManagement($package)->load($package);

        self::assertFileDoesNotExist($this->getTmpDir() . self::STATE_JSON_FILE);
        self::assertSame('', $state->specHash);
        self::assertSame([], $state->additionalFiles->files());
        self::assertSame([], $state->generatedFiles->files());
    }

    #[Test]
    #[DataProvider('persistedStateProvider')]
    public function saveAndLoad(State $state): void
    {
        $package         = $this->package();
        $stateManagement = $this->stateManagement($package);
        $statePath       = $this->getTmpDir() . $package->destination->root . DIRECTORY_SEPARATOR . self::STATE_JSON_FILE;

        $stateManagement->save($package, $state);

        self::assertJsonStringEqualsJsonFile($statePath, State::serialize($state));
        self::assertJsonStringEqualsJsonString(
            State::serialize($state),
            State::serialize($stateManagement->load($package)),
        );
    }

    /** @return iterable<string, array{State}> */
    public static function persistedStateProvider(): iterable
    {
        yield 'empty state' => [State::initialize()];

        yield 'full state' => [self::stateWithFiles(md5((string) time()))];
    }

    private static function stateWithFiles(string $specHash): State
    {
        $fileHash = md5_file(__FILE__);
        self::assertIsString($fileHash);

        $state = State::initialize();
        $state->generatedFiles->upsert(__FILE__, $fileHash);
        $state->additionalFiles->upsert(__FILE__, $fileHash);

        return new State($specHash, $state->generatedFiles, $state->additionalFiles);
    }

    private function stateManagement(Package $package): StateManagement
    {
        return new StateManagement($this->getTmpDir(), new Configuration(
            new \OpenAPITools\Configuration\State(self::STATE_JSON_FILE),
            new Gathering('api.github.com.yaml', null, new Gathering\Schemas(true, true)),
            [$package],
        ));
    }

    private function package(): Package
    {
        return new Package(
            new Package\Metadata('GitHub', 'Fully type safe generated GitHub REST API client', []),
            'api-clients',
            'github',
            'git@github.com:php-api-clients/github.git',
            'v0.2.x',
            null,
            new Package\Templates(__DIR__ . '/templates', []),
            new Package\Destination('github', 'src', 'tests'),
            new Namespace_('ApiClients\Client\GitHub', 'ApiClients\Tests\Client\GitHub'),
            new Package\QA(
                phpcs: new Package\QA\Tool(true, null),
                phpstan: new Package\QA\Tool(true, 'etc/phpstan-extension.neon'),
                psalm: new Package\QA\Tool(false, null),
            ),
            new Package\State(['composer.json', 'composer.lock']),
            [],
        );
    }
}
