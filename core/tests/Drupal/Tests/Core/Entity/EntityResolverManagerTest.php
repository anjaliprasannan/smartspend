<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Entity;

use Drupal\Core\Entity\EntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityResolverManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Routing\Route;

/**
 * @coversDefaultClass \Drupal\Core\Entity\EntityResolverManager
 * @group Entity
 */
class EntityResolverManagerTest extends UnitTestCase {

  /**
   * The tested entity resolver manager.
   *
   * @var \Drupal\Core\Entity\EntityResolverManager
   */
  protected $entityResolverManager;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The mocked class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $classResolver;

  /**
   * The mocked dependency injection container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $container;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $this->container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
    $this->classResolver = $this->getClassResolverStub();

    $this->entityResolverManager = new EntityResolverManager($this->entityTypeManager, $this->classResolver);
  }

  /**
   * Tests setRouteOptions() with no parameter.
   *
   * We don't have any entity type involved, so we don't need any upcasting.
   *
   * @covers ::setRouteOptions
   * @covers ::getControllerClass
   *
   * @dataProvider providerTestSetRouteOptionsWithStandardRoute
   */
  public function testSetRouteOptionsWithStandardRoute($controller): void {
    $route = new Route('/example', [
      '_controller' => $controller,
    ]);

    $defaults = $route->getDefaults();
    $this->entityResolverManager->setRouteOptions($route);
    $this->assertEquals($defaults, $route->getDefaults());
    $this->assertEmpty($route->getOption('parameters'));
  }

  /**
   * Data provider for testSetRouteOptionsWithStandardRoute.
   */
  public static function providerTestSetRouteOptionsWithStandardRoute() {
    return [
      ['Drupal\Tests\Core\Entity\BasicControllerClass::exampleControllerMethod'],
      ['Drupal\Tests\Core\Entity\test_function_controller'],
    ];
  }

  /**
   * Tests setRouteOptions() with a controller with a non entity argument.
   *
   * @covers ::setRouteOptions
   * @covers ::getControllerClass
   *
   * @dataProvider providerTestSetRouteOptionsWithStandardRouteWithArgument
   */
  public function testSetRouteOptionsWithStandardRouteWithArgument($controller): void {
    $route = new Route('/example/{argument}', [
      '_controller' => $controller,
      'argument' => 'test',
    ]);

    $defaults = $route->getDefaults();
    $this->entityResolverManager->setRouteOptions($route);
    $this->assertEquals($defaults, $route->getDefaults());
    $this->assertEmpty($route->getOption('parameters'));
  }

  /**
   * Data provider for testSetRouteOptionsWithStandardRouteWithArgument.
   */
  public static function providerTestSetRouteOptionsWithStandardRouteWithArgument() {
    return [
      ['Drupal\Tests\Core\Entity\BasicControllerClass::exampleControllerMethodWithArgument'],
      ['Drupal\Tests\Core\Entity\test_function_controller_with_argument'],
    ];
  }

  /**
   * Tests setRouteOptions() with a _content default.
   *
   * @covers ::setRouteOptions
   * @covers ::getControllerClass
   *
   * @dataProvider providerTestSetRouteOptionsWithContentController
   */
  public function testSetRouteOptionsWithContentController($controller): void {
    $route = new Route('/example/{argument}', [
      '_controller' => $controller,
      'argument' => 'test',
    ]);

    $defaults = $route->getDefaults();
    $this->entityResolverManager->setRouteOptions($route);
    $this->assertEquals($defaults, $route->getDefaults());
    $this->assertEmpty($route->getOption('parameters'));
  }

  /**
   * Data provider for testSetRouteOptionsWithContentController.
   */
  public static function providerTestSetRouteOptionsWithContentController() {
    return [
      ['Drupal\Tests\Core\Entity\BasicControllerClass::exampleControllerMethodWithArgument'],
      ['Drupal\Tests\Core\Entity\test_function_controller_with_argument'],
    ];
  }

