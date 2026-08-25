<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Generator;

use OpenAPITools\Generator\Utils\FileStringyfier;
use OpenAPITools\Utils\File;
use PhpParser\BuilderFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

use const PHP_EOL;

final class FileStringyfierTest extends TestCase
{
    private const string STRING_FILE = '{"specHash":"","generatedFiles":{"files":[]},"additionalFiles":{"files":[]}}';

    private const string FIXTURE_CLASS = 'OpenAPITools\\Tests\\Generator\\Fixture\\Example';

    #[Test]
    public function stringyfiesStringContents(): void
    {
        $contents = new FileStringyfier(new Standard())->toString(
            new File('', 'some.json', self::STRING_FILE, File::DO_NOT_LOAD_ON_WRITE),
        );

        self::assertSame(self::STRING_FILE . PHP_EOL, $contents);
    }

    #[Test]
    public function stringyfiesAstContents(): void
    {
        $builderFactory = new BuilderFactory();
        $contents       = new FileStringyfier(new Standard())->toString(
            new File(
                '',
                self::FIXTURE_CLASS,
                $builderFactory->namespace('OpenAPITools\\Tests\\Generator\\Fixture')->addStmt(
                    $builderFactory->class('Example')->makeFinal()->getNode(),
                )->getNode(),
                File::DO_NOT_LOAD_ON_WRITE,
            ),
        );

        self::assertStringStartsWith("<?php\n\ndeclare (strict_types=1);\nnamespace OpenAPITools\\Tests\\Generator\\Fixture;\n", $contents);
        self::assertStringContainsString("final class Example\n{\n}\n", $contents);
    }
}
