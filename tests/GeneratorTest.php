<?php

declare(strict_types=1);

namespace OpenAPITools\Tests\Generator;

use OpenAPITools\Configuration\Configuration;
use OpenAPITools\Configuration\Gathering;
use OpenAPITools\Configuration\Package;
use OpenAPITools\Contract\FileGenerator;
use OpenAPITools\Generator\Generator;
use OpenAPITools\Generator\StateManagement;
use OpenAPITools\Representation\Namespaced\Representation;
use OpenAPITools\Tests\Generator\Fixtures\NonPackage;
use OpenAPITools\Utils\File;
use OpenAPITools\Utils\Namespace_;
use OpenAPITools\Utils\State;
use PhpParser\BuilderFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WyriHaximus\TestUtilities\TestCase;

use function class_exists;
use function copy;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function hash;
use function md5;
use function mkdir;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function str_replace;

use const PHP_EOL;

final class GeneratorTest extends TestCase
{
    private const string STATE_FILE = 'etc/state.json';

    private const string FIXTURE_NAMESPACE = 'OpenAPITools\\Tests\\Generator\\Fixture';

    private const string LOADABLE_CLASS = self::FIXTURE_NAMESPACE . '\\LoadableExample';

    #[Test]
    #[DataProvider('generatedFileProvider')]
    public function generateWritesFiles(File $file, string $relativePath, bool $checkContent, string $expectedContent, bool $expectLoaded): void
    {
        $location = $this->location();
        $this->generate($location, [$file]);

        $path = $location . 'generated/' . $relativePath;
        self::assertFileExists($path);

        if ($checkContent) {
            self::assertSame($expectedContent, (string) file_get_contents($path));
        }

        if (! $expectLoaded) {
            return;
        }

        self::assertTrue(class_exists(self::LOADABLE_CLASS, false));
    }

    /** @return iterable<string, array{File, string, bool, string, bool}> */
    public static function generatedFileProvider(): iterable
    {
        yield 'php with load' => [
            new File(
                'src',
                self::LOADABLE_CLASS,
                '<?php declare(strict_types=1); namespace OpenAPITools\\Tests\\Generator\\Fixture; final class LoadableExample {}',
                File::DO_LOAD_ON_WRITE,
            ),
            'src/OpenAPITools/Tests/Generator/Fixture/LoadableExample.php',
            false,
            '',
            true,
        ];

        yield 'json without php extension' => [
            new File('', 'composer.json', '{"name":"example/test"}', File::DO_NOT_LOAD_ON_WRITE),
            'composer.json',
            true,
            '{"name":"example/test"}' . PHP_EOL,
            false,
        ];

        $builderFactory = new BuilderFactory();

        yield 'ast contents' => [
            new File(
                'src',
                self::FIXTURE_NAMESPACE . '\\FromAst',
                $builderFactory->namespace('OpenAPITools\\Tests\\Generator\\Fixture')->addStmt(
                    $builderFactory->class('FromAst')->makeFinal()->getNode(),
                )->getNode(),
                File::DO_NOT_LOAD_ON_WRITE,
            ),
            'src/OpenAPITools/Tests/Generator/Fixture/FromAst.php',
            false,
            '',
            false,
        ];
    }

    #[Test]
    #[DataProvider('additionalFileProvider')]
    public function generateTracksAdditionalFiles(string $fileName, bool $createFile, string $contents, string $expectedHash): void
    {
        $location = $this->location();

        if ($createFile) {
            mkdir($location . 'generated', 0777, true);
            file_put_contents($location . 'generated/' . $fileName, $contents);
        }

        $this->generate($location, [], [$fileName]);

        $state = State::deserialize((string) file_get_contents($location . 'generated/' . self::STATE_FILE));
        self::assertSame($expectedHash, $state->additionalFiles->get($fileName)->hash);
    }

    /** @return iterable<string, array{string, bool, string, string}> */
    public static function additionalFileProvider(): iterable
    {
        yield 'existing file' => [
            'composer.json',
            true,
            '{"name":"tracked/example"}',
            hash('sha3-512', '{"name":"tracked/example"}'),
        ];

        yield 'missing file' => [
            'composer.lock',
            false,
            '',
            '',
        ];
    }

