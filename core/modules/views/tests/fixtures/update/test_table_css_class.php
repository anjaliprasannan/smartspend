<?php

/**
 * @file
 * Test fixture.
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Serialization\Yaml;

$connection = Database::getConnection();

$connection->insert('config')
  ->fields([
    'collection' => '',
    'name' => 'views.view.test_table_css_class',
    'data' => serialize(Yaml::decode(file_get_contents('core/modules/views/tests/fixtures/update/views.view.test_table_css_class.yml'))),
  ])
  ->execute();
