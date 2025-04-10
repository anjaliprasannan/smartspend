<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate\Unit\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\process\Substr;

// cspell:ignore aptain Janeway

/**
 * Tests the substr plugin.
 *
 * @coversDefaultClass \Drupal\migrate\Plugin\migrate\process\Substr
 *
 * @group migrate
 */
class SubstrTest extends MigrateProcessTestCase {

  /**
   * Tests Substr plugin based on providerTestSubstr() values.
   *
   * @dataProvider providerTestSubstr
   */
  public function testSubstr($start = NULL, $length = NULL, $expected = NULL): void {
    $configuration['start'] = $start;
    $configuration['length'] = $length;
    $this->plugin = new Substr($configuration, 'map', []);
    $value = $this->plugin->transform('Captain Janeway', $this->migrateExecutable, $this->row, 'destination_property');
    $this->assertSame($expected, $value);
  }

  /**
   * Data provider for testSubstr().
   */
  public static function providerTestSubstr() {
    return [
      // Tests with valid start and length values.
      [0, 7, 'Captain'],
      // Tests with valid start > 0 and valid length.
      [6, 3, 'n J'],
      // Tests with valid start < 0 and valid length.
      [-7, 4, 'Jane'],
      // Tests without start value and valid length value.
      [NULL, 7, 'Captain'],
      // Tests with valid start value and no length value.
      [1, NULL, 'aptain Janeway'],
      // Tests without both start and length values.
      [NULL, NULL, 'Captain Janeway'],
    ];
  }

  /**
   * Tests invalid input type.
   */
  public function testSubstrFail(): void {
    $configuration = [];
    $this->plugin = new Substr($configuration, 'map', []);
    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage('The input value must be a string.');
    $this->plugin->transform(['Captain Janeway'], $this->migrateExecutable, $this->row, 'destination_property');
  }

  /**
   * Tests that the start parameter is an integer.
   */
  public function testStartIsString(): void {
    $configuration['start'] = '2';
    $this->plugin = new Substr($configuration, 'map', []);
    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage('The start position configuration value should be an integer. Omit this key to capture from the beginning of the string.');
    $this->plugin->transform(['foo'], $this->migrateExecutable, $this->row, 'destination_property');
  }

  /**
   * Tests that the length parameter is an integer.
   */
  public function testLengthIsString(): void {
    $configuration['length'] = '1';
    $this->plugin = new Substr($configuration, 'map', []);
    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage('The character length configuration value should be an integer. Omit this key to capture from the start position to the end of the string.');
    $this->plugin->transform(['foo'], $this->migrateExecutable, $this->row, 'destination_property');
  }

}
