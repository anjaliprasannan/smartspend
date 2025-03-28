<?php

declare(strict_types=1);

namespace Drupal\ajax_forms_test\Form;

use Drupal\ajax_forms_test\Callbacks;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form constructor for the Ajax Command display form.
 *
 * @internal
 */
class AjaxFormsTestCommandsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ajax_forms_test_ajax_commands_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    // Shows the 'after' command with a callback generating commands.
    $form['after_command_example'] = [
      '#value' => $this->t("AJAX 'After': Click to put something after the div"),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsAfterCallback'],
      ],
      '#suffix' => '<div id="after_div">Something can be inserted after this</div>',
    ];

    // Shows the 'alert' command.
    $form['alert_command_example'] = [
      '#value' => $this->t("AJAX 'Alert': Click to alert"),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsAlertCallback'],
      ],
    ];

    // Shows the 'announce' command with default priority.
    $form['announce_command_example'] = [
      '#value' => $this->t("AJAX 'Announce': Click to announce"),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsAnnounceCallback'],
      ],
    ];

    // Shows the 'announce' command with 'polite' priority.
    $form['announce_command_polite_example'] = [
      '#value' => $this->t("AJAX 'Announce': Click to announce with 'polite' priority"),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsAnnouncePoliteCallback'],
      ],
    ];

    // Shows the 'announce' command with 'assertive' priority.
    $form['announce_command_assertive_example'] = [
      '#value' => $this->t("AJAX 'Announce': Click to announce with 'assertive' priority"),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsAnnounceAssertiveCallback'],
      ],
    ];

    // Shows the 'announce' command used twice in one AjaxResponse.
    $form['announce_command_double_example'] = [
      '#value' => $this->t("AJAX 'Announce': Click to announce twice"),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsDoubleAnnounceCallback'],
      ],
    ];

    // Shows the 'append' command.
    $form['append_command_example'] = [
      '#value' => $this->t("AJAX 'Append': Click to append something"),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsAppendCallback'],
      ],
      '#suffix' => '<div id="append_div">Append inside this div</div>',
    ];

    // Shows the 'before' command.
    $form['before_command_example'] = [
      '#value' => $this->t("AJAX 'before': Click to put something before the div"),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsBeforeCallback'],
      ],
      '#suffix' => '<div id="before_div">Insert something before this.</div>',
    ];

    // Shows the 'changed' command without asterisk.
    $form['changed_command_example'] = [
      '#value' => $this->t("AJAX changed: Click to mark div changed."),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsChangedCallback'],
      ],
      '#suffix' => '<div id="changed_div"> <div id="changed_div_mark_this">This div can be marked as changed or not.</div></div>',
    ];
    // Shows the 'changed' command adding the asterisk.
    $form['changed_command_asterisk_example'] = [
      '#value' => $this->t("AJAX changed: Click to mark div changed with asterisk."),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsChangedAsteriskCallback'],
      ],
    ];

    // Shows the Ajax 'css' command.
    $form['css_command_example'] = [
      '#value' => $this->t("Set the '#box' div to be blue."),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsCssCallback'],
      ],
      '#suffix' => '<div id="css_div" style="height: 50px; width: 50px; border: 1px solid black"> box</div>',
    ];

    // Shows the Ajax 'data' command. But there is no use of this information,
    // as this would require a javascript client to use the data.
    $form['data_command_example'] = [
      '#value' => $this->t("AJAX data command: Issue command."),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsDataCallback'],
      ],
      '#suffix' => '<div id="data_div">Data attached to this div.</div>',
    ];

    // Shows the Ajax 'invoke' command.
    $form['invoke_command_example'] = [
      '#value' => $this->t("AJAX invoke command: Invoke addClass() method."),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsInvokeCallback'],
      ],
      '#suffix' => '<div id="invoke_div">Original contents</div>',
    ];

    // Shows the Ajax 'html' command.
    $form['html_command_example'] = [
      '#value' => $this->t("AJAX html: Replace the HTML in a selector."),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsHtmlCallback'],
      ],
      '#suffix' => '<div id="html_div">Original contents</div>',
    ];

    // Shows the Ajax 'insert' command.
    $form['insert_command_example'] = [
      '#value' => $this->t("AJAX insert: Let client insert based on #ajax['method']."),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsInsertCallback'],
        'method' => 'prepend',
      ],
      '#suffix' => '<div id="insert_div">Original contents</div>',
    ];

    // Shows the Ajax 'prepend' command.
    $form['prepend_command_example'] = [
      '#value' => $this->t("AJAX 'prepend': Click to prepend something"),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsPrependCallback'],
      ],
      '#suffix' => '<div id="prepend_div">Something will be prepended to this div. </div>',
    ];

    // Shows the Ajax 'remove' command.
    $form['remove_command_example'] = [
      '#value' => $this->t("AJAX 'remove': Click to remove text"),
      '#type' => 'submit',
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsRemoveCallback'],
      ],
      '#suffix' => '<div id="remove_div"><div id="remove_text">text to be removed</div></div>',
    ];

    // Shows the Ajax 'restripe' command.
    $form['restripe_command_example'] = [
      '#type' => 'submit',
      '#value' => $this->t("AJAX 'restripe' command"),
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsRestripeCallback'],
      ],
      '#suffix' => '<div id="restripe_div">
                    <table id="restripe_table" style="border: 1px solid black" >
                    <tr id="table-first"><td>first row</td></tr>
                    <tr ><td>second row</td></tr>
                    </table>
                    </div>',
    ];

    // Demonstrates the Ajax 'settings' command. The 'settings' command has
    // nothing visual to "show", but it can be tested.
    $form['settings_command_example'] = [
      '#type' => 'submit',
      '#value' => $this->t("AJAX 'settings' command"),
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsSettingsCallback'],
      ],
    ];

    // Shows the Ajax 'add_css' command.
    $form['add_css_command_example'] = [
      '#type' => 'submit',
      '#value' => $this->t("AJAX 'add_css' command"),
      '#ajax' => [
        'callback' => [Callbacks::class, 'advancedCommandsAddCssCallback'],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
