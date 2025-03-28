<?php

// phpcs:ignoreFile Portions of this file are a direct copy of
// \Symfony\Component\DependencyInjection\Loader\YamlFileLoader.

namespace Drupal\Core\DependencyInjection;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Core\Serialization\Yaml;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * YamlFileLoader loads YAML files service definitions.
 *
 * Drupal does not use Symfony's Config component, and Symfony's dependency on
 * it cannot be removed easily. Therefore, this is a partial but mostly literal
 * copy of upstream, which does not depend on the Config component.
 *
 * @see \Symfony\Component\DependencyInjection\Loader\YamlFileLoader
 * @see https://github.com/symfony/symfony/pull/10920
 *
 * NOTE: 98% of this code is a literal copy of Symfony's YamlFileLoader.
 *
 * This file does NOT follow Drupal coding standards, so as to simplify future
 * synchronizations.
 */
class YamlFileLoader
{
    private const DEFAULTS_KEYWORDS = [
        'public' => 'public',
        'tags' => 'tags',
        'autowire' => 'autowire',
        'autoconfigure' => 'autoconfigure',
    ];

    /**
     * @var \Drupal\Core\DependencyInjection\ContainerBuilder $container
     */
    protected $container;

    /**
     * File cache object.
     *
     * @var \Drupal\Component\FileCache\FileCacheInterface
     */
    protected $fileCache;

    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
        $this->fileCache = FileCacheFactory::get('container_yaml_loader');
    }

    /**
     * Loads a Yaml file.
     *
     * @param mixed $file
     *   The resource
     */
    public function load($file)
    {
        // Load from the file cache, fall back to loading the file.
        $content = $this->fileCache->get($file);
        if (!$content) {
            $content = $this->loadFile($file);
            $this->fileCache->set($file, $content);
        }

        // Not supported.
        //$this->container->addResource(new FileResource($path));

        // empty file
        if (null === $content) {
            return;
        }

        // imports
        // Not supported.
        //$this->parseImports($content, $file);

        // parameters
        if (isset($content['parameters'])) {
            if (!is_array($content['parameters'])) {
                throw new InvalidArgumentException(sprintf('The "parameters" key should contain an array in %s. Check your YAML syntax.', $file));
            }

            foreach ($content['parameters'] as $key => $value) {
                $this->container->setParameter($key, $this->resolveServices($value));
            }
        }

        // extensions
        // Not supported.
        //$this->loadFromExtensions($content);

        // services
        $this->parseDefinitions($content, $file);
    }

    /**
     * Parses definitions
     *
     * @param array $content
     * @param string $file
     */
    private function parseDefinitions($content, $file)
    {
        if (!isset($content['services'])) {
            return;
        }

        if (!is_array($content['services'])) {
            throw new InvalidArgumentException(sprintf('The "services" key should contain an array in %s. Check your YAML syntax.', $file));
        }

        // Some extensions split up their dependencies into multiple files.
        if (isset($content['_provider'])) {
            $provider = $content['_provider'];
        }
        else {
            $basename = basename($file);
            [$provider, ] = explode('.', $basename, 2);
        }
        $defaults = $this->parseDefaults($content, $file);
        $defaults['tags'][] = [
            'name' => '_provider',
            'provider' => $provider
        ];
        foreach ($content['services'] as $id => $service) {
            $this->parseDefinition($id, $service, $file, $defaults);
        }
    }

    /**
     * @param array  $content
     * @param string $file
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    private function parseDefaults(array &$content, string $file): array
    {
        if (!\array_key_exists('_defaults', $content['services'])) {
            return [];
        }
        $defaults = $content['services']['_defaults'];
        unset($content['services']['_defaults']);

        if (!\is_array($defaults)) {
            throw new InvalidArgumentException(sprintf('Service "_defaults" key must be an array, "%s" given in "%s".', \gettype($defaults), $file));
        }

        foreach ($defaults as $key => $default) {
            if (!isset(self::DEFAULTS_KEYWORDS[$key])) {
                throw new InvalidArgumentException(sprintf('The configuration key "%s" cannot be used to define a default value in "%s". Allowed keys are "%s".', $key, $file, implode('", "', self::DEFAULTS_KEYWORDS)));
            }
        }

        if (isset($defaults['tags'])) {
            if (!\is_array($tags = $defaults['tags'])) {
                throw new InvalidArgumentException(sprintf('Parameter "tags" in "_defaults" must be an array in "%s". Check your YAML syntax.', $file));
            }

            foreach ($tags as $tag) {
                if (!\is_array($tag)) {
                    $tag = ['name' => $tag];
                }

                if (!isset($tag['name'])) {
                    throw new InvalidArgumentException(sprintf('A "tags" entry in "_defaults" is missing a "name" key in "%s".', $file));
                }
                $name = $tag['name'];
                unset($tag['name']);

                if (!\is_string($name) || '' === $name) {
                    throw new InvalidArgumentException(sprintf('The tag name in "_defaults" must be a non-empty string in "%s".', $file));
                }

                foreach ($tag as $attribute => $value) {
                    if (!is_scalar($value) && null !== $value) {
                        throw new InvalidArgumentException(sprintf('Tag "%s", attribute "%s" in "_defaults" must be of a scalar-type in "%s". Check your YAML syntax.', $name, $attribute, $file));
                    }
                }
            }
        }

        return $defaults;
    }

    /**
     * Parses a definition.
     *
     * @param string $id
     * @param array $service
     * @param string $file
     * @param array $defaults
     *
     * @throws InvalidArgumentException
     *   When tags are invalid.
     */
    private function parseDefinition(string $id, $service, string $file, array $defaults)
    {
        if (\is_string($service) && str_starts_with($service, '@')) {
            $this->container->setAlias($id, $alias = new Alias(substr($service, 1)));
            if (isset($defaults['public'])) {
                $alias->setPublic($defaults['public']);
            }

            return;
        }

        if (null === $service) {
            $service = [];
        }

        if (!is_array($service)) {
            throw new InvalidArgumentException(sprintf('A service definition must be an array or a string starting with "@" but %s found for service "%s" in %s. Check your YAML syntax.', gettype($service), $id, $file));
        }

        if (isset($service['alias'])) {
            $this->container->setAlias($id, $alias = new Alias($service['alias']));
            if (isset($service['public'])) {
                $alias->setPublic($service['public']);
            } elseif (isset($defaults['public'])) {
                $alias->setPublic($defaults['public']);
            }

            if (array_key_exists('deprecated', $service)) {
              $deprecation = \is_array($service['deprecated']) ? $service['deprecated'] : ['message' => $service['deprecated']];
              $alias->setDeprecated($deprecation['package'] ?? '', $deprecation['version'] ?? '', $deprecation['message']);
            }

            return;
        }

        if (isset($service['parent'])) {
            $definition = new ChildDefinition($service['parent']);
        } else {
            $definition = new Definition();
        }

        // Drupal services are public by default.
        $definition->setPublic(true);

        if (isset($defaults['public'])) {
            $definition->setPublic($defaults['public']);
        }
        if (isset($defaults['autowire'])) {
            $definition->setAutowired($defaults['autowire']);
        }
        if (isset($defaults['autoconfigure'])) {
            $definition->setAutoconfigured($defaults['autoconfigure']);
        }

        $definition->setChanges([]);

        if (isset($service['class'])) {
            $definition->setClass($service['class']);
        }

        if (isset($service['shared'])) {
            $definition->setShared($service['shared']);
        }

        if (isset($service['synthetic'])) {
            $definition->setSynthetic($service['synthetic']);
        }

        if (isset($service['lazy'])) {
            $definition->setLazy($service['lazy']);
        }

        if (isset($service['public'])) {
            $definition->setPublic($service['public']);
        }

        if (isset($service['abstract'])) {
            $definition->setAbstract($service['abstract']);
        }

        if (array_key_exists('deprecated', $service)) {
          $deprecation = \is_array($service['deprecated']) ? $service['deprecated'] : ['message' => $service['deprecated']];
          $definition->setDeprecated($deprecation['package'] ?? '', $deprecation['version'] ?? '', $deprecation['message']);
        }

        if (isset($service['factory'])) {
            if (is_string($service['factory'])) {
                if (str_contains($service['factory'], ':') && !str_contains($service['factory'], '::')) {
                    $parts = explode(':', $service['factory']);
                    $definition->setFactory([$this->resolveServices('@'.$parts[0]), $parts[1]]);
                } else {
                    $definition->setFactory($service['factory']);
                }
            } else {
                $definition->setFactory([$this->resolveServices($service['factory'][0]), $service['factory'][1]]);
            }
        }

        if (isset($service['factory_class'])) {
            $definition->setFactory($service['factory_class']);
        }

        if (isset($service['factory_method'])) {
            $definition->setFactory($service['factory_method']);
        }

        if (isset($service['factory_service'])) {
            $definition->setFactory($service['factory_service']);
        }

        if (isset($service['file'])) {
            $definition->setFile($service['file']);
        }

        if (isset($service['arguments'])) {
            $definition->setArguments($this->resolveServices($service['arguments']));
        }

        if (isset($service['properties'])) {
            $definition->setProperties($this->resolveServices($service['properties']));
        }

        if (isset($service['configurator'])) {
            if (is_string($service['configurator'])) {
                $definition->setConfigurator($service['configurator']);
            } else {
                $definition->setConfigurator([$this->resolveServices($service['configurator'][0]), $service['configurator'][1]]);
            }
        }

        if (isset($service['calls'])) {
            if (!is_array($service['calls'])) {
                throw new InvalidArgumentException(sprintf('Parameter "calls" must be an array for service "%s" in %s. Check your YAML syntax.', $id, $file));
            }

            foreach ($service['calls'] as $call) {
                if (isset($call['method'])) {
                    $method = $call['method'];
                    $args = isset($call['arguments']) ? $this->resolveServices($call['arguments']) : [];
                } else {
                    $method = $call[0];
                    $args = isset($call[1]) ? $this->resolveServices($call[1]) : [];
                }

                $definition->addMethodCall($method, $args);
            }
        }

        $tags = $service['tags'] ?? [];
        if (!\is_array($tags)) {
            throw new InvalidArgumentException(sprintf('Parameter "tags" must be an array for service "%s" in "%s". Check your YAML syntax.', $id, $file));
        }

        if (isset($defaults['tags'])) {
            $tags = array_merge($tags, $defaults['tags']);
        }

        foreach ($tags as $tag) {
            if (!\is_array($tag)) {
                $tag = ['name' => $tag];
            }

            if (!isset($tag['name'])) {
                throw new InvalidArgumentException(sprintf('A "tags" entry is missing a "name" key for service "%s" in "%s".', $id, $file));
            }
            $name = $tag['name'];
            unset($tag['name']);

            if (!\is_string($name) || '' === $name) {
                throw new InvalidArgumentException(sprintf('The tag name for service "%s" in "%s" must be a non-empty string.', $id, $file));
            }

            foreach ($tag as $attribute => $value) {
                if (!is_scalar($value) && null !== $value) {
                    throw new InvalidArgumentException(sprintf('A "tags" attribute must be of a scalar-type for service "%s", tag "%s", attribute "%s" in "%s". Check your YAML syntax.', $id, $name, $attribute, $file));
                }
            }

            $definition->addTag($name, $tag);
        }

        if (null !== $decorates = $service['decorates'] ?? null) {
            if ('' !== $decorates && '@' === $decorates[0]) {
                throw new InvalidArgumentException(\sprintf('The value of the "decorates" option for the "%s" service must be the id of the service without the "@" prefix (replace "%s" with "%s").', $id, $service['decorates'], substr($decorates, 1)));
            }

            $decorationOnInvalid = \array_key_exists('decoration_on_invalid', $service) ? $service['decoration_on_invalid'] : 'exception';
            if ('exception' === $decorationOnInvalid) {
                $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
            }
            elseif ('ignore' === $decorationOnInvalid) {
                $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
            }
            elseif (null === $decorationOnInvalid) {
                $invalidBehavior = ContainerInterface::NULL_ON_INVALID_REFERENCE;
            }
            elseif ('null' === $decorationOnInvalid) {
                throw new InvalidArgumentException(\sprintf('Invalid value "%s" for attribute "decoration_on_invalid" on service "%s". Did you mean null (without quotes) in "%s"?', $decorationOnInvalid, $id, $file));
            }
            else {
                throw new InvalidArgumentException(\sprintf('Invalid value "%s" for attribute "decoration_on_invalid" on service "%s". Did you mean "exception", "ignore" or null in "%s"?', $decorationOnInvalid, $id, $file));
            }

            $renameId = $service['decoration_inner_name'] ?? null;
            $priority = $service['decoration_priority'] ?? 0;

            $definition->setDecoratedService($decorates, $renameId, $priority, $invalidBehavior);
        }

        if (isset($service['autowire'])) {
            $definition->setAutowired($service['autowire']);
        }

        $this->container->setDefinition($id, $definition);
    }

    /**
     * Loads a YAML file.
     *
     * @param string $file
     *
     * @return array The file content
     *
     * @throws InvalidArgumentException
     *   When the given file is not a local file or when it does not exist.
     */
    protected function loadFile($file)
    {
        if (!stream_is_local($file)) {
            throw new InvalidArgumentException(sprintf('This is not a local file "%s".', $file));
        }

        if (!file_exists($file)) {
            throw new InvalidArgumentException(sprintf('The service file "%s" is not valid.', $file));
        }

        try {
          $valid_file = $this->validate(Yaml::decode(file_get_contents($file)), $file);
        }
        catch (InvalidDataTypeException $e) {
          throw new InvalidArgumentException(sprintf('The file "%s" does not contain valid YAML: ', $file) . $e->getMessage());
        }

        return $valid_file;
    }

    /**
     * Validates a YAML file.
     *
     * @param mixed $content
     * @param string $file
     *
     * @return array
     *
     * @throws InvalidArgumentException
     *   When service file is not valid.
     */
    private function validate($content, $file)
    {
        if (null === $content) {
            return $content;
        }

        if (!is_array($content)) {
            throw new InvalidArgumentException(sprintf('The service file "%s" is not valid. It should contain an array. Check your YAML syntax.', $file));
        }

        if ($invalid_keys = array_keys(array_diff_key($content, ['parameters' => 1, 'services' => 1]))) {
            throw new InvalidArgumentException(sprintf('The service file "%s" is not valid: it contains invalid root key(s) "%s". Services have to be added under "services" and Parameters under "parameters".', $file, implode('", "', $invalid_keys)));
        }

        return $content;
    }

    /**
     * Resolves services.
     *
     * @param string|array $value
     *
     * @return array|string|Reference
     */
    private function resolveServices(mixed $value): mixed
    {
        if ($value instanceof TaggedValue) {
            $argument = $value->getValue();
            if (\in_array($value->getTag(), ['tagged', 'tagged_iterator', 'tagged_locator'], true)) {
               $forLocator = 'tagged_locator' === $value->getTag();

              if (\is_array($argument) && isset($argument['tag']) && $argument['tag']) {
                 if ($diff = array_diff(array_keys($argument), $supportedKeys = ['tag', 'index_by', 'default_index_method', 'default_priority_method', 'exclude', 'exclude_self'])) {
                   throw new InvalidArgumentException(sprintf('"!%s" tag contains unsupported key "%s"; supported ones are "%s".', $value->getTag(), implode('", "', $diff), implode('", "', $supportedKeys)));
                 }

                 $argument = new TaggedIteratorArgument($argument['tag'], $argument['index_by'] ?? null, $argument['default_index_method'] ?? null, $forLocator, $argument['default_priority_method'] ?? null, (array) ($argument['exclude'] ?? null), $argument['exclude_self'] ?? true);
              } elseif (\is_string($argument) && $argument) {
                 $argument = new TaggedIteratorArgument($argument, null, null, $forLocator);
              } else {
                 throw new InvalidArgumentException(sprintf('"!%s" tags only accept a non empty string or an array with a key "tag"".', $value->getTag()));
              }

              if ($forLocator) {
                 $argument = new ServiceLocatorArgument($argument);
              }

              return $argument;
            }

            if ($value->getTag() === 'service_closure') {
                return new ServiceClosureArgument($this->resolveServices($argument));
            }

        }
        if (is_array($value)) {
            $value = array_map([$this, 'resolveServices'], $value);
        } elseif (is_string($value) && str_starts_with($value, '@=')) {
            // Not supported.
            //return new Expression(substr($value, 2));
            throw new InvalidArgumentException(sprintf("'%s' is an Expression, but expressions are not supported.", $value));
        } elseif (is_string($value) && str_starts_with($value, '@')) {
            if (str_starts_with($value, '@@')) {
                $value = substr($value, 1);
                $invalidBehavior = null;
            } elseif (str_starts_with($value, '@?')) {
                $value = substr($value, 2);
                $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
            } else {
                $value = substr($value, 1);
                $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
            }

            if (str_ends_with($value, '=')) {
                $value = substr($value, 0, -1);
            }

            if (null !== $invalidBehavior) {
                $value = new Reference($value, $invalidBehavior);
            }
        }

        return $value;
    }

}
