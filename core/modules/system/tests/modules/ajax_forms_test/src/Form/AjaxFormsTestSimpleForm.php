<?php

declare(strict_types=1);

namespace Drupal\ajax_forms_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\ajax_forms_test\Callbacks;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form builder: Builds a form that triggers a simple AJAX callback.
 *
 * @internal
 */
class AjaxFormsTestSimpleForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ajax_forms_test_simple_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];
    $form['select'] = [
      '#title' => $this->t('Color'),
      '#type' => 'select',
      '#options' => [
        'red' => 'red',
        'green' => 'green',
        'blue' => 'blue',
      ],
      '#ajax' => [
        'callback' => [Callbacks::class, 'selectCallback'],
      ],
      '#suffix' => '<div id="ajax_selected_color">No color yet selected</div>',
    ];

    $form['checkbox'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Test checkbox'),
      '#ajax' => [
        'callback' => [Callbacks::class, 'checkboxCallback'],
      ],
      '#suffix' => '<div id="ajax_checkbox_value">No action yet</div>',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('submit'),
    ];

    // This is for testing invalid callbacks that should return a 500 error in
    // \Drupal\Core\Form\FormAjaxResponseBuilderInterface::buildResponse().
    $invalid_callbacks = [
      'null' => NULL,
      'empty' => '',
      'nonexistent' => 'some_function_that_does_not_exist',
    ];
    foreach ($invalid_callbacks as $key => $value) {
      $form['select_' . $key . '_callback'] = [
        '#type' => 'select',
        '#title' => $this->t('Test %key callbacks', ['%key' => $key]),
        '#options' => ['red' => 'red', 'green' => 'green'],
        '#ajax' => ['callback' => $value],
      ];
    }

    $form['test_group'] = [
      '#type' => 'details',
      '#title' => $this->t('Test group'),
      '#open' => TRUE,
    ];

    // Test ajax element in a #group.
    $form['checkbox_in_group_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'checkbox-wrapper'],
      '#group' => 'test_group',
      'checkbox_in_group' => [
        '#type' => 'checkbox',
        '#title' => $this->t('AJAX checkbox in a group'),
        '#ajax' => [
          'callback' => [Callbacks::class, 'checkboxGroupCallback'],
          'wrapper' => 'checkbox-wrapper',
        ],
      ],
      'nested_group' => [
        '#type' => 'details',
        '#title' => $this->t('Nested group'),
        '#open' => TRUE,
      ],
      'checkbox_in_nested' => [
        '#type' => 'checkbox',
        '#group' => 'nested_group',
        '#title' => $this->t('AJAX checkbox in a nested group'),
        '#ajax' => [
          'callback' => [Callbacks::class, 'checkboxGroupCallback'],
          'wrapper' => 'checkbox-wrapper',
        ],
      ],
    ];

    $form['another_checkbox_in_nested'] = [
      '#type' => 'checkbox',
      '#group' => 'nested_group',
      '#title' => $this->t('Another AJAX checkbox in a nested group'),
    ];

    $form['textfield_focus_tests'] = [
      '#type' => 'details',
      '#title' => $this->t('Test group 2'),
      '#open' => TRUE,
    ];
    $form['textfield_focus_tests']['textfield'] = [
      '#type' => 'textfield',
      '#title' => 'Textfield 1',
      '#ajax' => [
        'callback' => [static::class, 'textfieldCallback'],
      ],
    ];
    $form['textfield_focus_tests']['textfield_2'] = [
      '#type' => 'textfield',
      '#title' => 'Textfield 2',
      '#ajax' => [
        'callback' => [static::class, 'textfieldCallback'],
        'event' => 'change',
        'refocus-blur' => FALSE,
      ],
    ];
    $form['textfield_focus_tests']['textfield_3'] = [
      '#type' => 'textfield',
      '#title' => 'Textfield 3',
      '#ajax' => [
        'callback' => [static::class, 'textfieldCallback'],
        'event' => 'change',
      ],
    ];

    return $form;
  }

  public static function textfieldCallback($form) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