    #[Test]
    public function generateReadsFileUrlSpec(): void
    {
        $location = $this->location();
        $specPath = $location . 'spec.yaml';
        $this->generateWithConfiguration($location, new Configuration(
            new \OpenAPITools\Configuration\State(self::STATE_FILE),
            new Gathering($this->fileUrl($specPath), null, new Gathering\Schemas(true, true)),
            [$this->package([])],
        ));

        $state = State::deserialize((string) file_get_contents($location . 'generated/' . self::STATE_FILE));
        self::assertSame(
            hash('sha3-512', (string) file_get_contents($specPath)),
            $state->specHash,
        );
    }

    #[Test]
    public function generateSkipsNonPackageEntries(): void
    {
        $location = $this->location();
        Generator::generate(new Configuration(
            new \OpenAPITools\Configuration\State(self::STATE_FILE),
            new Gathering('spec.yaml', null, new Gathering\Schemas(true, true)),
            [
                $this->package([new File('', 'only.json', '{}', File::DO_NOT_LOAD_ON_WRITE)]),
                new NonPackage(),
            ],
        ), $location);

        self::assertFileExists($location . 'generated/only.json');
    }

    #[Test]
    public function generateSkipsUnchangedFiles(): void
    {
        $location = $this->location();
        $files    = [new File('', 'README.md', '# Example', File::DO_NOT_LOAD_ON_WRITE)];

        $this->generate($location, $files);
        $mtime = filemtime($location . 'generated/README.md');
        self::assertIsInt($mtime);

        $this->generate($location, $files);
        self::assertSame($mtime, filemtime($location . 'generated/README.md'));
    }

    #[Test]
    public function generateRemovesStaleGeneratedFiles(): void
    {
        $location = $this->location();
        $files    = [
            new File(
                'src',
                self::FIXTURE_NAMESPACE . '\\Current',
                '<?php declare(strict_types=1); namespace OpenAPITools\\Tests\\Generator\\Fixture; final class Current {}',
                File::DO_NOT_LOAD_ON_WRITE,
            ),
        ];

        $this->generate($location, $files);

        $keepFile  = $location . 'generated/src/OpenAPITools/Tests/Generator/Fixture/Current.php';
        $staleFile = $location . 'generated/src/OpenAPITools/Tests/Generator/Fixture/Stale.php';
        copy($keepFile, $staleFile);

        $this->saveState($location, static function (State $state) use ($keepFile, $staleFile, $location): void {
            $state->generatedFiles->upsert($keepFile, md5((string) file_get_contents($keepFile)));
            $state->generatedFiles->upsert(
                $location . 'generated/src/./OpenAPITools/Tests/Generator/Fixture/../Fixture/Stale.php',
                md5((string) file_get_contents($staleFile)),
            );
        });

        $this->generate($location, $files);

        self::assertFileDoesNotExist($staleFile);
        self::assertFileExists($keepFile);
    }

    #[Test]
    public function generateSkipsMissingStaleFiles(): void
    {
        $location = $this->location();

        $this->saveState($location, static function (State $state) use ($location): void {
            $state->generatedFiles->upsert($location . 'generated/missing-stale.json', md5('stale'));
        });

        $this->generate($location, [new File('', 'keep.json', '{}', File::DO_NOT_LOAD_ON_WRITE)]);

        self::assertFileExists($location . 'generated/keep.json');
        self::assertFileDoesNotExist($location . 'generated/missing-stale.json');
    }

    #[Test]
    public function generateClearsPersistedAdditionalFiles(): void
    {
        $location = $this->location();

        $this->saveState($location, static function (State $state): void {
            $state->additionalFiles->upsert('composer.json', 'previous-hash');
        });

        $this->generate($location, []);

        $state = new StateManagement($location, $this->configuration([]))->load($this->package([]));
        self::assertFalse($state->additionalFiles->has('composer.json'));
    }

