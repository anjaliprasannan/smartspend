<?php

namespace Drupal\file\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\File\Exception\FileException;
use Drupal\file\FileAccessControlHandler;
use Drupal\file\FileInterface;
use Drupal\file\FileStorage;
use Drupal\file\FileStorageSchema;
use Drupal\file\FileViewsData;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the file entity class.
 *
 * @ingroup file
 */
#[ContentEntityType(
  id: 'file',
  label: new TranslatableMarkup('File'),
  label_collection: new TranslatableMarkup('Files'),
  label_singular: new TranslatableMarkup('file'),
  label_plural: new TranslatableMarkup('files'),
  entity_keys: [
    'id' => 'fid',
    'label' => 'filename',
    'langcode' => 'langcode',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  handlers: [
    'storage' => FileStorage::class,
    'storage_schema' => FileStorageSchema::class,
    'access' => FileAccessControlHandler::class,
    'views_data' => FileViewsData::class,
    'list_builder' => EntityListBuilder::class,
    'form' => ['delete' => ContentEntityDeleteForm::class],
    'route_provider' => ['html' => FileRouteProvider::class],
  ],
  links: [
    'delete-form' => '/file/{file}/delete',
  ],
  base_table: 'file_managed',
  label_count: [
    'singular' => '@count file',
    'plural' => '@count files',
  ],
)]
class File extends ContentEntityBase implements FileInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function getFilename() {
    return $this->get('filename')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setFilename($filename) {
    $this->get('filename')->value = $filename;
  }

  /**
   * {@inheritdoc}
   */
  public function getFileUri() {
    return $this->get('uri')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setFileUri($uri) {
    $this->get('uri')->value = $uri;
  }

  /**
   * {@inheritdoc}
   */
  public function createFileUrl($relative = TRUE) {
    /** @var \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator */
    $file_url_generator = \Drupal::service('file_url_generator');
    return $relative ? $file_url_generator->generateString($this->getFileUri()) : $file_url_generator->generateAbsoluteString($this->getFileUri());
  }

  /**
   * {@inheritdoc}
   */
  public function getMimeType() {
    return $this->get('filemime')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMimeType($mime) {
    $this->get('filemime')->value = $mime;
  }

  /**
   * {@inheritdoc}
   */
  public function getSize() {
    $filesize = $this->get('filesize')->value;
    return isset($filesize) ? (int) $filesize : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setSize($size) {
    $this->get('filesize')->value = $size;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    $created = $this->get('created')->value;
    return isset($created) ? (int) $created : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isPermanent() {
    return $this->get('status')->value == static::STATUS_PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function isTemporary() {
    return $this->get('status')->value == 0;
  }

  /**
   * {@inheritdoc}
   */
  public function setPermanent() {
    $this->get('status')->value = static::STATUS_PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function setTemporary() {
    $this->get('status')->value = 0;
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    // Automatically detect filename if not set.
    if (!isset($values['filename']) && isset($values['uri'])) {
      $values['filename'] = \Drupal::service('file_system')->basename($values['uri']);
    }

    // Automatically detect filemime if not set.
    if (!isset($values['filemime']) && isset($values['uri'])) {
      $values['filemime'] = \Drupal::service('file.mime_type.guesser')->guessMimeType($values['uri']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // The file itself might not exist or be available right now.
    $uri = $this->getFileUri();
    $size = @filesize($uri);

    // Set size unless there was an error.
    if ($size !== FALSE) {
      $this->setSize($size);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    foreach ($entities as $entity) {
      // Delete all remaining references to this file.
      $file_usage = \Drupal::service('file.usage')->listUsage($entity);
      if (!empty($file_usage)) {
        foreach ($file_usage as $module => $usage) {
          \Drupal::service('file.usage')->delete($entity, $module);
        }
      }
      // Delete the actual file. Failures due to invalid files and files that
      // were already deleted are logged to watchdog but ignored, the
      // corresponding file entity will be deleted.
      try {
        \Drupal::service('file_system')->delete($entity->getFileUri());
      }
      catch (FileException) {
        // Ignore and continue.
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['fid']->setLabel(t('File ID'))
      ->setDescription(t('The file ID.'));

    $fields['uuid']->setDescription(t('The file UUID.'));

    $fields['langcode']->setLabel(t('Language code'))
      ->setDescription(t('The file language code.'));

    $fields['uid']
      ->setDescription(t('The user ID of the file.'));

    $fields['filename'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Filename'))
      ->setDescription(t('Name of the file with no path components.'));

    $fields['uri'] = BaseFieldDefinition::create('file_uri')
      ->setLabel(t('URI'))
      ->setDescription(t('The URI to access the file (either local or remote).'))
      ->setSetting('max_length', 255)
      ->setSetting('case_sensitive', TRUE)
      ->addConstraint('FileUriUnique');

    $fields['filemime'] = BaseFieldDefinition::create('string')
      ->setLabel(t('File MIME type'))
      ->setSetting('is_ascii', TRUE)
      ->setDescription(t("The file's MIME type."));

    $fields['filesize'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('File size'))
      ->setDescription(t('The size of the file in bytes.'))
      ->setSetting('unsigned', TRUE)
      ->setSetting('size', 'big');

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('The status of the file, temporary (FALSE) and permanent (TRUE).'))
      ->setDefaultValue(FALSE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The timestamp that the file was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The timestamp that the file was last changed.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultEntityOwner() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function invalidateTagsOnSave($update) {
    $tags = $this->getListCacheTagsToInvalidate();
    // Always invalidate the 404 or 403 response cache because while files do
    // not have a canonical URL as such, they may be served via routes such as
    // private files.
    // Creating or updating an entity may change a cached 403 or 404 response.
    $tags = Cache::mergeTags($tags, ['4xx-response']);
    if ($update) {
      $tags = Cache::mergeTags($tags, $this->getCacheTagsToInvalidate());
    }
    Cache::invalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadHeaders(): array {
    return [
      'Content-Type' => $this->getMimeType(),
      'Content-Length' => $this->getSize(),
      'Cache-Control' => 'private',
    ];
  }

}
