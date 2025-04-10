<?php

namespace Drupal\Core\Updater;

@trigger_error('The ' . __NAMESPACE__ . '\Updater base class is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site. See https://www.drupal.org/node/3512364', E_USER_DEPRECATED);

use Drupal\Core\FileTransfer\FileTransferException;
use Drupal\Core\FileTransfer\FileTransfer;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines the base class for Updaters used in Drupal.
 *
 * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no
 *   replacement. Use composer to manage the code for your site.
 *
 * @see https://www.drupal.org/node/3512364
 */
abstract class Updater {

  use StringTranslationTrait;

  /**
   * Directory to install from.
   *
   * @var string
   */
  public $source;

  /**
   * The root directory under which new projects will be copied.
   *
   * @var string
   */
  protected $root;

  /**
   * The name of the project directory (basename).
   */
  protected string $name;

  /**
   * The title of the project.
   */
  protected string $title;

  /**
   * Constructs a new updater.
   *
   * @param string $source
   *   Directory to install from.
   * @param string $root
   *   The root directory under which the project will be copied to if it's a
   *   new project. Usually this is the app root (the directory in which the
   *   Drupal site is installed).
   */
  public function __construct($source, $root) {
    $this->source = $source;
    $this->root = $root;
    $this->name = self::getProjectName($source);
    $this->title = self::getProjectTitle($source);
  }

  /**
   * Returns an Updater of the appropriate type depending on the source.
   *
   * If a directory is provided which contains a module, will return a
   * ModuleUpdater.
   *
   * @param string $source
   *   Directory of a Drupal project.
   * @param string $root
   *   The root directory under which the project will be copied to if it's a
   *   new project. Usually this is the app root (the directory in which the
   *   Drupal site is installed).
   *
   * @return \Drupal\Core\Updater\Updater
   *   A new Drupal\Core\Updater\Updater object.
   *
   * @throws \Drupal\Core\Updater\UpdaterException
   */
  public static function factory($source, $root) {
    if (is_dir($source)) {
      $updater = self::getUpdaterFromDirectory($source);
    }
    else {
      throw new UpdaterException('Unable to determine the type of the source directory.');
    }
    return new $updater($source, $root);
  }

  /**
   * Determines which Updater class can operate on the given directory.
   *
   * @param string $directory
   *   Extracted Drupal project.
   *
   * @return string
   *   The class name which can work with this project type.
   *
   * @throws \Drupal\Core\Updater\UpdaterException
   */
  public static function getUpdaterFromDirectory($directory) {
    // Gets a list of possible implementing classes.
    $updaters = drupal_get_updaters();
    foreach ($updaters as $updater) {
      $class = $updater['class'];
      if (call_user_func([$class, 'canUpdateDirectory'], $directory)) {
        return $class;
      }
    }
    throw new UpdaterException('Cannot determine the type of project.');
  }

  /**
   * Determines what the most important (or only) info file is in a directory.
   *
   * Since there is no enforcement of which info file is the project's "main"
   * info file, this will get one with the same name as the directory, or the
   * first one it finds.  Not ideal, but needs a larger solution.
   *
   * @param string $directory
   *   Directory to search in.
   *
   * @return string
   *   Path to the info file.
   */
  public static function findInfoFile($directory) {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $info_files = [];
    if (is_dir($directory)) {
      $info_files = $file_system->scanDirectory($directory, '/.*\.info.yml$/');
    }
    if (!$info_files) {
      return FALSE;
    }
    foreach ($info_files as $info_file) {
      if (mb_substr($info_file->filename, 0, -9) == $file_system->basename($directory)) {
        // Info file Has the same name as the directory, return it.
        return $info_file->uri;
      }
    }
    // Otherwise, return the first one.
    $info_file = array_shift($info_files);
    return $info_file->uri;
  }

  /**
   * Get Extension information from directory.
   *
   * @param string $directory
   *   Directory to search in.
   *
   * @return array
   *   Extension info.
   *
   * @throws \Drupal\Core\Updater\UpdaterException
   *   If the info parser does not provide any info.
   */
  protected static function getExtensionInfo($directory) {
    $info_file = static::findInfoFile($directory);
    $info = \Drupal::service('info_parser')->parse($info_file);
    if (empty($info)) {
      throw new UpdaterException("Unable to parse info file: '$info_file'.");
    }

    return $info;
  }

