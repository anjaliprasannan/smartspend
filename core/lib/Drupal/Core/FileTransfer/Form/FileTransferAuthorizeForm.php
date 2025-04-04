<?php

namespace Drupal\Core\FileTransfer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides the file transfer authorization form.
 *
 * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no
 *   replacement. Use composer to manage the code for your site.
 *
 * @see https://www.drupal.org/node/3512364
 * @internal
 */
class FileTransferAuthorizeForm extends FormBase {

  /**
   * The app root.
   *
   * @var string
   */
  protected $root;

  /**
   * Constructs a new FileTransferAuthorizeForm object.
   *
   * @param string $root
   *   The app root.
   */
  public function __construct($root) {
    @trigger_error(__CLASS__ . ' is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site. See https://www.drupal.org/node/3512364', E_USER_DEPRECATED);

    $this->root = $root;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->getParameter('app.root'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'authorize_filetransfer_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get all the available ways to transfer files.
    $available_backends = $this->getRequest()->getSession()->get('authorize_filetransfer_info', []);
    if (empty($available_backends)) {
      $this->messenger()->addError($this->t('Unable to continue, no available methods of file transfer'));
      return [];
    }

    if (!$this->getRequest()->isSecure()) {
      $form['information']['https_warning'] = [
        '#prefix' => '<div class="messages messages--error">',
        '#markup' => $this->t('WARNING: You are not using an encrypted connection, so your password will be sent in plain text. <a href=":https-link">Learn more</a>.', [':https-link' => 'https://www.drupal.org/https-information']),
        '#suffix' => '</div>',
      ];
    }

    // Decide on a default backend.
    $authorize_filetransfer_default = $form_state->getValue(['connection_settings', 'authorize_filetransfer_default']);
    if (!$authorize_filetransfer_default) {
      $authorize_filetransfer_default = key($available_backends);
    }

    $form['information']['main_header'] = [
      '#prefix' => '<h3>',
      '#markup' => $this->t('To continue, provide your server connection details'),
      '#suffix' => '</h3>',
    ];

    $form['connection_settings']['#tree'] = TRUE;
    $form['connection_settings']['authorize_filetransfer_default'] = [
      '#type' => 'select',
      '#title' => $this->t('Connection method'),
      '#default_value' => $authorize_filetransfer_default,
      '#weight' => -10,
    ];

    /*
     * Here we create two submit buttons. For a JS enabled client, they will
     * only ever see submit_process. However, if a client doesn't have JS
     * enabled, they will see submit_connection on the first form (when picking
     * what filetransfer type to use, and submit_process on the second one
     * (which leads to the actual operation).
     */
    $form['submit_connection'] = [
      '#prefix' => "<br style='clear:both'/>",
      '#name' => 'enter_connection_settings',
      '#type' => 'submit',
      '#value' => $this->t('Enter connection settings'),
      '#weight' => 100,
    ];

    $form['submit_process'] = [
      '#name' => 'process_updates',
      '#type' => 'submit',
      '#value' => $this->t('Continue'),
      '#weight' => 100,
    ];

    // Build a container for each connection type.
    foreach ($available_backends as $name => $backend) {
      $form['connection_settings']['authorize_filetransfer_default']['#options'][$name] = $backend['title'];
      $form['connection_settings'][$name] = [
        '#type' => 'container',
        '#attributes' => ['class' => ["filetransfer-$name", 'filetransfer']],
        '#states' => [
          'visible' => [
            'select[name="connection_settings[authorize_filetransfer_default]"]' => ['value' => $name],
          ],
        ],
      ];
      // We can't use #prefix on the container itself since then the header
      // won't be hidden and shown when the containers are being manipulated via
      // JS.
      $form['connection_settings'][$name]['header'] = [
        '#markup' => '<h4>' . $this->t('@backend connection settings', ['@backend' => $backend['title']]) . '</h4>',
      ];

      $form['connection_settings'][$name] += $this->addConnectionSettings($name);

      // Start non-JS code.
      if ($form_state->getValue(['connection_settings', 'authorize_filetransfer_default']) == $name) {

        // Change the submit button to the submit_process one.
        $form['submit_process']['#attributes'] = [];
        unset($form['submit_connection']);

        // Activate the proper filetransfer settings form.
        $form['connection_settings'][$name]['#attributes']['style'] = 'display:block';
        // Disable the select box.
        $form['connection_settings']['authorize_filetransfer_default']['#disabled'] = TRUE;

        // Create a button for changing the type of connection.
        $form['connection_settings']['change_connection_type'] = [
          '#name' => 'change_connection_type',
          '#type' => 'submit',
          '#value' => $this->t('Change connection type'),
          '#weight' => -5,
          '#attributes' => ['class' => ['filetransfer-change-connection-type']],
        ];
      }
      // End non-JS code.
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Only validate the form if we have collected all of the user input and are
    // ready to proceed with updating or installing.
    if ($form_state->getTriggeringElement()['#name'] != 'process_updates') {
      return;
    }

    if ($form_connection_settings = $form_state->getValue('connection_settings')) {
      $backend = $form_connection_settings['authorize_filetransfer_default'];
      $filetransfer = $this->getFiletransfer($backend, $form_connection_settings[$backend]);
      try {
        if (!$filetransfer) {
          throw new \Exception("The connection protocol '$backend' does not exist.");
        }
        $filetransfer->connect();
      }
      catch (\Exception $e) {
        // The format of this error message is similar to that used on the
        // database connection form in the installer.
        $form_state->setErrorByName('connection_settings', $this->t('Failed to connect to the server. The server reports the following message: <p class="error">@message</p> For more help adding or updating code on your server, see the <a href=":handbook_url">handbook</a>.', [
          '@message' => $e->getMessage(),
          ':handbook_url' => 'https://www.drupal.org/docs/extending-drupal/overview',
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_connection_settings = $form_state->getValue('connection_settings');
    switch ($form_state->getTriggeringElement()['#name']) {
      case 'process_updates':

        // Save the connection settings to the DB.
        $filetransfer_backend = $form_connection_settings['authorize_filetransfer_default'];

        // If the database is available then try to save our settings. We have
        // to make sure it is available since this code could potentially (will
        // likely) be called during the installation process, before the
        // database is set up.
        try {
          $filetransfer = $this->getFiletransfer($filetransfer_backend, $form_connection_settings[$filetransfer_backend]);

          // Now run the operation.
          $response = $this->runOperation($filetransfer);
          if ($response instanceof Response) {
            $form_state->setResponse($response);
          }
        }
        catch (\Exception) {
          // If there is no database available, we don't care and just skip
          // this part entirely.
        }

        break;

      case 'enter_connection_settings':
        $form_state->setRebuild();
        break;

      case 'change_connection_type':
        $form_state->setRebuild();
        $form_state->unsetValue(['connection_settings', 'authorize_filetransfer_default']);
        break;
    }
  }

  /**
   * Gets a FileTransfer class for a specific transfer method and settings.
   *
   * @param string $backend
   *   The FileTransfer backend to get the class for.
   * @param array $settings
   *   Array of settings for the FileTransfer.
   *
   * @return \Drupal\Core\FileTransfer\FileTransfer|bool
   *   An instantiated FileTransfer object for the requested method and
   *   settings, or FALSE if there was an error finding or instantiating it.
   */
  protected function getFiletransfer($backend, $settings = []) {
    $filetransfer = FALSE;
    $info = $this->getRequest()->getSession()->get('authorize_filetransfer_info', []);
    if (!empty($info[$backend])) {
      if (class_exists($info[$backend]['class'])) {
        $filetransfer = $info[$backend]['class']::factory($this->root, $settings);
      }
    }
    return $filetransfer;
  }

  /**
   * Generates the Form API array for a given connection backend's settings.
   *
   * @param string $backend
   *   The name of the backend (e.g. 'ftp', 'ssh', etc).
   *
   * @return array
   *   Form API array of connection settings for the given backend.
   *
   * @see hook_filetransfer_backends()
   */
  protected function addConnectionSettings($backend) {
    $defaults = [];
    $form = [];

    // Create an instance of the file transfer class to get its settings form.
    $filetransfer = $this->getFiletransfer($backend);
    if ($filetransfer) {
      $form = $filetransfer->getSettingsForm();
    }
    // Fill in the defaults based on the saved settings, if any.
    $this->setConnectionSettingsDefaults($form, NULL, $defaults);
    return $form;
  }

  /**
   * Sets the default settings on a file transfer connection form recursively.
   *
   * The default settings for the file transfer connection forms are saved in
   * the database. The settings are stored as a nested array in the case of a
   * settings form that has details or otherwise uses a nested structure.
   * Therefore, to properly add defaults, we need to walk through all the
   * children form elements and process those defaults recursively.
   *
   * @param array $element
   *   Reference to the Form API form element we're operating on.
   * @param string $key
   *   The key for our current form element, if any.
   * @param array $defaults
   *   The default settings for the file transfer backend we're operating on.
   */
  protected function setConnectionSettingsDefaults(&$element, $key, array $defaults) {
    // If we're operating on a form element which isn't a details, and we have
    // a default setting saved, stash it in #default_value.
    if (!empty($key) && isset($defaults[$key]) && isset($element['#type']) && $element['#type'] != 'details') {
      $element['#default_value'] = $defaults[$key];
    }
    // Now, we walk through all the child elements, and recursively invoke
    // ourselves on each one. Since the $defaults settings array can be nested
    // (because of #tree, any values inside details will be nested), if
    // there's a subarray of settings for the form key we're currently
    // processing, pass in that subarray to the recursive call. Otherwise, just
    // pass on the whole $defaults array.
    foreach (Element::children($element) as $child_key) {
      $this->setConnectionSettingsDefaults($element[$child_key], $child_key, ((isset($defaults[$key]) && is_array($defaults[$key])) ? $defaults[$key] : $defaults));
    }
  }

  /**
   * Runs the operation specified in 'authorize_operation' session property.
   *
   * @param array $filetransfer
   *   The FileTransfer object to use for running the operation.
   *
   * @return \Symfony\Component\HttpFoundation\Response|null
   *   The result of running the operation. If this is an instance of
   *   \Symfony\Component\HttpFoundation\Response the calling code should use
   *   that response for the current page request.
   */
  protected function runOperation($filetransfer) {
    $operation = $this->getRequest()->getSession()->remove('authorize_operation');

    require_once $operation['file'];
    return call_user_func_array($operation['callback'], array_merge([$filetransfer], $operation['arguments']));
  }

}
