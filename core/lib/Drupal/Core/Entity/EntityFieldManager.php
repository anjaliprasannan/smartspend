<?php

namespace Drupal\Core\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldDefinition;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PreWarm\PreWarmableInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\TypedDataManagerInterface;

/**
 * Manages the discovery of entity fields.
 *
 * This includes field definitions, base field definitions, and field storage
 * definitions.
 */
class EntityFieldManager implements EntityFieldManagerInterface, PreWarmableInterface {

  use UseCacheBackendTrait;
  use StringTranslationTrait;

  /**
   * Extra fields info, if initialized.
   *
   * The fields are keyed by entity type, bundle, type ('form' or 'display'),
   * and the extra field name.
   *
   * @var array[][][][]|null
   */
  protected ?array $extraFields = NULL;

  /**
   * Static cache of base field definitions.
   *
   * @var array
   */
  protected $baseFieldDefinitions;

  /**
   * Static cache of field definitions per bundle and entity type.
   *
   * @var array
   */
  protected $fieldDefinitions;

  /**
   * Static cache of field storage definitions per entity type.
   *
   * Elements of the array:
   *  - $entity_type_id: \Drupal\Core\Field\BaseFieldDefinition[]
   *
   * @var array
   */
  protected $fieldStorageDefinitions;

  /**
   * Static cache of active field storage definitions per entity type.
   *
   * @var array
   */
  protected $activeFieldStorageDefinitions;

  /**
   * An array of lightweight maps of fields, keyed by entity type.
   *
   * Each value is an array whose keys are field names and whose value is an
   * array with two entries:
   *   - type: The field type.
   *   - bundles: The bundles in which the field appears.
   *
   * @var array
   */
  protected $fieldMap = [];

  /**
   * An array of lightweight maps of fields, keyed by field type.
   *
   * Each value is an array whose key are entity types including arrays in the
   * same form as $fieldMap. It helps access the mapping between types and
   * fields by the field type.
   *
   * @var array
   */
  protected $fieldMapByFieldType = [];

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The key-value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValueFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The entity last installed schema repository.
   *
   * @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface
   */
  protected $entityLastInstalledSchemaRepository;

