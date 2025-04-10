<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_drupal\Kernel;

use Drupal\Component\Discovery\YamlDiscovery;
use Drupal\KernelTests\FileSystemModuleDiscoveryDataProviderTrait;
use Drupal\migrate_drupal\MigrationConfigurationTrait;

/**
 * Tests that core modules have a migrate_drupal.yml file as needed.
 *
 * Checks that each module that requires a migrate_drupal.yml has the file.
 * Because more that one migrate_drupal.yml file may have the same entry the
 * ValidateMigrationStateTest, which validates the file contents, is not able
 * to determine that all the required files exits.
 *
 * @group migrate_drupal
 */
class StateFileExistsTest extends MigrateDrupalTestBase {

  use FileSystemModuleDiscoveryDataProviderTrait;
  use MigrationConfigurationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Test migrations states.
    'migrate_state_finished_test',
    'migrate_state_not_finished_test',
  ];

  /**
   * Modules that should have a migrate_drupal.yml file.
   *
   * @var array
   */
  protected $stateFileRequired = [
    // @todo Remove ban in https://www.drupal.org/project/drupal/issues/3488827
    'ban',
    'block',
    'block_content',
    'comment',
    'config_translation',
    'contact',
    'content_translation',
    'datetime',
    'dblog',
    'field',
    'file',
    'filter',
    'image',
    'language',
    'link',
    'locale',
    'menu_link_content',
    'migrate_state_finished_test',
    'migrate_state_not_finished_test',
    'menu_ui',
    'migrate_drupal',
    'node',
    'options',
    'path',
    'responsive_image',
    'search',
    'shortcut',
    'syslog',
    'system',
    'taxonomy',
    'telephone',
    'text',
    'update',
    'user',
  ];

  /**
   * Tests that the migrate_drupal.yml files exist as needed.
   */
  public function testMigrationState(): void {
    // Install all available modules.
    $module_handler = $this->container->get('module_handler');
    $all_modules = $this->coreModuleListDataProvider();
    $modules_enabled = $module_handler->getModuleList();
    $modules_to_enable = array_keys(array_diff_key($all_modules, $modules_enabled));
    $this->enableModules($modules_to_enable);
    // Note that the kernel has rebuilt the container in enableModules this
    // $module_handler is no longer the $module_handler instance from above.
    $module_handler = $this->container->get('module_handler');

    // Modules with a migrate_drupal.yml file.
    $has_state_file = (new YamlDiscovery('migrate_drupal', array_map(function ($value) {
      return $value . '/migrations/state';
    }, $module_handler->getModuleDirectories())))->findAll();

    foreach ($this->stateFileRequired as $module) {
      $this->assertArrayHasKey($module, $has_state_file, sprintf("Module '%s' should have a migrate_drupal.yml file", $module));
    }
    $this->assertSameSize($this->stateFileRequired, $has_state_file);
  }

}
