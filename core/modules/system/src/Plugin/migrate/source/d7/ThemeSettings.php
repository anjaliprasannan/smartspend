<?php

namespace Drupal\system\Plugin\migrate\source\d7;

use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate_drupal\Plugin\migrate\source\VariableMultiRow;

/**
 * Drupal 7 theme settings source from database.
 *
 * For available configuration keys, refer to the parent classes.
 *
 * @see \Drupal\migrate_drupal\Plugin\migrate\source\VariableMultiRow
 * @see \Drupal\migrate\Plugin\migrate\source\SqlBase
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 */
#[MigrateSource(
  id: 'd7_theme_settings',
  source_module: 'system',
)]
class ThemeSettings extends VariableMultiRow {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('variable', 'v')
      ->fields('v', ['name', 'value'])
      ->condition('name', 'theme_%_settings', 'LIKE');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'name' => $this->t('Theme settings variable for a theme.'),
      'value' => $this->t('The theme settings variable value.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['name']['type'] = 'string';
    return $ids;
  }

}