  /**
   * Constructs a new EntityFieldManager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
   *   The typed data manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key-value factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $entity_last_installed_schema_repository
   *   The entity last installed schema repository.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityDisplayRepositoryInterface $entity_display_repository, TypedDataManagerInterface $typed_data_manager, LanguageManagerInterface $language_manager, KeyValueFactoryInterface $key_value_factory, ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend, EntityLastInstalledSchemaRepositoryInterface $entity_last_installed_schema_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityDisplayRepository = $entity_display_repository;

    $this->typedDataManager = $typed_data_manager;
    $this->languageManager = $language_manager;
    $this->keyValueFactory = $key_value_factory;
    $this->moduleHandler = $module_handler;
    $this->cacheBackend = $cache_backend;
    $this->entityLastInstalledSchemaRepository = $entity_last_installed_schema_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFieldDefinitions($entity_type_id) {
    // Check the static cache.
    if (!isset($this->baseFieldDefinitions[$entity_type_id])) {
      // Not prepared, try to load from cache.
      $cid = 'entity_base_field_definitions:' . $entity_type_id . ':' . $this->languageManager->getCurrentLanguage()->getId();
      if ($cache = $this->cacheGet($cid)) {
        $this->baseFieldDefinitions[$entity_type_id] = $cache->data;
      }
      else {
        // Rebuild the definitions and put it into the cache.
        $this->baseFieldDefinitions[$entity_type_id] = $this->buildBaseFieldDefinitions($entity_type_id);
        $this->cacheSet($cid, $this->baseFieldDefinitions[$entity_type_id], Cache::PERMANENT, [
          'entity_types',
          'entity_field_info',
        ]);
      }
    }
    return $this->baseFieldDefinitions[$entity_type_id];
  }

  /**
   * Builds base field definitions for an entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID. Only entity types that implement
   *   \Drupal\Core\Entity\FieldableEntityInterface are supported.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of field definitions, keyed by field name.
   *
   * @throws \LogicException
   *   Thrown if a config entity type is given or if one of the entity keys is
   *   flagged as translatable.
   */
  protected function buildBaseFieldDefinitions($entity_type_id) {
    /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type */
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $class = $entity_type->getClass();
    /** @var string[] $keys */
    $keys = array_filter($entity_type->getKeys());

    // Fail with an exception for non-fieldable entity types.
    if (!$entity_type->entityClassImplements(FieldableEntityInterface::class)) {
      throw new \LogicException("Getting the base fields is not supported for entity type {$entity_type->getLabel()}.");
    }

    // Retrieve base field definitions.
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface[] $base_field_definitions */
    $base_field_definitions = $class::baseFieldDefinitions($entity_type);

    // Make sure translatable entity types are correctly defined.
    if ($entity_type->isTranslatable()) {
      // The langcode field should always be translatable if the entity type is.
      if (isset($keys['langcode']) && isset($base_field_definitions[$keys['langcode']])) {
        $base_field_definitions[$keys['langcode']]->setTranslatable(TRUE);
      }
      // A default_langcode field should always be defined.
      if (!isset($base_field_definitions[$keys['default_langcode']])) {
        $base_field_definitions[$keys['default_langcode']] = BaseFieldDefinition::create('boolean')
          ->setLabel($this->t('Default translation'))
          ->setDescription($this->t('A flag indicating whether this is the default translation.'))
          ->setTranslatable(TRUE)
          ->setRevisionable(TRUE)
          ->setDefaultValue(TRUE);
      }
    }

    // Make sure that revisionable entity types are correctly defined.
    if ($entity_type->isRevisionable()) {
      $field_name = $entity_type->getRevisionMetadataKey('revision_default');
      $base_field_definitions[$field_name] = BaseFieldDefinition::create('boolean')
        ->setLabel($this->t('Default revision'))
        ->setDescription($this->t('A flag indicating whether this was a default revision when it was saved.'))
        ->setStorageRequired(TRUE)
        ->setInternal(TRUE)
        ->setTranslatable(FALSE)
        ->setRevisionable(TRUE);
    }

    // Make sure that revisionable and translatable entity types are correctly
    // defined.
    if ($entity_type->isRevisionable() && $entity_type->isTranslatable()) {
      // The 'revision_translation_affected' field should always be defined.
      // This field has been added unconditionally in Drupal 8.4.0 and it is
      // overriding any pre-existing definition on purpose so that any
      // differences are immediately available in the status report.
      $base_field_definitions[$keys['revision_translation_affected']] = BaseFieldDefinition::create('boolean')
        ->setLabel($this->t('Revision translation affected'))
        ->setDescription($this->t('Indicates if the last edit of a translation belongs to current revision.'))
        ->setReadOnly(TRUE)
        ->setRevisionable(TRUE)
        ->setTranslatable(TRUE);
    }

    // Assign base field definitions the entity type provider.
    $provider = $entity_type->getProvider();
    foreach ($base_field_definitions as $definition) {
      // @todo Remove this check once FieldDefinitionInterface exposes a proper
      //   provider setter. See https://www.drupal.org/node/2346329.
      if ($definition instanceof BaseFieldDefinition) {
        $definition->setProvider($provider);
      }
    }

    // Retrieve base field definitions from modules.
    $this->moduleHandler->invokeAllWith(
      'entity_base_field_info',
      function (callable $hook, string $module) use (&$base_field_definitions, $entity_type) {
        $module_definitions = $hook($entity_type) ?? [];
        // Ensure the provider key actually matches the name of the provider
        // defining the field.
        foreach ($module_definitions as $field_name => $definition) {
          // @todo Remove this check once FieldDefinitionInterface exposes a
          //   proper provider setter. See https://www.drupal.org/node/2346329.
          if ($definition instanceof BaseFieldDefinition && $definition->getProvider() == NULL) {
            $definition->setProvider($module);
          }
          $base_field_definitions[$field_name] = $definition;
        }
      }
    );

    // Automatically set the field name, target entity type and bundle
    // for non-configurable fields.
    foreach ($base_field_definitions as $field_name => $base_field_definition) {
      if ($base_field_definition instanceof BaseFieldDefinition) {
        $base_field_definition->setName($field_name);
        $base_field_definition->setTargetEntityTypeId($entity_type_id);
        $base_field_definition->setTargetBundle(NULL);
      }
    }

    // Invoke alter hook.
    $this->moduleHandler->alter('entity_base_field_info', $base_field_definitions, $entity_type);

    // Ensure defined entity keys are there and have proper revisionable and
    // translatable values.
    foreach (array_intersect_key($keys, array_flip(['id', 'revision', 'uuid', 'bundle'])) as $key => $field_name) {
      if (!isset($base_field_definitions[$field_name])) {
        throw new \LogicException("The $field_name field definition does not exist and it is used as $key entity key.");
      }
      if ($base_field_definitions[$field_name]->isRevisionable()) {
        throw new \LogicException("The {$base_field_definitions[$field_name]->getLabel()} field cannot be revisionable as it is used as $key entity key.");
      }
      if ($base_field_definitions[$field_name]->isTranslatable()) {
        throw new \LogicException("The {$base_field_definitions[$field_name]->getLabel()} field cannot be translatable as it is used as $key entity key.");
      }
    }

    // Make sure translatable entity types define the "langcode" field properly.
    if ($entity_type->isTranslatable() && (!isset($keys['langcode']) || !isset($base_field_definitions[$keys['langcode']]) || !$base_field_definitions[$keys['langcode']]->isTranslatable())) {
      throw new \LogicException("The {$entity_type->getLabel()} entity type cannot be translatable as it does not define a translatable \"langcode\" field.");
    }

    return $base_field_definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitions($entity_type_id, $bundle) {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    if (!isset($this->fieldDefinitions[$entity_type_id][$bundle][$langcode])) {
      $base_field_definitions = $this->getBaseFieldDefinitions($entity_type_id);
      // Not prepared, try to load from cache.
      $cid = 'entity_bundle_field_definitions:' . $entity_type_id . ':' . $bundle . ':' . $langcode;
      if ($cache = $this->cacheGet($cid)) {
        $bundle_field_definitions = $cache->data;
      }
      else {
        // Rebuild the definitions and put it into the cache.
        $bundle_field_definitions = $this->buildBundleFieldDefinitions($entity_type_id, $bundle, $base_field_definitions);
        $this->cacheSet($cid, $bundle_field_definitions, Cache::PERMANENT, ['entity_types', 'entity_field_info']);
      }
      // Field definitions consist of the bundle specific overrides and the
      // base fields, merge them together. Use array_replace() to replace base
      // fields with by bundle overrides and keep them in order, append
      // additional by bundle fields.
      $this->fieldDefinitions[$entity_type_id][$bundle][$langcode] = array_replace($base_field_definitions, $bundle_field_definitions);
    }
    return $this->fieldDefinitions[$entity_type_id][$bundle][$langcode];
  }

  /**
   * Builds field definitions for a specific bundle within an entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID. Only entity types that implement
   *   \Drupal\Core\Entity\FieldableEntityInterface are supported.
   * @param string $bundle
   *   The bundle.
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $base_field_definitions
   *   The list of base field definitions.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of bundle field definitions, keyed by field name. Does
   *   not include base fields.
   */
  protected function buildBundleFieldDefinitions($entity_type_id, $bundle, array $base_field_definitions) {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

    // Use a bundle specific class if one is defined.
    $class = $this->entityTypeManager->getStorage($entity_type_id)->getEntityClass($bundle);

    // Allow the entity class to provide bundle fields and bundle-specific
    // overrides of base fields.
    $bundle_field_definitions = $class::bundleFieldDefinitions($entity_type, $bundle, $base_field_definitions);

    // Load base field overrides from configuration. These take precedence over
    // base field overrides returned above.
    $base_field_override_ids = array_map(function ($field_name) use ($entity_type_id, $bundle) {
      return $entity_type_id . '.' . $bundle . '.' . $field_name;
    }, array_keys($base_field_definitions));
    $base_field_overrides = $this->entityTypeManager->getStorage('base_field_override')->loadMultiple($base_field_override_ids);
    foreach ($base_field_overrides as $base_field_override) {
      /** @var \Drupal\Core\Field\Entity\BaseFieldOverride $base_field_override */
      $field_name = $base_field_override->getName();
      $bundle_field_definitions[$field_name] = $base_field_override;
    }

    $provider = $entity_type->getProvider();
    foreach ($bundle_field_definitions as $definition) {
      // @todo Remove this check once FieldDefinitionInterface exposes a proper
      //   provider setter. See https://www.drupal.org/node/2346329.
      if ($definition instanceof BaseFieldDefinition) {
        $definition->setProvider($provider);
      }
    }

    // Retrieve bundle field definitions from modules.
    $this->moduleHandler->invokeAllWith(
      'entity_bundle_field_info',
      function (callable $hook, string $module) use (&$bundle_field_definitions, $entity_type, $bundle, $base_field_definitions) {
        $module_definitions = $hook($entity_type, $bundle, $base_field_definitions) ?? [];
        // Ensure the provider key actually matches the name of the provider
        // defining the field.
        foreach ($module_definitions as $field_name => $definition) {
          // @todo Remove this check once FieldDefinitionInterface exposes a
          //   proper provider setter. See https://www.drupal.org/node/2346329.
          if ($definition instanceof BaseFieldDefinition) {
            $definition->setProvider($module);
          }
          $bundle_field_definitions[$field_name] = $definition;
        }
      }
    );

    // Automatically set the field name, target entity type and bundle
    // for non-configurable fields.
    foreach ($bundle_field_definitions as $field_name => $field_definition) {
      if ($field_definition instanceof BaseFieldDefinition) {
        $field_definition->setName($field_name);
        $field_definition->setTargetEntityTypeId($entity_type_id);
      }
      if ($field_definition instanceof BaseFieldDefinition || $field_definition instanceof FieldDefinition) {
        $field_definition->setTargetBundle($bundle);
      }
    }

    // Invoke 'per bundle' alter hook.
    $this->moduleHandler->alter('entity_bundle_field_info', $bundle_field_definitions, $entity_type, $bundle);

    return $bundle_field_definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldStorageDefinitions($entity_type_id) {
    if (!isset($this->fieldStorageDefinitions[$entity_type_id])) {
      $this->fieldStorageDefinitions[$entity_type_id] = [];
      // Add all non-computed base fields.
      foreach ($this->getBaseFieldDefinitions($entity_type_id) as $field_name => $definition) {
        if (!$definition->isComputed()) {
          $this->fieldStorageDefinitions[$entity_type_id][$field_name] = $definition;
        }
      }
      // Not prepared, try to load from cache.
      $cid = 'entity_field_storage_definitions:' . $entity_type_id . ':' . $this->languageManager->getCurrentLanguage()->getId();
      if ($cache = $this->cacheGet($cid)) {
        $field_storage_definitions = $cache->data;
      }
      else {
        // Rebuild the definitions and put it into the cache.
        $field_storage_definitions = $this->buildFieldStorageDefinitions($entity_type_id);
        $this->cacheSet($cid, $field_storage_definitions, Cache::PERMANENT, ['entity_types', 'entity_field_info']);
      }
      $this->fieldStorageDefinitions[$entity_type_id] += $field_storage_definitions;
    }
    return $this->fieldStorageDefinitions[$entity_type_id];
  }

  /**
   * Gets the active field storage definitions for a content entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID. Only content entities are supported.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface[]
   *   An array of field storage definitions that are active in the current
   *   request, keyed by field name.
   *
   * @internal
   */
  public function getActiveFieldStorageDefinitions($entity_type_id) {
    if (!isset($this->activeFieldStorageDefinitions[$entity_type_id])) {
      $this->activeFieldStorageDefinitions[$entity_type_id] = $this->entityLastInstalledSchemaRepository->getLastInstalledFieldStorageDefinitions($entity_type_id);
    }
    return $this->activeFieldStorageDefinitions[$entity_type_id] ?: $this->getFieldStorageDefinitions($entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldMap(array $field_map) {
    $this->fieldMap = $field_map;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMap() {
    if (!$this->fieldMap) {
      // Not prepared, try to load from cache.
      $cid = 'entity_field_map';
      if ($cache = $this->cacheGet($cid)) {
        $this->fieldMap = $cache->data;
      }
      else {
        // The field map is built in two steps. First, add all base fields, by
        // looping over all fieldable entity types. They always exist for all
        // bundles, and we do not expect to have so many different entity
        // types for this to become a bottleneck.
        foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
          if ($entity_type->entityClassImplements(FieldableEntityInterface::class)) {
            $bundles = array_keys($this->entityTypeBundleInfo->getBundleInfo($entity_type_id));
            foreach ($this->getBaseFieldDefinitions($entity_type_id) as $field_name => $base_field_definition) {
              $this->fieldMap[$entity_type_id][$field_name] = [
                'type' => $base_field_definition->getType(),
                'bundles' => array_combine($bundles, $bundles),
              ];
            }
          }
        }

        // In the second step, the per-bundle fields are added, based on the
        // persistent bundle field map stored in a key value collection. This
        // data is managed in the
        // FieldDefinitionListener::onFieldDefinitionCreate() and
        // FieldDefinitionListener::onFieldDefinitionDelete() methods.
        // Rebuilding this information in the same way as base fields would not
        // scale, as the time to query would grow exponentially with more fields
        // and bundles. A cache would be deleted during cache clears, which is
        // the only time it is needed, so a key value collection is used.
        $bundle_field_maps = $this->keyValueFactory->get('entity.definitions.bundle_field_map')->getAll();
        foreach ($bundle_field_maps as $entity_type_id => $bundle_field_map) {
          foreach ($bundle_field_map as $field_name => $map_entry) {
            if (!isset($this->fieldMap[$entity_type_id][$field_name])) {
              $this->fieldMap[$entity_type_id][$field_name] = $map_entry;
            }
            else {
              $this->fieldMap[$entity_type_id][$field_name]['bundles'] += $map_entry['bundles'];
            }
          }
        }

        $this->cacheSet($cid, $this->fieldMap, Cache::PERMANENT, ['entity_types', 'entity_field_info']);
      }
    }
    return $this->fieldMap;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMapByFieldType($field_type) {
    if (!isset($this->fieldMapByFieldType[$field_type])) {
      $filtered_map = [];
      $map = $this->getFieldMap();
      foreach ($map as $entity_type => $fields) {
        foreach ($fields as $field_name => $field_info) {
          if ($field_info['type'] == $field_type) {
            $filtered_map[$entity_type][$field_name] = $field_info;
          }
        }
      }
      $this->fieldMapByFieldType[$field_type] = $filtered_map;
    }
    return $this->fieldMapByFieldType[$field_type];
  }

  /**
   * Builds field storage definitions for an entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID. Only entity types that implement
   *   \Drupal\Core\Entity\FieldableEntityInterface are supported.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface[]
   *   An array of field storage definitions, keyed by field name.
   */
  protected function buildFieldStorageDefinitions($entity_type_id) {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $field_definitions = [];

    // Retrieve base field definitions from modules.
    $this->moduleHandler->invokeAllWith(
      'entity_field_storage_info',
      function (callable $hook, string $module) use (&$field_definitions, $entity_type, $entity_type_id) {
        $module_definitions = $hook($entity_type) ?? [];
        // Ensure the provider key actually matches the name of the provider
        // defining the field.
        foreach ($module_definitions as $field_name => $definition) {
          // @todo Remove this check once FieldDefinitionInterface exposes a
          //   proper provider setter. See https://www.drupal.org/node/2346329.
          if ($definition instanceof BaseFieldDefinition) {
            $definition->setProvider($module);
            $definition->setName($field_name);
            $definition->setTargetEntityTypeId($entity_type_id);
          }
          $field_definitions[$field_name] = $definition;
        }
      }
    );

    // Invoke alter hook.
    $this->moduleHandler->alter('entity_field_storage_info', $field_definitions, $entity_type);

    return $field_definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedFieldDefinitions() {
    $this->baseFieldDefinitions = [];
    $this->fieldDefinitions = [];
    $this->fieldStorageDefinitions = [];
    $this->activeFieldStorageDefinitions = [];
    $this->fieldMap = [];
    $this->fieldMapByFieldType = [];
    $this->entityDisplayRepository->clearDisplayModeInfo();
    $this->extraFields = NULL;
    Cache::invalidateTags(['entity_field_info']);
    // The typed data manager statically caches prototype objects with injected
    // definitions, clear those as well.
    $this->typedDataManager->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function useCaches($use_caches = FALSE) {
    $this->useCaches = $use_caches;
    if (!$use_caches) {
      $this->fieldDefinitions = [];
      $this->baseFieldDefinitions = [];
      $this->fieldStorageDefinitions = [];
      $this->activeFieldStorageDefinitions = [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getExtraFields($entity_type_id, $bundle) {
    $this->extraFields ??= $this->loadExtraFields();

    // Read from the "static" cache.
    // Return an empty fallback if the bundle has no extra fields.
    return $this->extraFields[$entity_type_id][$bundle] ?? [
      'form' => [],
      'display' => [],
    ];
  }

  /**
   * Loads extra fields from cache, or rebuilds them.
   *
   * @return array[][][][]
   *   Extra fields by entity type, bundle name, type (form/display) and
   *   extra field name.
   */
  protected function loadExtraFields(): array {
    // Read from the persistent cache. Since hook_entity_extra_field_info() and
    // hook_entity_extra_field_info_alter() might contain t() calls, we cache
    // per language.
    $cache_id = 'entity_extra_field_info:' . $this->languageManager->getCurrentLanguage()->getId();
    $cached = $this->cacheGet($cache_id);
    if ($cached) {
      return $cached->data;
    }

    $extra = $this->moduleHandler->invokeAll('entity_extra_field_info');
    $this->moduleHandler->alter('entity_extra_field_info', $extra);

    // Apply default values to each bundle.
    foreach ($extra as $entity_type_id => $extra_fields_by_bundle) {
      foreach ($extra_fields_by_bundle as $bundle => $bundle_extra_fields) {
        $extra[$entity_type_id][$bundle] += [
          'form' => [],
          'display' => [],
        ];
      }
    }

    $this->cacheSet($cache_id, $extra, Cache::PERMANENT, [
      'entity_field_info',
    ]);

    return $extra;
  }

  /**
   * {@inheritdoc}
   */
  public function preWarm(): void {
    $this->getFieldMap();
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldLabels(string $entity_type, string $field_name): array {
    $label_counter = [];
    $all_labels = [];
    // Count the number of fields per label per field storage.
    foreach (array_keys($this->entityTypeBundleInfo->getBundleInfo($entity_type)) as $bundle) {
      $bundle_fields = array_filter($this->getFieldDefinitions($entity_type, $bundle), function ($field_definition) {
        return $field_definition instanceof FieldConfigInterface;
      });
      if (isset($bundle_fields[$field_name])) {
        $field = $bundle_fields[$field_name];
        $label = $field->getLabel();
        $label_counter[$label] = isset($label_counter[$label]) ? ++$label_counter[$label] : 1;
        $all_labels[$label] = TRUE;
      }
    }
    if (empty($label_counter)) {
      return [$field_name, $all_labels];
    }
    // Sort the field labels by the most used label and return the most used
    // one. If the counts are equal, sort by the label to ensure the result is
    // deterministic.
    uksort($label_counter, function ($a, $b) use ($label_counter) {
      if ($label_counter[$a] === $label_counter[$b]) {
        return strcmp($a, $b);
      }
      return $label_counter[$b] <=> $label_counter[$a];
    });
    $label_counter = array_keys($label_counter);

    return [$label_counter[0], $all_labels];
  }

}
