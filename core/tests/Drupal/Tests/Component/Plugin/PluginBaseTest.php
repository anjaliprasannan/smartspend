<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Plugin;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\Component\Plugin\PluginBase
 * @group Plugin
 */
class PluginBaseTest extends TestCase {

  /**
   * @dataProvider providerTestGetPluginId
   * @covers ::getPluginId
   */
  public function testGetPluginId($plugin_id, $expected): void {
    $plugin_base = new StubPluginBase(
      [],
      $plugin_id,
      [],
    );

    $this->assertEquals($expected, $plugin_base->getPluginId());
  }

  /**
   * Returns test data for testGetPluginId().
   *
   * @return array
   *   An array of test cases, where each item contains a plugin ID and
   *   the expected plugin ID result.
   */
  public static function providerTestGetPluginId() {
    return [
      ['base_id', 'base_id'],
      ['base_id:derivative', 'base_id:derivative'],
    ];
  }

  /**
   * @dataProvider providerTestGetBaseId
   * @coves ::getBaseId
   */
  public function testGetBaseId($plugin_id, $expected): void {
    $plugin_base = new StubPluginBase(
      [],
      $plugin_id,
      [],
    );

    $this->assertEquals($expected, $plugin_base->getBaseId());
  }

  /**
   * Returns test data for testGetBaseId().
   *
   * @return array
   *   An array of test cases, where each item contains a plugin ID and
   *   the expected base ID result.
   */
  public static function providerTestGetBaseId() {
    return [
      ['base_id', 'base_id'],
      ['base_id:derivative', 'base_id'],
    ];
  }

  /**
   * @dataProvider providerTestGetDerivativeId
   * @covers ::getDerivativeId
   */
  public function testGetDerivativeId($plugin_id = NULL, $expected = NULL): void {
    $plugin_base = new StubPluginBase(
      [],
      $plugin_id,
      [],
    );

    $this->assertEquals($expected, $plugin_base->getDerivativeId());
  }

  /**
   * Returns test data for testGetDerivativeId().
   *
   * @return array
   *   An array of test cases, where each item contains a plugin ID and
   *   the expected derivative ID result.
   */
  public static function providerTestGetDerivativeId() {
    return [
      ['base_id', NULL],
      ['base_id:derivative', 'derivative'],
    ];
  }

  /**
   * @covers ::getPluginDefinition
   */
  public function testGetPluginDefinition(): void {
    $plugin_base = new StubPluginBase(
      [],
      'plugin_id',
      ['value', ['key' => 'value']],
    );

    $this->assertEquals(['value', ['key' => 'value']], $plugin_base->getPluginDefinition());
  }

}
