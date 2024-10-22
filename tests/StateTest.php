<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Utils;

use OpenAPITools\Utils\State;
use WyriHaximus\TestUtilities\TestCase;

use function basename;
use function current;

final class StateTest extends TestCase
{
    private const EMPTY_JSON    = '{"specHash":"","generatedFiles":{"files":[]},"additionalFiles":{"files":[]}}';
    private const ONE_FILE_JSON = '{"specHash":"","generatedFiles":{"files":[]},"additionalFiles":{"files":[{"name":"StateTest.php","hash":"bef729dcdda9be9df5af3a1f3f50873e"}]}}';

    /** @test */
    public function initialize(): void
    {
        $state = State::initialize();

        self::assertSame('', $state->specHash);
        self::assertCount(0, $state->generatedFiles->files());
        self::assertCount(0, $state->additionalFiles->files());

        self::assertSame(self::EMPTY_JSON, State::serialize($state));
    }

    /** @test */
    public function deserializeEmpty(): void
    {
        $state = State::deserialize(self::EMPTY_JSON);

        self::assertSame('', $state->specHash);
        self::assertCount(0, $state->generatedFiles->files());
        self::assertCount(0, $state->additionalFiles->files());

        self::assertSame(self::EMPTY_JSON, State::serialize($state));
    }

    /** @test */
    public function deserializeOneFile(): void
    {
        $state = State::deserialize(self::ONE_FILE_JSON);

        self::assertSame('', $state->specHash);
        self::assertCount(0, $state->generatedFiles->files());
        self::assertCount(1, $state->additionalFiles->files());

        self::assertSame(self::ONE_FILE_JSON, State::serialize($state));
    }

    /** @return iterable<array<State>> */
    public function emptyStateDataProvider(): iterable
    {
        yield [State::initialize()];
        yield [State::deserialize(self::EMPTY_JSON)];
    }

    /**
     * @test
     * @dataProvider emptyStateDataProvider
     */
    public function operations(State $state): void
    {
        $file  = new State\File(
            basename(__FILE__),
            'bef729dcdda9be9df5af3a1f3f50873e',
        );
        $state = State::deserialize(self::EMPTY_JSON);

        self::assertCount(0, $state->generatedFiles->files());
        self::assertCount(0, $state->additionalFiles->files());
        self::assertSame(self::EMPTY_JSON, State::serialize($state));
        self::assertFalse($state->additionalFiles->has(basename(__FILE__)));

        $state->additionalFiles->upsert($file->name, $file->hash);

        self::assertCount(0, $state->generatedFiles->files());
        self::assertCount(1, $state->additionalFiles->files());
        self::assertSame(
            '{"specHash":"","generatedFiles":{"files":[]},"additionalFiles":{"files":[{"name":"' . $file->name . '","hash":"' . $file->hash . '"}]}}',
            State::serialize($state),
        );
        self::assertTrue($state->additionalFiles->has(basename(__FILE__)));
        self::assertInstanceOf(State\File::class, current($state->additionalFiles->files()));
        self::assertSame($file->name, current($state->additionalFiles->files())->name);
        self::assertSame($file->hash, current($state->additionalFiles->files())->hash);
        self::assertSame($file->name, $state->additionalFiles->get(basename(__FILE__))->name);
        self::assertSame($file->hash, $state->additionalFiles->get(basename(__FILE__))->hash);

        $state->additionalFiles->remove($file->name);

        self::assertCount(0, $state->generatedFiles->files());
        self::assertCount(0, $state->additionalFiles->files());
        self::assertSame(self::EMPTY_JSON, State::serialize($state));
        self::assertFalse($state->additionalFiles->has(basename(__FILE__)));
    }
}
