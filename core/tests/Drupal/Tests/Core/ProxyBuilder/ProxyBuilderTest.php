<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\ProxyBuilder;

use Drupal\Core\ProxyBuilder\ProxyBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\ProxyBuilder\ProxyBuilder
 * @group proxy_builder
 */
class ProxyBuilderTest extends UnitTestCase {

  /**
   * The tested proxy builder.
   *
   * @var \Drupal\Core\ProxyBuilder\ProxyBuilder
   */
  protected $proxyBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->proxyBuilder = new ProxyBuilder();
  }

  /**
   * @covers ::buildMethod
   * @covers ::buildParameter
   * @covers ::buildMethodBody
   */
  public function testBuildComplexMethod(): void {
    $class = 'Drupal\Tests\Core\ProxyBuilder\TestServiceComplexMethod';

    $result = $this->proxyBuilder->build($class);

    // @todo Solve the silly linebreak for an empty array.
    $method_body = <<<'EOS'

/**
 * {@inheritdoc}
 */
public function complexMethod($parameter, callable $function, ?\Drupal\Tests\Core\ProxyBuilder\TestServiceNoMethod $test_service = NULL, array &$elements = array (
))
{
    return $this->lazyLoadItself()->complexMethod($parameter, $function, $test_service, $elements);
}

EOS;

    $this->assertEquals($this->buildExpectedClass($class, $method_body), $result);
  }

  /**
   * Constructs the expected class output.
   *
   * @param string $class
   *   The class name that is being built.
   * @param string $expected_methods_body
   *   The expected body of decorated methods.
   * @param string $interface_string
   *   (optional) The expected "implements" clause of the class definition.
   *
   * @return string
   *   The code of the entire proxy.
   */
  protected function buildExpectedClass($class, $expected_methods_body, $interface_string = '') {
    $reflection = new \ReflectionClass($class);
    $namespace = ProxyBuilder::buildProxyNamespace($class);
    $proxy_class = $reflection->getShortName();
    $expected_string = <<<'EOS'

namespace {{ namespace }} {

    /**
     * Provides a proxy class for \{{ class }}.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class {{ proxy_class }}{{ interface_string }}
    {

        use \Drupal\Core\DependencyInjection\DependencySerializationTrait;

        /**
         * The id of the original proxied service.
         *
         * @var string
         */
        protected $drupalProxyOriginalServiceId;

        /**
         * The real proxied service, after it was lazy loaded.
         *
         * @var \{{ class }}
         */
        protected $service;

        /**
         * The service container.
         *
         * @var \Symfony\Component\DependencyInjection\ContainerInterface
         */
        protected $container;

        /**
         * Constructs a ProxyClass Drupal proxy object.
         *
         * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
         *   The container.
         * @param string $drupal_proxy_original_service_id
         *   The service ID of the original service.
         */
        public function __construct(\Symfony\Component\DependencyInjection\ContainerInterface $container, $drupal_proxy_original_service_id)
        {
            $this->container = $container;
            $this->drupalProxyOriginalServiceId = $drupal_proxy_original_service_id;
        }

        /**
         * Lazy loads the real service from the container.
         *
         * @return object
         *   Returns the constructed real service.
         */
        protected function lazyLoadItself()
        {
            if (!isset($this->service)) {
                $this->service = $this->container->get($this->drupalProxyOriginalServiceId);
            }

            return $this->service;
        }
{{ expected_methods_body }}
    }

}

EOS;

    $expected_methods_body = implode("\n", array_map(function ($value) {
      if ($value === '') {
        return $value;
      }
      return "        $value";
    }, explode("\n", $expected_methods_body)));

    $expected_string = str_replace('{{ proxy_class }}', $proxy_class, $expected_string);
    $expected_string = str_replace('{{ namespace }}', $namespace, $expected_string);
    $expected_string = str_replace('{{ class }}', $class, $expected_string);
    $expected_string = str_replace('{{ expected_methods_body }}', $expected_methods_body, $expected_string);
    $expected_string = str_replace('{{ interface_string }}', $interface_string, $expected_string);

    return $expected_string;
  }

}

/**
 * Class used to test a service that has no methods.
 */
class TestServiceNoMethod {

}

/**
 * Call used to test a service with a complex method.
 */
class TestServiceComplexMethod {

  public function complexMethod($parameter, callable $function, ?TestServiceNoMethod $test_service = NULL, array &$elements = []) {

  }

}