    #[Test]
    public function generateThrowsWhenFqcnIsEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Generated file FQCN must not be empty.');

        $this->generate($this->location(), [new File('', '', '{}', File::DO_NOT_LOAD_ON_WRITE)]);
    }

    #[Test]
    public function generateThrowsWhenFilePathEscapesOutputDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('File path escapes package output directory:');

        $this->generate($this->location(), [
            new File('..', 'outside', '{}', File::DO_NOT_LOAD_ON_WRITE),
        ]);
    }

    #[Test]
    public function generateThrowsWhenConfigurationLocationDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Configuration location does not exist or is not a directory:');

        Generator::generate($this->configuration([]), $this->location() . 'missing/');
    }

    #[Test]
    public function generateThrowsWhenSpecIsUnreadable(): void
    {
        set_error_handler(static fn (): bool => true);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageIsOrContains('Could not read spec:');

            Generator::generate(
                new Configuration(
                    new \OpenAPITools\Configuration\State(self::STATE_FILE),
                    new Gathering('file:///does-not-exist/spec.yaml', null, new Gathering\Schemas(true, true)),
                    [$this->package([])],
                ),
                $this->location(),
            );
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param list<File>   $files
     * @param list<string> $additionalFiles
     */
    private function generate(string $location, array $files, array $additionalFiles = []): void
    {
        Generator::generate($this->configuration($files, $additionalFiles), $location);
    }

    private function generateWithConfiguration(string $location, Configuration $configuration): void
    {
        Generator::generate($configuration, $location);
    }

    /**
     * @param list<File>   $files
     * @param list<string> $additionalFiles
     */
    private function configuration(array $files, array $additionalFiles = []): Configuration
    {
        return new Configuration(
            new \OpenAPITools\Configuration\State(self::STATE_FILE),
            new Gathering('spec.yaml', null, new Gathering\Schemas(true, true)),
            [$this->package($files, $additionalFiles)],
        );
    }

    /**
     * @param list<File>   $files
     * @param list<string> $additionalFiles
     */
    private function package(array $files, array $additionalFiles = []): Package
    {
        return new Package(
            new Package\Metadata('Example', 'Generated example package', []),
            'api-clients',
            'example',
            null,
            null,
            null,
            new Package\Templates(__DIR__ . '/templates', []),
            new Package\Destination('generated', 'src', 'tests'),
            new Namespace_(self::FIXTURE_NAMESPACE, self::FIXTURE_NAMESPACE . '\\Tests'),
            new Package\QA(
                phpcs: new Package\QA\Tool(true, null),
                phpstan: new Package\QA\Tool(true, null),
                psalm: new Package\QA\Tool(false, null),
            ),
            new Package\State($additionalFiles),
            [$this->generator($files)],
        );
    }

    /** @param list<File> $files */
    private function generator(array $files): FileGenerator
    {
        return new readonly class ($files) implements FileGenerator {
            /** @param list<File> $files */
            public function __construct(
                private array $files,
            ) {
            }

            /** @return iterable<File> */
            public function generate(\OpenAPITools\Contract\Package $package, Representation $representation): iterable
            {
                unset($package, $representation);

                yield from $this->files;
            }
        };
    }

    private function location(): string
    {
        $location = $this->getTmpDir();
        copy(__DIR__ . '/fixtures/spec.yaml', $location . 'spec.yaml');

        return $location;
    }

    private function fileUrl(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if (preg_match('#^[A-Za-z]:/#', $path) === 1) {
            return 'file:///' . $path;
        }

        return 'file://' . $path;
    }

    private function saveState(string $location, callable $configure): void
    {
        $configuration = $this->configuration([]);
        $package       = $configuration->packages[0];
        self::assertInstanceOf(Package::class, $package);

        $state = State::initialize();
        $configure($state);

        new StateManagement($location, $configuration)->save($package, new State(
            hash('sha3-512', (string) file_get_contents($location . 'spec.yaml')),
            $state->generatedFiles,
            $state->additionalFiles,
        ));
    }
}
