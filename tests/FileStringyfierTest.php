<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Generator;

use OpenAPITools\Generator\FileStringyfier;
use OpenAPITools\Utils\File;
use PhpParser\PrettyPrinter\Standard;
use WyriHaximus\TestUtilities\TestCase;

use const PHP_EOL;

final class FileStringyfierTest extends TestCase
{
    private const STRING_FILE = '{"specHash":"","generatedFiles":{"files":[]},"additionalFiles":{"files":[]}}';

    /** @test */
    public function string(): void
    {
        $file = new File('', 'some.json', self::STRING_FILE, File::DO_NOT_LOAD_ON_WRITE);

        self::assertSame(self::STRING_FILE . PHP_EOL, (new FileStringyfier(new Standard()))->toString($file));
    }
}
