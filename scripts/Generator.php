<?php

/*
 * Copyright (c) Romain Cottard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Eureka\Component\ClientApiGenerator\Script;

use cebe\openapi\exceptions\IOException;
use cebe\openapi\exceptions\TypeErrorException;
use cebe\openapi\exceptions\UnresolvableReferenceException;
use cebe\openapi\json\InvalidJsonPointerSyntaxException;
use cebe\openapi\Reader;
use cebe\openapi\spec\Schema;
use Eureka\Component\ClientApiGenerator\ClientBuilder;
use Eureka\Component\ClientApiGenerator\Config\Config;
use Eureka\Component\ClientApiGenerator\Enum\OperationType;
use Eureka\Component\ClientApiGenerator\FormatterBuilder;
use Eureka\Component\ClientApiGenerator\Schema\JsonApiSchema;
use Eureka\Component\ClientApiGenerator\Schema\SchemaResolver;
use Eureka\Component\ClientApiGenerator\Type\TypeResolver;
use Eureka\Component\ClientApiGenerator\VOBuilder;
use Eureka\Component\Console\AbstractScript;
use Eureka\Component\Console\Help;
use Eureka\Component\Console\Option\Option;
use Eureka\Component\Console\Option\Options;
use PhpParser\BuilderFactory;

/**
 * @phpstan-import-type ConfigData from Config
 */
class Generator extends AbstractScript
{
    public function __construct(
    ) {
        $this->setDescription('Orm generator');
        $this->setExecutable();

        $this->initOptions(
            (new Options())
                ->add(new Option('c', 'config', 'Json config file for generator', mandatory: false, hasArgument: true))
                ->add(new Option('f', 'file', 'OpenAPI file to process', mandatory: false, hasArgument: true))
                ->add(new Option('n', 'namespace', 'Base namespace for all generated classes', mandatory: false, hasArgument: true))
                ->add(new Option('d', 'destination', 'Destination path for generated code', mandatory: false, hasArgument: true)),
        );
    }

    public function help(): void
    {
        (new Help('...', $this->declaredOptions(), $this->output(), $this->options()))->display();
    }

    /**
     * @throws IOException
     * @throws TypeErrorException
     * @throws UnresolvableReferenceException
     * @throws InvalidJsonPointerSyntaxException
     * @throws \JsonException
     */
    public function run(): void
    {
        $config = $this->initConfig();

        $builderFactory   = new BuilderFactory();
        $schemaResolver   = new SchemaResolver($config);
        $typeResolver     = new TypeResolver($config);
        $jsonApiSchema    = new JsonApiSchema();
        $voBuilder        = new VOBuilder($builderFactory, $schemaResolver, $typeResolver);
        $formatterBuilder = new FormatterBuilder($builderFactory, $voBuilder, $schemaResolver, $typeResolver);
        $clientBuilder    = new ClientBuilder($builderFactory, $formatterBuilder, $voBuilder, $jsonApiSchema, $schemaResolver, $typeResolver);

        if (\str_ends_with($config->file, '.yaml')) {
            $openapi = Reader::readFromYamlFile((string) \realpath($config->file));
        } else {
            $openapi = Reader::readFromJsonFile((string) \realpath($config->file));
        }

        //~ Add each path as client
        $paths   = $openapi->paths->getPaths();
        foreach ($paths as $path => $pathItem) {
            if ($config->isIgnoredPath($path)) {
                continue;
            }
            $clientBuilder->add($path, $pathItem);
        }

        if (!\is_dir($config->destination) && !\mkdir($config->destination, 0755, true)) {
            throw new \UnexpectedValueException("Failed to create directory '$config->destination' !");
        }

        $clientBuilder->generate($config->destination, $config->namespace);
        $formatterBuilder->generate($config->destination, $config->namespace);
        $voBuilder->generate($config->destination, $config->namespace);
    }

    /**
     * @throws \JsonException
     */
    private function initConfig(): Config
    {
        $configData = [];

        $configFile = \trim((string) $this->options()->value('c', 'config'));
        if (\file_exists($configFile)) {
            /** @var ConfigData $configData */
            $configData = \json_decode((string) \file_get_contents($configFile), associative: true, flags: \JSON_THROW_ON_ERROR);
        }

        $file        = $this->options()->value('f', 'file');
        $destination = $this->options()->value('d', 'destination');
        $namespace   = $this->options()->value('n', 'namespace');

        $file = \trim((string) ($file ?? $configData['file'] ?? ''));
        if (!\file_exists($file)) {
            throw new \UnexpectedValueException('Cannot found file');
        }

        $schemas = [];
        if (isset($configData['custom']['schemas']) && $configData['custom']['schemas'] !== []) {
            foreach ($configData['custom']['schemas'] as $name => $definition) {
                $schemas[$name] = new Schema($definition);
            }
        }

        return new Config(
            $file,
            (string) ($namespace ?? $configData['namespace'] ?? ''),
            (string) ($destination ?? $configData['destination'] ?? ''),
            $configData['ignorePaths'] ?? [],
            $configData['overrides']['types'] ?? [],
            $configData['overrides']['schemas'] ?? [],
            $schemas,
        );
    }
}
