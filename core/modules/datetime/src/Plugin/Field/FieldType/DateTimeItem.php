<?php

namespace Drupal\datetime\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'datetime' field type.
 */
#[FieldType(
  id: "datetime",
  label: new TranslatableMarkup("Date"),
  description: [
    new TranslatableMarkup("Ideal when date and time needs to be input by users, like event dates and times"),
    new TranslatableMarkup("Date or date and time stored in a readable string format"),
    new TranslatableMarkup("Easy to read and understand for humans"),
  ],
  category: "date_time",
  default_widget: "datetime_default",
  default_formatter: "datetime_default",
  list_class: DateTimeFieldItemList::class,
  constraints: ["DateTimeFormat" => []]
)]
class DateTimeItem extends FieldItemBase implements DateTimeItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'datetime_type' => 'datetime',
    ] + parent::defaultStorageSettings();
  }

  /**
   * Value for the 'datetime_type' setting: store only a date.
   */
  const DATETIME_TYPE_DATE = 'date';

  /**
   * Value for the 'datetime_type' setting: store a date and time.
   */
  const DATETIME_TYPE_DATETIME = 'datetime';

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('datetime_iso8601')
      ->setLabel(new TranslatableMarkup('Date value'))
      ->setRequired(TRUE);

    $properties['date'] = DataDefinition::create('any')
      ->setLabel(new TranslatableMarkup('Computed date'))
      ->setDescription(new TranslatableMarkup('The computed DateTime object.'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\datetime\DateTimeComputed')
      ->setSetting('date source', 'value');

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'description' => 'The date value.',
          'type' => 'varchar',
          'length' => 20,
        ],
      ],
      'indexes' => [
        'value' => ['value'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = [];

    $element['datetime_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Date type'),
      '#description' => $this->t('Choose the type of date to create.'),
      '#default_value' => $this->getSetting('datetime_type'),
      '#options' => [
        static::DATETIME_TYPE_DATETIME => $this->t('Date and time'),
        static::DATETIME_TYPE_DATE => $this->t('Date only'),
      ],
      '#disabled' => $has_data,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $type = $field_definition->getSetting('datetime_type');

    // Just pick a date in the past year. No guidance is provided by this Field
    // type.
    $timestamp = \Drupal::time()->getRequestTime() - mt_rand(0, 86400 * 365);
    if ($type == DateTimeItem::DATETIME_TYPE_DATE) {
      $values['value'] = gmdate(static::DATE_STORAGE_FORMAT, $timestamp);
    }
    else {
      $values['value'] = gmdate(static::DATETIME_STORAGE_FORMAT, $timestamp);
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    // Enforce that the computed date is recalculated.
    if ($property_name == 'value') {
      $this->set('date', NULL);
    }
    parent::onChange($property_name, $notify);
  }

}