  /**
   * Tests setRouteOptions() with an entity type parameter.
   *
   * @covers ::setRouteOptions
   * @covers ::getControllerClass
   * @covers ::getEntityTypes
   * @covers ::setParametersFromReflection
   *
   * @dataProvider providerTestSetRouteOptionsWithEntityTypeNoUpcasting
   */
  public function testSetRouteOptionsWithEntityTypeNoUpcasting($controller): void {
    $this->setupEntityTypes();

    $route = new Route('/example/{entity_test}', [
      '_controller' => $controller,
    ]);

    $defaults = $route->getDefaults();
    $this->entityResolverManager->setRouteOptions($route);
    $this->assertEquals($defaults, $route->getDefaults());
    $this->assertEmpty($route->getOption('parameters'));
  }

  /**
   * Data provider for testSetRouteOptionsWithEntityTypeNoUpcasting.
   */
  public static function providerTestSetRouteOptionsWithEntityTypeNoUpcasting() {
    return [
      ['Drupal\Tests\Core\Entity\BasicControllerClass::exampleControllerWithEntityNoUpcasting'],
      ['Drupal\Tests\Core\Entity\test_function_controller_no_upcasting'],
    ];
  }

  /**
   * Tests setRouteOptions() with an entity type parameter, upcasting.
   *
   * @covers ::setRouteOptions
   * @covers ::getControllerClass
   * @covers ::getEntityTypes
   * @covers ::setParametersFromReflection
   *
   * @dataProvider providerTestSetRouteOptionsWithEntityTypeUpcasting
   */
  public function testSetRouteOptionsWithEntityTypeUpcasting($controller): void {
    $this->setupEntityTypes();

    $route = new Route('/example/{entity_test}', [
      '_controller' => $controller,
    ]);

    $defaults = $route->getDefaults();
    $this->entityResolverManager->setRouteOptions($route);
    $this->assertEquals($defaults, $route->getDefaults());
    $parameters = $route->getOption('parameters');
    $this->assertEquals(['entity_test' => ['type' => 'entity:entity_test']], $parameters);
  }

  /**
   * Data provider for testSetRouteOptionsWithEntityTypeUpcasting.
   */
  public static function providerTestSetRouteOptionsWithEntityTypeUpcasting() {
    return [
      ['Drupal\Tests\Core\Entity\BasicControllerClass::exampleControllerWithEntityUpcasting'],
      ['Drupal\Tests\Core\Entity\test_function_controller_entity_upcasting'],
    ];
  }

  /**
   * Tests setRouteOptions() with an entity type parameter form.
   *
   * @covers ::setRouteOptions
   * @covers ::getControllerClass
   * @covers ::getEntityTypes
   * @covers ::setParametersFromReflection
   */
  public function testSetRouteOptionsWithEntityFormUpcasting(): void {
    $this->setupEntityTypes();

    $route = new Route('/example/{entity_test}', [
      '_form' => 'Drupal\Tests\Core\Entity\BasicForm',
    ]);

    $defaults = $route->getDefaults();
    $this->entityResolverManager->setRouteOptions($route);
    $this->assertEquals($defaults, $route->getDefaults());
    $parameters = $route->getOption('parameters');
    $this->assertEquals(['entity_test' => ['type' => 'entity:entity_test']], $parameters);
  }

  /**
   * Tests setRouteOptions() with entity form upcasting, no create method.
   *
   * @covers ::setRouteOptions
   * @covers ::getControllerClass
   * @covers ::getEntityTypes
   * @covers ::setParametersFromReflection
   */
  public function testSetRouteOptionsWithEntityUpcastingNoCreate(): void {
    $this->setupEntityTypes();

    $route = new Route('/example/{entity_test}', [
      '_form' => 'Drupal\Tests\Core\Entity\BasicFormNoContainerInjectionInterface',
    ]);

    $defaults = $route->getDefaults();
    $this->entityResolverManager->setRouteOptions($route);
    $this->assertEquals($defaults, $route->getDefaults());
    $parameters = $route->getOption('parameters');
    $this->assertEquals(['entity_test' => ['type' => 'entity:entity_test']], $parameters);
  }