  /**
   * Gets the name of the project directory (basename).
   *
   * @todo It would be nice, if projects contained an info file which could
   *   provide their canonical name.
   *
   * @param string $directory
   *   The full directory path.
   *
   * @return string
   *   The name of the project.
   */
  public static function getProjectName($directory) {
    return \Drupal::service('file_system')->basename($directory);
  }

  /**
   * Returns the project name from a Drupal info file.
   *
   * @param string $directory
   *   Directory to search for the info file.
   *
   * @return string
   *   The title of the project.
   *
   * @throws \Drupal\Core\Updater\UpdaterException
   */
  public static function getProjectTitle($directory) {
    $info_file = self::findInfoFile($directory);
    $info = \Drupal::service('info_parser')->parse($info_file);
    if (empty($info)) {
      throw new UpdaterException("Unable to parse info file: '$info_file'.");
    }
    return $info['name'];
  }

  /**
   * Returns the path to the default install location for the current project.
   *
   * @return string
   *   The absolute path of the directory.
   */
  abstract public function getInstallDirectory();

  /**
   * Stores the default parameters for the Updater.
   *
   * @param array $overrides
   *   An array of overrides for the default parameters.
   *
   * @return array
   *   An array of configuration parameters for an update or install operation.
   */
  protected function getInstallArgs($overrides = []) {
    $args = [
      'make_backup' => FALSE,
      'install_dir' => $this->getInstallDirectory(),
      'backup_dir'  => $this->getBackupDir(),
    ];
    return array_merge($args, $overrides);
  }

  /**
   * Updates a Drupal project and returns a list of next actions.
   *
   * @param \Drupal\Core\FileTransfer\FileTransfer $filetransfer
   *   Object that is a child of FileTransfer. Used for moving files
   *   to the server.
   * @param array $overrides
   *   An array of settings to override defaults; see self::getInstallArgs().
   *
   * @return array
   *   An array of links which the user may need to complete the update
   *
   * @throws \Drupal\Core\Updater\UpdaterException
   * @throws \Drupal\Core\Updater\UpdaterFileTransferException
   */
  public function update(&$filetransfer, $overrides = []) {
    try {
      // Establish arguments with possible overrides.
      $args = $this->getInstallArgs($overrides);

      // Take a Backup.
      if ($args['make_backup']) {
        $this->makeBackup($filetransfer, $args['install_dir'], $args['backup_dir']);
      }

      if (!$this->name) {
        // This is bad, don't want to delete the install directory.
        throw new UpdaterException('Fatal error in update, cowardly refusing to wipe out the install directory.');
      }

      // Make sure the installation parent directory exists and is writable.
      $this->prepareInstallDirectory($filetransfer, $args['install_dir']);

      if (is_dir($args['install_dir'] . '/' . $this->name)) {
        // Remove the existing installed file.
        $filetransfer->removeDirectory($args['install_dir'] . '/' . $this->name);
      }

      // Copy the directory in place.
      $filetransfer->copyDirectory($this->source, $args['install_dir']);

      // Make sure what we just installed is readable by the web server.
      $this->makeWorldReadable($filetransfer, $args['install_dir'] . '/' . $this->name);

      // Run the updates.
      // @todo Decide if we want to implement this.
      $this->postUpdate();

      // For now, just return a list of links of things to do.
      return $this->postUpdateTasks();
    }
    catch (FileTransferException $e) {
      throw new UpdaterFileTransferException("File Transfer failed, reason: '" . strtr($e->getMessage(), $e->arguments) . "'");
    }
  }

  /**
   * Installs a Drupal project, returns a list of next actions.
   *
   * @param \Drupal\Core\FileTransfer\FileTransfer $filetransfer
   *   Object that is a child of FileTransfer.
   * @param array $overrides
   *   An array of settings to override defaults; see self::getInstallArgs().
   *
   * @return array
   *   An array of links which the user may need to complete the install.
   *
   * @throws \Drupal\Core\Updater\UpdaterFileTransferException
   */
  public function install(&$filetransfer, $overrides = []) {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. There is no replacement. See https://www.drupal.org/node/3461934', E_USER_DEPRECATED);
    try {
      // Establish arguments with possible overrides.
      $args = $this->getInstallArgs($overrides);

      // Make sure the installation parent directory exists and is writable.
      $this->prepareInstallDirectory($filetransfer, $args['install_dir']);

      // Copy the directory in place.
      $filetransfer->copyDirectory($this->source, $args['install_dir']);

      // Make sure what we just installed is readable by the web server.
      $this->makeWorldReadable($filetransfer, $args['install_dir'] . '/' . $this->name);

      // Potentially enable something?
      // @todo Decide if we want to implement this.
      $this->postInstall();
      // For now, just return a list of links of things to do.
      return $this->postInstallTasks();
    }
    catch (FileTransferException $e) {
      throw new UpdaterFileTransferException("File Transfer failed, reason: '" . strtr($e->getMessage(), $e->arguments) . "'");
    }
  }

