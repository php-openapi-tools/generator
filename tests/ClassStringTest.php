<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Utils;

use OpenAPITools\Utils\ClassString;
use OpenAPITools\Utils\Namespace_;
use WyriHaximus\TestUtilities\TestCase;

final class ClassStringTest extends TestCase
{
    /** @return iterable<array<Namespace_|string>> */
    public function factoryDataProvider(): iterable
    {
        yield [
            new Namespace_(
                'OpenAPITools\Utils',
                'ApiClients\Tests\Tools\Utils',
            ),
            'ClassString',
            'ClassString',
            'ClassString',
            'OpenAPITools\Utils',
            'ApiClients\Tests\Tools\Utils',
            'OpenAPITools\Utils',
            'ApiClients\Tests\Tools\Utils',
            '\OpenAPITools\Utils\ClassString',
            '\ApiClients\Tests\Tools\Utils\ClassString',
        ];

        yield [
            new Namespace_(
                'OpenAPITools',
                'ApiClients\Tests\Tools',
            ),
            'Utils\ClassString',
            'Utils\ClassString',
            'ClassString',
            'OpenAPITools',
            'ApiClients\Tests\Tools',
            'OpenAPITools\Utils',
            'ApiClients\Tests\Tools\Utils',
            '\OpenAPITools\Utils\ClassString',
            '\ApiClients\Tests\Tools\Utils\ClassString',
        ];

        yield [
            new Namespace_(
                '\OpenAPITools\\',
                '\ApiClients\Tests\Tools\\',
            ),
            '\Utils\ClassString\\',
            'Utils\ClassString',
            'ClassString',
            'OpenAPITools',
            'ApiClients\Tests\Tools',
            'OpenAPITools\Utils',
            'ApiClients\Tests\Tools\Utils',
            '\OpenAPITools\Utils\ClassString',
            '\ApiClients\Tests\Tools\Utils\ClassString',
        ];
    }

    /**
     * @test
     * @dataProvider factoryDataProvider
     */
    public function factory(Namespace_ $namespace, string $rawRelative, string $relative, string $className, string $baseNamespaceSource, string $baseNamespaceTest, string $namespaceSource, string $namespaceTest, string $fullyQualifiedSource, string $fullyQualifiedTest): void
    {
        $classString = ClassString::factory($namespace, $rawRelative);

        self::assertSame($baseNamespaceSource, $classString->baseNamespace->source);
        self::assertSame($baseNamespaceTest, $classString->baseNamespace->test);
        self::assertSame($relative, $classString->relative);
        self::assertSame($className, $classString->className);
        self::assertSame($namespaceSource, $classString->namespace->source);
        self::assertSame($namespaceTest, $classString->namespace->test);
        self::assertSame($fullyQualifiedSource, $classString->fullyQualified->source);
        self::assertSame($fullyQualifiedTest, $classString->fullyQualified->test);
    }
}
