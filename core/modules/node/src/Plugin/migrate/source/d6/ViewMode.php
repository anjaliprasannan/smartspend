<?php

namespace Drupal\node\Plugin\migrate\source\d6;

use Drupal\migrate\Attribute\MigrateSource;

// cspell:ignore cnfi

/**
 * Drupal 6 view mode source from database.
 *
 * For available configuration keys, refer to the parent classes.
 *
 * @see \Drupal\migrate\Plugin\migrate\source\SqlBase
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 */
#[MigrateSource(
  id: 'd6_view_mode',
  source_module: 'content',
)]
class ViewMode extends ViewModeBase {

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    $rows = [];
    $result = $this->prepareQuery()->execute();
    while ($field_row = $result->fetchAssoc()) {
      $field_row['display_settings'] = unserialize($field_row['display_settings']);
      foreach ($this->getViewModes() as $view_mode) {
        // Append to the return value if the row has display settings for this
        // view mode and the view mode is neither hidden nor excluded.
        // @see \Drupal\field\Plugin\migrate\source\d6\FieldInstancePerViewMode::initializeIterator()
        if (isset($field_row['display_settings'][$view_mode]) && $field_row['display_settings'][$view_mode]['format'] != 'hidden' && empty($field_row['display_settings'][$view_mode]['exclude'])) {
          if (!isset($rows[$view_mode])) {
            $rows[$view_mode]['entity_type'] = 'node';
            $rows[$view_mode]['view_mode'] = $view_mode;
          }
        }
      }
    }

    return new \ArrayIterator($rows);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('content_node_field_instance', 'cnfi')
      ->fields('cnfi', [
        'display_settings',
      ]);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'display_settings' => $this->t('Serialize data with display settings.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['view_mode']['type'] = 'string';
    return $ids;
  }

}