  /**
   * Makes sure the installation parent directory exists and is writable.
   *
   * @param \Drupal\Core\FileTransfer\FileTransfer $filetransfer
   *   Object which is a child of FileTransfer.
   * @param string $directory
   *   The installation directory to prepare.
   *
   * @throws \Drupal\Core\Updater\UpdaterException
   */
  public function prepareInstallDirectory(&$filetransfer, $directory) {
    // Make the parent dir writable if need be and create the dir.
    if (!is_dir($directory)) {
      $parent_dir = dirname($directory);
      if (!is_writable($parent_dir)) {
        @chmod($parent_dir, 0755);
        // It is expected that this will fail if the directory is owned by the
        // FTP user. If the FTP user == web server, it will succeed.
        try {
          $filetransfer->createDirectory($directory);
          $this->makeWorldReadable($filetransfer, $directory);
        }
        catch (FileTransferException $e) {
          // Probably still not writable. Try to chmod and do it again.
          // @todo Make a new exception class so we can catch it differently.
          try {
            $old_perms = fileperms($parent_dir) & 0777;
            $filetransfer->chmod($parent_dir, 0755);
            $filetransfer->createDirectory($directory);
            $this->makeWorldReadable($filetransfer, $directory);
            // Put the permissions back.
            $filetransfer->chmod($parent_dir, $old_perms);
          }
          catch (FileTransferException $e) {
            // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
            $message = $this->t($e->getMessage(), $e->arguments);
            $throw_message = $this->t('Unable to create %directory due to the following: %reason', [
              '%directory' => $directory,
              '%reason' => $message,
            ]);
            throw new UpdaterException($throw_message);
          }
        }
        // Put the parent directory back.
        @chmod($parent_dir, 0555);
      }
    }
  }

  /**
   * Ensures that a given directory is world readable.
   *
   * @param \Drupal\Core\FileTransfer\FileTransfer $filetransfer
   *   Object which is a child of FileTransfer.
   * @param string $path
   *   The file path to make world readable.
   * @param bool $recursive
   *   If the chmod should be applied recursively.
   */
  public function makeWorldReadable(&$filetransfer, $path, $recursive = TRUE) {
    if (!is_executable($path)) {
      // Set it to read + execute.
      $new_perms = fileperms($path) & 0777 | 0005;
      $filetransfer->chmod($path, $new_perms, $recursive);
    }
  }

  /**
   * Performs a backup.
   *
   * @param \Drupal\Core\FileTransfer\FileTransfer $filetransfer
   *   Object which is a child of FileTransfer.
   * @param string $from
   *   The file path to copy from.
   * @param string $to
   *   The file path to copy to.
   *
   * @todo Not implemented: https://www.drupal.org/node/2474355
   */
  public function makeBackup(FileTransfer $filetransfer, $from, $to) {
  }

  /**
   * Returns the full path to a directory where backups should be written.
   */
  public function getBackupDir() {
    return \Drupal::service('stream_wrapper_manager')->getViaScheme('temporary')->getDirectoryPath();
  }

  /**
   * Performs actions after installation.
   */
  public function postInstall() {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. There is no replacement. See https://www.drupal.org/node/3461934', E_USER_DEPRECATED);
  }

  /**
   * Returns an array of links to pages that should be visited post operation.
   *
   * @return array
   *   Links which provide actions to take after the install is finished.
   */
  public function postInstallTasks() {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. There is no replacement. See https://www.drupal.org/node/3461934', E_USER_DEPRECATED);
    return [];
  }

  /**
   * Performs actions after new code is updated.
   */
  public function postUpdate() {
  }

  /**
   * Returns an array of links to pages that should be visited post operation.
   *
   * @return array
   *   Links which provide actions to take after the update is finished.
   */
  public function postUpdateTasks() {
    return [];
  }

}
