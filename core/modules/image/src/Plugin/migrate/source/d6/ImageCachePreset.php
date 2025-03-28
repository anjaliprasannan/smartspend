<?php

namespace Drupal\image\Plugin\migrate\source\d6;

use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Drupal\migrate\Row;

// cspell:ignore imagecache presetid presetname

/**
 * Drupal 6 imagecache presets source from database.
 *
 * For available configuration keys, refer to the parent classes.
 *
 * @see \Drupal\migrate\Plugin\migrate\source\SqlBase
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 */
#[MigrateSource(
  id: 'd6_imagecache_presets',
  source_module: 'imagecache',
)]
class ImageCachePreset extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('imagecache_preset', 'icp')
      ->fields('icp');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'presetid' => $this->t('Preset ID'),
      'presetname' => $this->t('Preset Name'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['presetid']['type'] = 'integer';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $actions = [];

    $results = $this->select('imagecache_action', 'ica')
      ->fields('ica')
      ->condition('presetid', $row->getSourceProperty('presetid'))
      ->execute();

    foreach ($results as $key => $result) {
      $actions[$key] = $result;
      $actions[$key]['data'] = unserialize($result['data']);
    }

    $row->setSourceProperty('actions', $actions);
    return parent::prepareRow($row);
  }

}
