<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\EntityTestHelper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Delete field storages and fields during config delete method invocation.
 *
 * @group field
 */
class FieldImportDeleteTest extends FieldKernelTestBase {

  /**
   * Modules to install.
   *
   * The default configuration provided by field_test_config is imported by
   * \Drupal\Tests\field\Kernel\FieldKernelTestBase::setUp() when it installs
   * field configuration.
   *
   * @var array
   */
  protected static $modules = ['field_test_config'];

  /**
   * Tests deleting field storages and fields as part of config import.
   */
  public function testImportDelete(): void {
    EntityTestHelper::createBundle('test_bundle');

    $this->installConfig(['field_test_config']);
    // At this point there are 5 field configuration objects in the active
    // storage.
    // - field.storage.entity_test.field_test_import
    // - field.storage.entity_test.field_test_import_2
    // - field.field.entity_test.entity_test.field_test_import
    // - field.field.entity_test.entity_test.field_test_import_2
    // - field.field.entity_test.test_bundle.field_test_import_2

    $field_name = 'field_test_import';
    $field_storage_id = "entity_test.$field_name";
    $field_name_2 = 'field_test_import_2';
    $field_storage_id_2 = "entity_test.$field_name_2";
    $field_id = "entity_test.entity_test.$field_name";
    $field_id_2a = "entity_test.entity_test.$field_name_2";
    $field_id_2b = "entity_test.test_bundle.$field_name_2";
    $field_storage_config_name = "field.storage.$field_storage_id";
    $field_storage_config_name_2 = "field.storage.$field_storage_id_2";
    $field_config_name = "field.field.$field_id";
    $field_config_name_2a = "field.field.$field_id_2a";
    $field_config_name_2b = "field.field.$field_id_2b";

    // Create an entity with data in the first field to make sure that field
    // needs to be purged.
    $entity_test = EntityTest::create([
      'type' => 'entity_test',
    ]);
    $entity_test->set($field_name, 'test data');
    $entity_test->save();

    // Create a second bundle for the 'Entity test' entity type.
    EntityTestHelper::createBundle('test_bundle');

    // Get the uuid's for the field storages.
    $field_storage_uuid = FieldStorageConfig::load($field_storage_id)->uuid();
    $field_storage_uuid_2 = FieldStorageConfig::load($field_storage_id_2)->uuid();

    $active = $this->container->get('config.storage');
    $sync = $this->container->get('config.storage.sync');
    $this->copyConfig($active, $sync);
    $this->assertTrue($sync->delete($field_storage_config_name), "Deleted field storage: $field_storage_config_name");
    $this->assertTrue($sync->delete($field_storage_config_name_2), "Deleted field storage: $field_storage_config_name_2");
    $this->assertTrue($sync->delete($field_config_name), "Deleted field: $field_config_name");
    $this->assertTrue($sync->delete($field_config_name_2a), "Deleted field: $field_config_name_2a");
    $this->assertTrue($sync->delete($field_config_name_2b), "Deleted field: $field_config_name_2b");

    $deletes = $this->configImporter()->getUnprocessedConfiguration('delete');
    $this->assertCount(5, $deletes, 'Importing configuration will delete 3 fields and 2 field storages.');

    // Import the content of the sync directory.
    $this->configImporter()->import();

    // Check that the field storages and fields are gone.
    \Drupal::entityTypeManager()->getStorage('field_storage_config')->resetCache([$field_storage_id]);
    $field_storage = FieldStorageConfig::load($field_storage_id);
    $this->assertNull($field_storage, 'The field storage was deleted.');
    \Drupal::entityTypeManager()->getStorage('field_storage_config')->resetCache([$field_storage_id_2]);
    $field_storage_2 = FieldStorageConfig::load($field_storage_id_2);
    $this->assertNull($field_storage_2, 'The second field storage was deleted.');
    \Drupal::entityTypeManager()->getStorage('field_config')->resetCache([$field_id]);
    $field = FieldConfig::load($field_id);
    $this->assertNull($field, 'The field was deleted.');
    \Drupal::entityTypeManager()->getStorage('field_config')->resetCache([$field_id_2a]);
    $field_2a = FieldConfig::load($field_id_2a);
    $this->assertNull($field_2a, 'The second field on test bundle was deleted.');
    \Drupal::entityTypeManager()->getStorage('field_config')->resetCache([$field_id_2b]);
    $field_2b = FieldConfig::load($field_id_2b);
    $this->assertNull($field_2b, 'The second field on test bundle 2 was deleted.');

    // Check that all config files are gone.
    $active = $this->container->get('config.storage');
    $this->assertSame([], $active->listAll($field_storage_config_name));
    $this->assertSame([], $active->listAll($field_storage_config_name_2));
    $this->assertSame([], $active->listAll($field_config_name));
    $this->assertSame([], $active->listAll($field_config_name_2a));
    $this->assertSame([], $active->listAll($field_config_name_2b));

    // Check that only the first storage definition is preserved in state.
    $deleted_storages = \Drupal::state()->get('field.storage.deleted', []);
    $this->assertTrue(isset($deleted_storages[$field_storage_uuid]));
    $this->assertFalse(isset($deleted_storages[$field_storage_uuid_2]));

    // Purge field data, and check that the storage definition has been
    // completely removed once the data is purged.
    field_purge_batch(10);
    $deleted_storages = \Drupal::state()->get('field.storage.deleted', []);
    $this->assertEmpty($deleted_storages, 'Fields are deleted');
  }

}
