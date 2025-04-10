<?php

namespace Drupal\file;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Defines getter and setter methods for file entity base fields.
 *
 * @ingroup file
 */
interface FileInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Indicates that the file is permanent and should not be deleted.
   *
   * Temporary files older than the system.file.temporary_maximum_age will be
   * removed during cron runs if cleanup is not disabled. (Permanent files will
   * not be removed during the file garbage collection process.)
   */
  const STATUS_PERMANENT = 1;

  /**
   * Returns the name of the file.
   *
   * This may differ from the basename of the URI if the file is renamed to
   * avoid overwriting an existing file.
   *
   * @return string|null
   *   Name of the file, or NULL if unknown.
   */
  public function getFilename();

  /**
   * Sets the name of the file.
   *
   * @param string|null $filename
   *   The file name that corresponds to this file, or NULL if unknown. May
   *   differ from the basename of the URI and changing the filename does not
   *   change the URI.
   */
  public function setFilename($filename);

  /**
   * Returns the URI of the file.
   *
   * @return string|null
   *   The URI of the file, e.g. public://directory/file.jpg, or NULL if it has
   *   not yet been set.
   */
  public function getFileUri();

  /**
   * Sets the URI of the file.
   *
   * @param string $uri
   *   The URI of the file, e.g. public://directory/file.jpg. Does not change
   *   the location of the file.
   */
  public function setFileUri($uri);

  /**
   * Creates a file URL for the URI of this file.
   *
   * @param bool $relative
   *   (optional) Whether the URL should be root-relative, defaults to TRUE.
   *
   * @return string
   *   A string containing a URL that may be used to access the file.
   *
   * @see \Drupal\Core\File\FileUrlGeneratorInterface
   */
  public function createFileUrl($relative = TRUE);

  /**
   * Returns the MIME type of the file.
   *
   * @return string|null
   *   The MIME type of the file, e.g. image/jpeg or text/xml, or NULL if it
   *   could not be determined.
   */
  public function getMimeType();

  /**
   * Sets the MIME type of the file.
   *
   * @param string|null $mime
   *   The MIME type of the file, e.g. image/jpeg or text/xml, or NULL if it
   *   could not be determined.
   */
  public function setMimeType($mime);

  /**
   * Returns the size of the file.
   *
   * @return int|null
   *   The size of the file in bytes, or NULL if it could not be determined.
   */
  public function getSize();

  /**
   * Sets the size of the file.
   *
   * @param int|null $size
   *   The size of the file in bytes, or NULL if it could not be determined.
   */
  public function setSize($size);

  /**
   * Returns TRUE if the file is permanent.
   *
   * @return bool
   *   TRUE if the file status is permanent.
   */
  public function isPermanent();

  /**
   * Returns TRUE if the file is temporary.
   *
   * @return bool
   *   TRUE if the file status is temporary.
   */
  public function isTemporary();

  /**
   * Sets the file status to permanent.
   */
  public function setPermanent();

  /**
   * Sets the file status to temporary.
   */
  public function setTemporary();

  /**
   * Returns the file entity creation timestamp.
   *
   * @return int|null
   *   Creation timestamp of the file entity, or NULL if unknown.
   */
  public function getCreatedTime();

  /**
   * Examines a file entity and returns content headers for download.
   *
   * @return array
   *   An associative array of headers, as expected by
   *   \Symfony\Component\HttpFoundation\StreamedResponse.
   */
  public function getDownloadHeaders(): array;

}
