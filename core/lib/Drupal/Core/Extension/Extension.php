<?php

namespace Drupal\Core\Extension;

/**
 * Defines an extension (file) object.
 *
 * This class does not implement the Serializable interface since problems
 * occurred when using the serialize method.
 *
 * @see https://bugs.php.net/bug.php?id=66052
 */
#[\AllowDynamicProperties]
class Extension {

  /**
   * The type of the extension (e.g., 'module').
   *
   * @var string
   */
  protected $type;

  /**
   * The relative pathname of the extension.
   *
   * An example relative pathname is 'core/modules/node/node.info.yml'.
   *
   * @var string
   */
  protected $pathname;

  /**
   * The filename of the main extension file (e.g., 'node.module').
   *
   * @var string|null
   */
  protected $filename;

  /**
   * An SplFileInfo instance for the extension's info file.
   *
   * Note that SplFileInfo is a PHP resource and resources cannot be serialized.
   *
   * @var \SplFileInfo
   */
  protected $splFileInfo;

  /**
   * The app root.
   *
   * @var string
   */
  protected $root;

  /**
   * The extension info array.
   */
  public array $info;

  /**
   * Constructs a new Extension object.
   *
   * @param string $root
   *   The app root.
   * @param string $type
   *   The type of the extension; e.g., 'module'.
   * @param string $pathname
   *   The relative path and filename of the extension's info file; e.g.,
   *   'core/modules/node/node.info.yml'.
   * @param string $filename
   *   (optional) The filename of the main extension file; e.g., 'node.module'.
   */
  public function __construct($root, $type, $pathname, $filename = NULL) {
    // @see \Drupal\Core\Theme\ThemeInitialization::getActiveThemeByName()
    assert($pathname === 'core/core.info.yml' || ($pathname[0] !== '/' && file_exists($root . '/' . $pathname)), sprintf('The file specified by the given app root, relative path and file name (%s) do not exist.', $root . '/' . $pathname));
    $this->root = $root;
    $this->type = $type;
    $this->pathname = $pathname;
    $this->filename = $filename;
  }

  /**
   * Returns the type of the extension.
   *
   * @return string
   *   The extension type. This is usually 'module' or 'theme'.
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Returns the internal name of the extension.
   *
   * @return string
   *   The machine name of the extension.
   */
  public function getName() {
    return basename($this->pathname, '.info.yml');
  }

  /**
   * Returns the relative path of the extension.
   *
   * @return string
   *   The relative path of the extension.
   */
  public function getPath() {
    return dirname($this->pathname);
  }

  /**
   * Returns the relative path and filename of the extension's info file.
   *
   * @return string
   *   The relative path and filename of the extension's .info file.
   */
  public function getPathname() {
    return $this->pathname;
  }

  /**
   * Returns the filename of the extension's info file.
   *
   * @return string
   *   The base name of the extension .info file.
   */
  public function getFilename() {
    return basename($this->pathname);
  }

  /**
   * Returns the relative path of the main extension file, if any.
   *
   * @return string|null
   *   The relative path for the main extension file, usually the *.module file.
   */
  public function getExtensionPathname() {
    if ($this->filename) {
      return $this->getPath() . '/' . $this->filename;
    }
  }

  /**
   * Returns the name of the main extension file, if any.
   *
   * @return string|null
   *   The filename of the main extension file, usually the *.module file.
   */
  public function getExtensionFilename() {
    return $this->filename;
  }

  /**
   * Loads the main extension file, if any.
   *
   * @return bool
   *   TRUE if this extension has a main extension file, FALSE otherwise.
   */
  public function load() {
    if ($this->filename) {
      include_once $this->root . '/' . $this->getPath() . '/' . $this->filename;
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Returns SplFileInfo instance for the extension's info file.
   *
   * @return \SplFileInfo
   *   The object to access a file information of info file.
   *
   * @see https://www.php.net/manual/class.splfileinfo.php
   */
  public function getFileInfo(): \SplFileInfo {
    if (!isset($this->splFileInfo)) {
      $this->splFileInfo = new \SplFileInfo($this->root . '/' . $this->pathname);
    }
    return $this->splFileInfo;
  }

  /**
   * Magic method implementation to serialize the extension object.
   *
   * @return array
   *   The names of all variables that should be serialized.
   */
  public function __sleep(): array {
    // @todo \Drupal\Core\Extension\ThemeExtensionList is adding custom
    //   properties to the Extension object.
    $properties = get_object_vars($this);
    // Don't serialize the app root, since this could change if the install is
    // moved. Don't serialize splFileInfo because it can not be.
    unset($properties['splFileInfo'], $properties['root']);
    return array_keys($properties);
  }

  /**
   * Magic method implementation to unserialize the extension object.
   */
  public function __wakeup(): void {
    // Get the app root from the container. While compiling the container we
    // have to discover all the extension service files in
    // \Drupal\Core\DrupalKernel::initializeServiceProviders(). This results in
    // creating extension objects before the container has the kernel.
    // Specifically, this occurs during the call to
    // \Drupal\Core\Extension\ExtensionDiscovery::scanDirectory().
    $container = \Drupal::hasContainer() ? \Drupal::getContainer() : FALSE;
    $this->root = $container && $container->hasParameter('app.root') ? $container->getParameter('app.root') : DRUPAL_ROOT;
  }

  /**
   * Checks if an extension is marked as experimental.
   *
   * @return bool
   *   TRUE if an extension is marked as experimental, FALSE otherwise.
   */
  public function isExperimental(): bool {
    return (isset($this->info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER])
        && $this->info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER] === ExtensionLifecycle::EXPERIMENTAL);
  }

  /**
   * Checks if an extension is marked as obsolete.
   *
   * @return bool
   *   TRUE if an extension is marked as obsolete, FALSE otherwise.
   */
  public function isObsolete(): bool {
    // This function checks for 'lifecycle: obsolete' to determine if an
    // extension is marked as obsolete.
    return (isset($this->info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER])
        && $this->info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER] === ExtensionLifecycle::OBSOLETE);
  }

}
