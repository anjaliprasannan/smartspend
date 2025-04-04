<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Render;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;

/**
 * @coversDefaultClass \Drupal\Core\Render\PlaceholderGenerator
 * @group Render
 */
class PlaceholderGeneratorTest extends RendererTestBase {

  /**
   * The tested placeholder generator.
   *
   * @var \Drupal\Core\Render\PlaceholderGenerator
   */
  protected $placeholderGenerator;

  /**
   * Ensure that the generated placeholder markup is valid.
   *
   * If it is not, then simply using DOMDocument on HTML that contains
   * placeholders may modify the placeholders' markup, which would make it
   * impossible to replace the placeholders: the placeholder markup in
   * #attached versus that in the HTML processed by DOMDocument would no longer
   * match.
   *
   * @covers ::createPlaceholder
   * @dataProvider providerCreatePlaceholderGeneratesValidHtmlMarkup
   */
  public function testCreatePlaceholderGeneratesValidHtmlMarkup(array $element): void {
    $build = $this->placeholderGenerator->createPlaceholder($element);

    $original_placeholder_markup = (string) $build['#markup'];
    $processed_placeholder_markup = Html::serialize(Html::load($build['#markup']));

    $this->assertEquals($original_placeholder_markup, $processed_placeholder_markup);
  }

  /**
   * Tests the creation of an element with a #lazy_builder callback.
   *
   * Between two renders neither the cache contexts nor tags sort should change.
   * A placeholder should generate the same hash, so it is not rendered twice.
   *
   * @covers ::createPlaceholder
   */
  public function testRenderPlaceholdersDifferentSortedContextsTags(): void {
    $contexts_1 = ['user', 'foo'];
    $contexts_2 = ['foo', 'user'];
    $tags_1 = ['current-temperature', 'foo'];
    $tags_2 = ['foo', 'current-temperature'];
    $test_element = [
      '#cache' => [
        'max-age' => Cache::PERMANENT,
      ],
      '#lazy_builder' => [
        'Drupal\Tests\Core\Render\PlaceholdersTest::callback',
        ['foo' => TRUE],
      ],
    ];

    $test_element['#cache']['contexts'] = $contexts_1;
    $test_element['#cache']['tags'] = $tags_1;
    $placeholder_element1 = $this->placeholderGenerator->createPlaceholder($test_element);

    $test_element['#cache']['contexts'] = $contexts_2;
    $test_element['#cache']['tags'] = $tags_1;
    $placeholder_element2 = $this->placeholderGenerator->createPlaceholder($test_element);

    $test_element['#cache']['contexts'] = $contexts_1;
    $test_element['#cache']['tags'] = $tags_2;
    $placeholder_element3 = $this->placeholderGenerator->createPlaceholder($test_element);

    // Verify placeholder and specially hash are same with different contexts
    // order.
    $this->assertSame((string) $placeholder_element1['#markup'], (string) $placeholder_element2['#markup']);

    // Verify placeholder and specially hash are same with different tags order.
    $this->assertSame((string) $placeholder_element1['#markup'], (string) $placeholder_element3['#markup']);
  }

  /**
   * @return array
   *   An array of test cases with different placeholder inputs.
   */
  public static function providerCreatePlaceholderGeneratesValidHtmlMarkup() {
    return [
      'multiple-arguments' => [['#lazy_builder' => ['Drupal\Tests\Core\Render\PlaceholdersTest::callback', ['foo', 'bar']]]],
      'special-character-&' => [['#lazy_builder' => ['Drupal\Tests\Core\Render\PlaceholdersTest::callback', ['foo&bar']]]],
      'special-character-"' => [['#lazy_builder' => ['Drupal\Tests\Core\Render\PlaceholdersTest::callback', ['foo"bar']]]],
      'special-character-<' => [['#lazy_builder' => ['Drupal\Tests\Core\Render\PlaceholdersTest::callback', ['foo<bar']]]],
      'special-character->' => [['#lazy_builder' => ['Drupal\Tests\Core\Render\PlaceholdersTest::callback', ['foo>bar']]]],
    ];

  }

}
