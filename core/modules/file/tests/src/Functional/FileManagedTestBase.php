<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Functional;

use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file_test\FileTestHelper;
use Drupal\Tests\BrowserTestBase;

/**
 * Provides a base class for testing files with the file_test module.
 */
abstract class FileManagedTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['file_test', 'file'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Clear out any hook calls.
    FileTestHelper::reset();
  }

  /**
   * Asserts that the specified file hooks were called only once.
   *
   * @param string[] $expected
   *   An array of strings containing with the hook name; for example, 'load',
   *   'save', 'insert', etc.
   */
  public function assertFileHooksCalled($expected) {
    \Drupal::state()->resetCache();

    // Determine which hooks were called.
    $actual = array_keys(array_filter(FileTestHelper::getAllCalls()));

    // Determine if there were any expected that were not called.
    $uncalled = array_diff($expected, $actual);
    if (count($uncalled)) {
      $this->assertTrue(FALSE, sprintf('Expected hooks %s to be called but %s was not called.', implode(', ', $expected), implode(', ', $uncalled)));
    }
    else {
      $this->assertTrue(TRUE, sprintf('All the expected hooks were called: %s', empty($expected) ? '(none)' : implode(', ', $expected)));
    }

    // Determine if there were any unexpected calls.
    $unexpected = array_diff($actual, $expected);
    if (count($unexpected)) {
      $this->assertTrue(FALSE, sprintf('Unexpected hooks were called: %s.', empty($unexpected) ? '(none)' : implode(', ', $unexpected)));
    }
    else {
      $this->assertTrue(TRUE, 'No unexpected hooks were called.');
    }
  }

  /**
   * Assert that a hook_file_* hook was called a certain number of times.
   *
   * @param string $hook
   *   String with the hook name; for instance, 'load', 'save', 'insert', etc.
   * @param int $expected_count
   *   Optional integer count.
   * @param string|null $message
   *   Optional translated string message.
   */
  public function assertFileHookCalled($hook, $expected_count = 1, $message = NULL) {
    $actual_count = count(FileTestHelper::getCalls($hook));

    if (!isset($message)) {
      if ($actual_count == $expected_count) {
        $message = "hook_file_$hook was called correctly.";
      }
      elseif ($expected_count == 0) {
        $message = "hook_file_$hook was not expected to be called but was actually called $actual_count time(s).";
      }
      else {
        $message = "hook_file_$hook was expected to be called $expected_count time(s) but was called $actual_count time(s).";
      }
    }
    $this->assertEquals($expected_count, $actual_count, $message);
  }

  /**
   * Asserts that two files have the same values (except timestamp).
   *
   * @param \Drupal\file\FileInterface $before
   *   File object to compare.
   * @param \Drupal\file\FileInterface $after
   *   File object to compare.
   */
  public function assertFileUnchanged(FileInterface $before, FileInterface $after) {
    $this->assertEquals($before->id(), $after->id());
    $this->assertEquals($before->getOwner()->id(), $after->getOwner()->id());
    $this->assertEquals($before->getFilename(), $after->getFilename());
    $this->assertEquals($before->getFileUri(), $after->getFileUri());
    $this->assertEquals($before->getMimeType(), $after->getMimeType());
    $this->assertEquals($before->getSize(), $after->getSize());
    $this->assertEquals($before->isPermanent(), $after->isPermanent());
  }

  /**
   * Asserts that two files are not the same by comparing the fid and filepath.
   *
   * @param \Drupal\file\FileInterface $file1
   *   File object to compare.
   * @param \Drupal\file\FileInterface $file2
   *   File object to compare.
   */
  public function assertDifferentFile(FileInterface $file1, FileInterface $file2) {
    $this->assertNotEquals($file1->id(), $file2->id());
    $this->assertNotEquals($file1->getFileUri(), $file2->getFileUri());
  }

  /**
   * Asserts that two files are the same by comparing the fid and filepath.
   *
   * @param \Drupal\file\FileInterface $file1
   *   File object to compare.
   * @param \Drupal\file\FileInterface $file2
   *   File object to compare.
   */
  public function assertSameFile(FileInterface $file1, FileInterface $file2) {
    $this->assertEquals($file1->id(), $file2->id());
    $this->assertEquals($file1->getFileUri(), $file2->getFileUri());
  }

  /**
   * Creates and saves a file, asserting that it was saved.
   *
   * @param string $filepath
   *   Optional string specifying the file path. If none is provided then a
   *   randomly named file will be created in the site's files directory.
   * @param string $contents
   *   Optional contents to save into the file. If a NULL value is provided an
   *   arbitrary string will be used.
   * @param string $scheme
   *   Optional string indicating the stream scheme to use. Drupal core includes
   *   public, private, and temporary. The public wrapper is the default.
   *
   * @return \Drupal\file\FileInterface
   *   File entity.
   */
  public function createFile($filepath = NULL, $contents = NULL, $scheme = NULL) {
    // Don't count hook invocations caused by creating the file.
    \Drupal::state()->set('file_test.count_hook_invocations', FALSE);
    $file = File::create([
      'uri' => $this->createUri($filepath, $contents, $scheme),
      'uid' => 1,
    ]);
    $file->save();
    // Write the record directly rather than using the API so we don't invoke
    // the hooks.
    // Verify that the file was added to the database.
    $this->assertGreaterThan(0, $file->id());

    \Drupal::state()->set('file_test.count_hook_invocations', TRUE);
    return $file;
  }

  /**
   * Creates a file and returns its URI.
   *
   * @param string $filepath
   *   Optional string specifying the file path. If none is provided then a
   *   randomly named file will be created in the site's files directory.
   * @param string $contents
   *   Optional contents to save into the file. If a NULL value is provided an
   *   arbitrary string will be used.
   * @param string $scheme
   *   Optional string indicating the stream scheme to use. Drupal core includes
   *   public, private, and temporary. The public wrapper is the default.
   *
   * @return string
   *   File URI.
   */
  public function createUri($filepath = NULL, $contents = NULL, $scheme = NULL) {
    if (!isset($filepath)) {
      // Prefix with non-latin characters to ensure that all file-related
      // tests work with international filenames.
      // cSpell:disable-next-line
      $filepath = 'Файл для тестирования ' . $this->randomMachineName();
    }
    if (!isset($scheme)) {
      $scheme = 'public';
    }
    $filepath = $scheme . '://' . $filepath;

    if (!isset($contents)) {
      $contents = "file_put_contents() doesn't seem to appreciate empty strings so let's put in some data.";
    }

    file_put_contents($filepath, $contents);
    $this->assertFileExists($filepath);
    return $filepath;
  }

}
