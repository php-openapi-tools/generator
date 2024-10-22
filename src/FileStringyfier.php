<?php

declare(strict_types=1);

namespace OpenAPITools\Generator;

use OpenAPITools\Utils\File;
use PhpParser\Node;
use PhpParser\PrettyPrinterAbstract;

use function is_string;

use const PHP_EOL;

final readonly class FileStringyfier
{
    public function __construct(
        private PrettyPrinterAbstract $printer,
    ) {
    }

    public function toString(File $file): string
    {
        if (is_string($file->contents)) {
            return $file->contents . PHP_EOL;
        }

        return $this->printer->prettyPrintFile([
            new Node\Stmt\Declare_([
                new Node\Stmt\DeclareDeclare('strict_types', new Node\Scalar\LNumber(1)),
            ]),
            $file->contents,
        ]) . PHP_EOL;
    }
}
