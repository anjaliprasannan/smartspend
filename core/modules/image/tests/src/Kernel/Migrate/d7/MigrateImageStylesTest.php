<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Kernel\Migrate\d7;

use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageStyleInterface;
use Drupal\image\ImageEffectBase;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Test image styles migration to config entities.
 *
 * @group image
 */
class MigrateImageStylesTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['image'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(static::$modules);
    $this->executeMigration('d7_image_styles');
  }

  /**
   * Tests the image styles migration.
   */
  public function testImageStylesMigration(): void {
    $this->assertEntity(
     'custom_image_style_1',
     "Custom image style 1",
      [
        'image_scale_and_crop',
        'image_desaturate',
      ],
      [
        [
          'width' => 55,
          'height' => 55,
          'anchor' => 'center-center',
        ],
        [],
      ]);
    $this->assertEntity(
     'custom_image_style_2',
     "Custom image style 2",
      [
        'image_resize',
        'image_rotate',
      ],
      [
        [
          'width' => 55,
          'height' => 100,
        ],
        [
          'degrees' => 45,
          'bgcolor' => '#FFFFFF',
          'random' => FALSE,
        ],
      ]);
    $this->assertEntity(
     'custom_image_style_3',
     "Custom image style 3",
      [
        'image_scale',
        'image_crop',
      ],
      [
        [
          'width' => 150,
          'height' => NULL,
          'upscale' => FALSE,
        ],
        [
          'width' => 50,
          'height' => 50,
          'anchor' => 'left-top',
        ],
      ]);
  }

  /**
   * Asserts various aspects of an ImageStyle entity.
   *
   * @param string $id
   *   The expected image style ID.
   * @param string $label
   *   The expected image style label.
   * @param array $expected_effect_plugins
   *   An array of expected plugins attached to the image style entity.
   * @param array $expected_effect_config
   *   An array of expected configuration for each effect in the image style.
   */
  protected function assertEntity(string $id, string $label, array $expected_effect_plugins, array $expected_effect_config): void {
    $style = ImageStyle::load($id);
    $this->assertInstanceOf(ImageStyleInterface::class, $style);
    /** @var \Drupal\image\ImageStyleInterface $style */
    $this->assertSame($id, $style->id());
    $this->assertSame($label, $style->label());

    // Check the number of effects associated with the style.
    $effects = $style->getEffects();
    $this->assertSameSize($expected_effect_plugins, $effects);

    $index = 0;
    foreach ($effects as $effect) {
      $this->assertInstanceOf(ImageEffectBase::class, $effect);
      $this->assertSame($expected_effect_plugins[$index], $effect->getPluginId());
      $config = $effect->getConfiguration();
      $this->assertSame($expected_effect_config[$index], $config['data']);
      $index++;
    }
  }

}