  /**
   * Tests setRouteOptions() with a form parameter without interface.
   *
   * @covers ::setRouteOptions
   * @covers ::getControllerClass
   * @covers ::getEntityTypes
   * @covers ::setParametersFromReflection
   */
  public function testSetRouteOptionsWithEntityFormNoUpcasting(): void {
    $this->setupEntityTypes();

    $route = new Route('/example/{entity_test}', [
      '_form' => 'Drupal\Tests\Core\Entity\BasicFormNoUpcasting',
    ]);

    $defaults = $route->getDefaults();
    $this->entityResolverManager->setRouteOptions($route);
    $this->assertEquals($defaults, $route->getDefaults());
    $this->assertEmpty($route->getOption('parameters'));
  }

  /**
   * Tests setRouteOptions() with an _entity_view route.
   *
   * @covers ::setRouteOptions
   * @covers ::getControllerClass
   * @covers ::getEntityTypes
   * @covers ::setParametersFromReflection
   * @covers ::setParametersFromEntityInformation
   */
  public function testSetRouteOptionsWithEntityViewRouteAndManualParameters(): void {
    $this->setupEntityTypes();
    $route = new Route('/example/{foo}',
      [
        '_entity_view' => 'entity_test.view',
      ],
      [],
      [
        'parameters' => [
          'foo' => [
            'type' => 'entity:entity_test',
          ],
        ],
      ]
    );

    $defaults = $route->getDefaults();
    $this->entityResolverManager->setRouteOptions($route);
    $this->assertEquals($defaults, $route->getDefaults());
    $parameters = $route->getOption('parameters');
    $this->assertEquals(['foo' => ['type' => 'entity:entity_test']], $parameters);
  }

  /**
   * Tests setRouteOptions() with an _entity_view route.
   *
   * @covers ::setRouteOptions
   * @covers ::getControllerClass
   * @covers ::getEntityTypes
   * @covers ::setParametersFromReflection
   * @covers ::setParametersFromEntityInformation
   */
  public function testSetRouteOptionsWithEntityViewRoute(): void {
    $this->setupEntityTypes();
    $route = new Route('/example/{entity_test}', [
      '_entity_view' => 'entity_test.view',
    ]);

    $defaults = $route->getDefaults();
    $this->entityResolverManager->setRouteOptions($route);
    $this->assertEquals($defaults, $route->getDefaults());
    $parameters = $route->getOption('parameters');
    $this->assertEquals(['entity_test' => ['type' => 'entity:entity_test']], $parameters);
  }

  /**
   * Tests setRouteOptions() with an _entity_list route.
   *
   * @covers ::setRouteOptions
   * @covers ::getControllerClass
   * @covers ::getEntityTypes
   * @covers ::setParametersFromReflection
   * @covers ::setParametersFromEntityInformation
   */
  public function testSetRouteOptionsWithEntityListRoute(): void {
    $this->setupEntityTypes();
    $route = new Route('/example/{entity_test}', [
      '_entity_list' => 'entity_test',
    ]);

    $defaults = $route->getDefaults();
    $this->entityResolverManager->setRouteOptions($route);
    $this->assertEquals($defaults, $route->getDefaults());
    $parameters = $route->getOption('parameters');
    $this->assertNull($parameters);
  }

  /**
   * Tests setRouteOptions() with an _entity_form route.
   *
   * @covers ::setRouteOptions
   * @covers ::getControllerClass
   * @covers ::getEntityTypes
   * @covers ::setParametersFromReflection
   * @covers ::setParametersFromEntityInformation
   */
  public function testSetRouteOptionsWithEntityFormRoute(): void {
    $this->setupEntityTypes();
    $route = new Route('/example/{entity_test}', [
      '_entity_form' => 'entity_test.edit',
    ]);

    $defaults = $route->getDefaults();
    $this->entityResolverManager->setRouteOptions($route);
    $this->assertEquals($defaults, $route->getDefaults());
    $parameters = $route->getOption('parameters');
    $this->assertEquals(['entity_test' => ['type' => 'entity:entity_test']], $parameters);
  }

