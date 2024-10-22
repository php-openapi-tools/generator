<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Utils;

use Jawira\CaseConverter\Convert;
use OpenAPITools\Utils\Utils;
use WyriHaximus\TestUtilities\TestCase;

use function array_keys;
use function strtolower;

final class UtilsTest extends TestCase
{
    /** @return iterable<array<string>> */
    public function cleanUpStringDataProvider(): iterable
    {
        foreach (Utils::CLEAN_UP_STRING_REPLACE as $badChar) {
            foreach ($this->prepareReservedKeywords() as $rawForbiddenKeyword => $forbiddenKeyword) {
                yield [
                    $badChar . $rawForbiddenKeyword,
                    '_' . $rawForbiddenKeyword,
                ];

                yield [
                    'Namespace/Sub/' . $badChar . $rawForbiddenKeyword,
                    'Namespace_Sub__' . $rawForbiddenKeyword,
                ];

                yield [
                    $badChar . $rawForbiddenKeyword . '/' . $badChar . $rawForbiddenKeyword,
                    '_' . $rawForbiddenKeyword . '__' . $rawForbiddenKeyword,
                ];
            }
        }
    }

    /**
     * @test
     * @dataProvider cleanUpStringDataProvider
     */
    public function cleanUpString(string $input, string $expectedOutput): void
    {
        self::assertSame($expectedOutput, Utils::cleanUpString($input));
    }

    /** @return iterable<array<string>> */
    public function classNameDataProvider(): iterable
    {
        foreach (array_keys(Utils::CLASS_NAME_REPLACE) as $badChar) {
            foreach ($this->prepareReservedKeywords() as $rawForbiddenKeyword => $forbiddenKeyword) {
                yield [
                    $rawForbiddenKeyword,
                    $forbiddenKeyword . (strtolower($forbiddenKeyword) === $rawForbiddenKeyword ? '_' : ''),
                ];

                yield [
                    $badChar . $rawForbiddenKeyword,
                    $forbiddenKeyword . (strtolower($forbiddenKeyword) === $rawForbiddenKeyword ? '_' : ''),
                ];

                yield [
                    'Namespace/Sub/' . $badChar . $rawForbiddenKeyword,
                    'Namespace\Sub\\' . $forbiddenKeyword . (strtolower($forbiddenKeyword) === $rawForbiddenKeyword ? '_' : ''),
                ];

                yield [
                    $badChar . $rawForbiddenKeyword . '/' . $badChar . $rawForbiddenKeyword,
                    $forbiddenKeyword . '\\' . $forbiddenKeyword . (strtolower($forbiddenKeyword) === $rawForbiddenKeyword ? '_' : ''),
                ];
            }
        }
    }

    /**
     * @test
     * @dataProvider classNameDataProvider
     */
    public function className(string $input, string $expectedOutput): void
    {
        self::assertSame($expectedOutput, Utils::className($input));
    }

    /** @return iterable<array<string>> */
    public function cleanUpNamespaceDataProvider(): iterable
    {
        yield ['Namespace/Sub/ClassName', '\Namespace\Sub\ClassName'];
        yield ['Namespace/\Sub/\ClassName', '\Namespace\Sub\ClassName'];
        yield ['Namespace\\Sub\\ClassName', '\Namespace\Sub\ClassName'];
        yield ['Namespace//Sub//ClassName', '\Namespace\Sub\ClassName'];
        yield ['Namespace/Sub/ClassName\\', '\Namespace\Sub\ClassName'];
        yield ['Namespace/\Sub/\ClassName\\', '\Namespace\Sub\ClassName'];
        yield ['Namespace\\Sub\\ClassName\\', '\Namespace\Sub\ClassName'];
        yield ['Namespace//Sub//ClassName\\', '\Namespace\Sub\ClassName'];
        yield ['Namespace//Sub//ClassName', '\Namespace\Sub\ClassName'];
        yield ['Namespace/\/\Sub/\/\ClassName', '\Namespace\Sub\ClassName'];
        yield ['Namespace\\\\Sub\\\\ClassName', '\Namespace\Sub\ClassName'];
        yield ['Namespace////Sub////ClassName', '\Namespace\Sub\ClassName'];
        yield ['Namespace//Sub//ClassName\\\\', '\Namespace\Sub\ClassName'];
        yield ['Namespace/\/\Sub/\/\ClassName\\\\', '\Namespace\Sub\ClassName'];
        yield ['Namespace\\\\Sub\\\\ClassName\\\\', '\Namespace\Sub\ClassName'];
        yield ['Namespace////Sub////ClassName\\\\', '\Namespace\Sub\ClassName'];
    }

    /**
     * @test
     * @dataProvider cleanUpNamespaceDataProvider
     */
    public function cleanUpNamespace(string $input, string $expectedOutput): void
    {
        self::assertSame($expectedOutput, Utils::cleanUpNamespace($input));
    }

    /** @test */
    public function fqcn(): void
    {
        self::assertSame('Namespace\ClassName', Utils::fqcn('Namespace/ClassName'));
    }

    /** @test */
    public function dirname(): void
    {
        self::assertSame('Namespace', Utils::dirname('Namespace\ClassName'));
    }

    /** @test */
    public function basename(): void
    {
        self::assertSame('ClassName', Utils::basename('Namespace\ClassName'));
    }

    /** @return iterable<array<string>> */
    public function fixKeywordDataProvider(): iterable
    {
        foreach ($this->prepareReservedKeywords() as $rawForbiddenKeyword => $forbiddenKeyword) {
            yield [$rawForbiddenKeyword, $rawForbiddenKeyword . '_'];
            yield ['Namespace/Sub/' . $rawForbiddenKeyword, 'Namespace\Sub\\' . $rawForbiddenKeyword . '_'];
            yield [$rawForbiddenKeyword . '/' . $rawForbiddenKeyword, $rawForbiddenKeyword . '\\' . $rawForbiddenKeyword . '_'];
        }
    }

    /**
     * @test
     * @dataProvider fixKeywordDataProvider
     */
    public function fixKeyword(string $input, string $expectedOutput): void
    {
        self::assertSame($expectedOutput, Utils::fixKeyword($input));
    }

    /** @return iterable<string, string> */
    private function prepareReservedKeywords(): iterable
    {
        foreach (Utils::RESERVED_KEYWORDS as $reservedKeyword) {
            yield $reservedKeyword => (new Convert($reservedKeyword))->toPascal();
        }
    }
}
