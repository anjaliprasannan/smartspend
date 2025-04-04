<?php

namespace Drupal\user\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Determines the settings for the profile field.
 */
#[MigrateProcess('profile_field_settings')]
class ProfileFieldSettings extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($type, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $settings = [];
    switch ($type) {
      case 'date':
        $settings['datetime_type'] = 'date';
        break;
    }
    return $settings;
  }

}