  /**
   * Tests an _entity_form route where a non-entity parameter is first.
   *
   * The {argument} preceding {entity_test} in route path, is upcasting with a
   * custom param converter.
   *
   * @covers ::setRouteOptions
   * @covers ::getControllerClass
   * @covers ::getEntityTypes
   * @covers ::setParametersFromReflection
   * @covers ::setParametersFromEntityInformation
   */
  public function testSetRouteOptionsWithEntityFormRouteAndArgument(): void {
    $this->setupEntityTypes();
    $route = new Route('/example/{argument}/{entity_test}', [
      '_entity_form' => 'entity_test.edit',
    ]);
    // Add {argument} parameter configuration. In this case {argument} is
    // upcasted by a custom param converter 'argument_type'.
    $route->setOption('parameters', ['argument' => ['type' => 'argument_type']]);

    $defaults = $route->getDefaults();
    $this->entityResolverManager->setRouteOptions($route);
    $this->assertEquals($defaults, $route->getDefaults());
    $parameters = $route->getOption('parameters');
    $expect = [
      'argument' => ['type' => 'argument_type'],
      'entity_test' => ['type' => 'entity:entity_test'],
    ];
    $this->assertEquals($expect, $parameters);
  }

  /**
   * Tests setRouteOptions() with an _entity_form route for an add form.
   *
   * @covers ::setRouteOptions
   * @covers ::getControllerClass
   * @covers ::getEntityTypes
   * @covers ::setParametersFromReflection
   * @covers ::setParametersFromEntityInformation
   */
  public function testSetRouteOptionsWithEntityAddFormRoute(): void {
    $this->setupEntityTypes();
    $route = new Route('/example/add', [
      '_entity_form' => 'entity_test.add',
    ]);

    $defaults = $route->getDefaults();
    $this->entityResolverManager->setRouteOptions($route);
    $this->assertEquals($defaults, $route->getDefaults());
    $this->assertFalse($route->hasOption('parameters'));
  }

  /**
   * Creates the entity type manager mock returning entity type objects.
   */
  protected function setupEntityTypes(): void {
    $definition = $this->createMock('Drupal\Core\Entity\EntityTypeInterface');
    $definition->expects($this->any())
      ->method('getClass')
      ->willReturn('Drupal\Tests\Core\Entity\TestEntity');
    $definition->expects($this->any())
      ->method('isRevisionable')
      ->willReturn(FALSE);
    $revisionable_definition = $this->createMock('Drupal\Core\Entity\EntityTypeInterface');
    $revisionable_definition->expects($this->any())
      ->method('getClass')
      ->willReturn('Drupal\Tests\Core\Entity\TestEntity');
    $revisionable_definition->expects($this->any())
      ->method('isRevisionable')
      ->willReturn(TRUE);
    $this->entityTypeManager->expects($this->any())
      ->method('getDefinitions')
      ->willReturn([
        'entity_test' => $definition,
        'entity_test_rev' => $revisionable_definition,
      ]);
    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->willReturnCallback(function ($entity_type) use ($definition, $revisionable_definition) {
        if ($entity_type == 'entity_test') {
          return $definition;
        }
        elseif ($entity_type === 'entity_test_rev') {
          return $revisionable_definition;
        }
        else {
          return NULL;
        }
      });
  }

}

/**
 * A class containing all kind of different controller methods.
 */
class BasicControllerClass {

  public function exampleControllerMethod() {
  }

  public function exampleControllerMethodWithArgument($argument) {
  }

  public function exampleControllerWithEntityNoUpcasting($entity_test) {
  }

  public function exampleControllerWithEntityUpcasting(EntityInterface $entity_test) {
  }

}

/**
 * A concrete entity.
 */
class TestEntity extends EntityBase {

}

/**
 * A basic form with a passed entity with an interface.
 *
 * @internal
 */
class BasicForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EntityInterface $entity_test = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}

/**
 * A basic form with a passed entity without an interface.
 */
class BasicFormNoUpcasting extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_test = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}

/**
 * A basic form without a container injection interface implemented.
 */
class BasicFormNoContainerInjectionInterface implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EntityInterface $entity_test = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}

function test_function_controller() {
}

function test_function_controller_with_argument($argument) {
}

function test_function_controller_no_upcasting($entity_test) {
}

function test_function_controller_entity_upcasting(EntityInterface $entity_test) {
}
