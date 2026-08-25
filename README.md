# generator

Package generation engine for [OpenAPI Tools](https://github.com/php-openapi-tools). Reads an OpenAPI spec and configuration, gathers a representation, runs file generators, and writes generated packages to disk with incremental state tracking.

![Continuous Integration](https://github.com/php-openapi-tools/generator/workflows/Continuous%20Integration/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/openapi-tools/generator/v/stable.png)](https://packagist.org/packages/openapi-tools/generator)
[![Total Downloads](https://poser.pugx.org/openapi-tools/generator/downloads.png)](https://packagist.org/packages/openapi-tools/generator/stats)
[![License](https://poser.pugx.org/openapi-tools/generator/license.png)](https://packagist.org/packages/openapi-tools/generator)

## Installation

To install via [Composer](https://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `^`.

```
composer require openapi-tools/generator
```

The package ships a CLI binary:

```
vendor/bin/openapi-generator <configuration>
```

## Components

| Class | Purpose |
| --- | --- |
| `Generator` | Orchestrates spec loading, gathering, file generation, and state persistence |
| `StateManagement` | Loads and saves per-package generation state from JSON |
| `PathResolver` | Resolves configuration-relative paths and normalizes output file locations |

Generation is configured through [`openapi-tools/configuration`](https://github.com/php-openapi-tools/configuration). File generators implement [`openapi-tools/contract`](https://github.com/php-openapi-tools/contract) `FileGenerator` and are typically provided by packages such as [`generator-schema`](https://github.com/php-openapi-tools/generator-schema), [`generator-hydrator`](https://github.com/php-openapi-tools/generator-hydrator), and [`generator-templates`](https://github.com/php-openapi-tools/generator-templates).

## Usage

### CLI

Pass a PHP or YAML configuration file. Paths in the configuration are resolved relative to the configuration file directory:

```shell
openapi-generator ./example/config.php
```

PHP configurations must `return` a `Configuration` instance. YAML configurations are hydrated automatically:

```shell
openapi-generator ./config.yaml
```

### Programmatic API

```php
use OpenAPITools\Generator\Generator;

Generator::generate($configuration, __DIR__ . '/');
```

The second argument is the configuration directory. It may be absolute or relative to the current working directory.

### Configuration

See [`openapi-tools/configuration`](https://github.com/php-openapi-tools/configuration) for the full configuration model. A minimal PHP example:

```php
use OpenAPITools\Configuration\Configuration;
use OpenAPITools\Configuration\Gathering;
use OpenAPITools\Configuration\Package;
use OpenAPITools\Generator\Schema\Schema;
use OpenAPITools\Utils\Namespace_;
use PhpParser\BuilderFactory;

$builderFactory = new BuilderFactory();

return new Configuration(
    new \OpenAPITools\Configuration\State('etc/state.json'),
    new Gathering('api.github.com.yaml', null, new Gathering\Schemas(true, true)),
    [
        new Package(
            new Package\Metadata('Example', 'Example API client', []),
            'api-clients',
            'example',
            null,
            null,
            null,
            new Package\Templates(__DIR__ . '/templates', []),
            new Package\Destination('example', 'src', 'tests'),
            new Namespace_(
                'ApiClients\Client\Example',
                'ApiClients\Tests\Client\Example',
            ),
            new Package\QA(
                phpcs: new Package\QA\Tool(true, null),
                phpstan: new Package\QA\Tool(true, null),
                psalm: new Package\QA\Tool(false, null),
            ),
            new Package\State(['composer.json', 'composer.lock']),
            [
                new Schema($builderFactory),
            ],
        ),
    ],
);
```

The [`example/config.php`](example/config.php) in this repository shows a complete setup including schema, hydrator, and template generators.

Run the example generation with:

```shell
make generate-packages
```

### Generation flow

For each `Package` in the configuration, `Generator`:

1. Loads the OpenAPI spec (local path or URL) and hashes its contents.
2. Builds a [`Representation`](https://github.com/php-openapi-tools/representation) through [`Gatherer::gather()`](https://github.com/php-openapi-tools/gatherer).
3. Resolves class names against the package namespace.
4. Runs each configured `FileGenerator` and writes emitted `File` instances.
5. Skips writes when file content is unchanged (based on stored hashes).
6. Removes stale generated files that are no longer emitted but remain inside the package output directory.
7. Tracks configured additional files (such as `composer.json`) by hash without overwriting them.
8. Persists updated state to the configured state file inside the package output directory.

Generated PHP files with `File::DO_LOAD_ON_WRITE` are `include_once`'d during the run so later generators can depend on freshly written classes.

### State management

Generation state is stored per package at `{destination.root}/{state.file}`. The state records:

| Field | Purpose |
| --- | --- |
| `specHash` | SHA3-512 hash of the OpenAPI spec used for the last run |
| `generatedFiles` | Map of generated file paths to content hashes |
| `additionalFiles` | Map of tracked non-generated files to content hashes |

On the next run, unchanged generated files are not rewritten. Files removed from generator output are deleted when they still exist under the package destination. Additional files listed in `Package\State` are only tracked; their contents are never modified by the generator.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT)

Copyright (c) 2026 Cees-Jan Kiewiet

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
