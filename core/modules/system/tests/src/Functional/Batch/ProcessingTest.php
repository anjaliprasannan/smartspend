<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Batch;

use Drupal\batch_test\BatchTestHelper;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests batch processing in form and non-form workflow.
 *
 * @group Batch
 */
class ProcessingTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['batch_test', 'test_page_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * Tests batches triggered outside of form submission.
   */
  public function testBatchNoForm(): void {
    // Displaying the page triggers batch 1.
    $batch_test_helper = new BatchTestHelper();
    $this->drupalGet('batch-test/no-form');
    $this->assertBatchMessages($this->_resultMessages('batch1'));
    $this->assertEquals($this->_resultStack('batch1'), $batch_test_helper->stack(), 'Execution order was correct.');
    $this->assertSession()->pageTextContains('Redirection successful.');
  }

  /**
   * Tests batches that redirect in the batch finished callback.
   */
  public function testBatchRedirectFinishedCallback(): void {
    $batch_test_helper = new BatchTestHelper();
    // Displaying the page triggers batch 1.
    $this->drupalGet('batch-test/finish-redirect');
    $this->assertBatchMessages($this->_resultMessages('batch1'));
    $this->assertEquals($this->_resultStack('batch1'), $batch_test_helper->stack(), 'Execution order was correct.');
    // Verify that the custom redirection after batch execution displays the
    // correct page.
    $this->assertSession()->pageTextContains('Test page text.');
    $this->assertSession()->addressEquals(Url::fromRoute('test_page_test.test_page'));
  }

  /**
   * Tests batches defined in a form submit handler.
   */
  public function testBatchForm(): void {
    $batch_test_helper = new BatchTestHelper();
    // Batch 0: no operation.
    $edit = ['batch' => 'batch0'];
    $this->drupalGet('batch-test');
    $this->submitForm($edit, 'Submit');
    // If there is any escaped markup it will include at least an escaped '<'
    // character, so assert on each page that there is no escaped '<' as a way
    // of verifying that no markup is incorrectly escaped.
    $this->assertSession()->assertNoEscaped('<');
    $this->assertBatchMessages($this->_resultMessages('batch0'));
    $this->assertSession()->pageTextContains('Redirection successful.');

    // Batch 1: several simple operations.
    $edit = ['batch' => 'batch1'];
    $this->drupalGet('batch-test');
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->assertNoEscaped('<');
    $this->assertBatchMessages($this->_resultMessages('batch1'));
    $this->assertEquals($this->_resultStack('batch1'), $batch_test_helper->stack(), 'Execution order was correct.');
    $this->assertSession()->pageTextContains('Redirection successful.');

    // Batch 2: one multistep operation.
    $edit = ['batch' => 'batch2'];
    $this->drupalGet('batch-test');
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->assertNoEscaped('<');
    $this->assertBatchMessages($this->_resultMessages('batch2'));
    $this->assertEquals($this->_resultStack('batch2'), $batch_test_helper->stack(), 'Execution order was correct.');
    $this->assertSession()->pageTextContains('Redirection successful.');

    // Batch 3: simple + multistep combined.
    $edit = ['batch' => 'batch3'];
    $this->drupalGet('batch-test');
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->assertNoEscaped('<');
    $this->assertBatchMessages($this->_resultMessages('batch3'));
    $this->assertEquals($this->_resultStack('batch3'), $batch_test_helper->stack(), 'Execution order was correct.');
    $this->assertSession()->pageTextContains('Redirection successful.');

    // Batch 4: nested batch.
    $edit = ['batch' => 'batch4'];
    $this->drupalGet('batch-test');
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->assertNoEscaped('<');
    $this->assertBatchMessages($this->_resultMessages('batch4'));
    $this->assertEquals($this->_resultStack('batch4'), $batch_test_helper->stack(), 'Execution order was correct.');
    $this->assertSession()->pageTextContains('Redirection successful.');

    // Submit batches 4 and 7. Batch 4 will trigger batch 2. Batch 7 will
    // trigger batches 6 and 5.
    $edit = ['batch' => ['batch4', 'batch7']];
    $this->drupalGet('batch-test');
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->assertNoEscaped('<');
    $this->assertSession()->responseContains('Redirection successful.');
    $this->assertBatchMessages($this->_resultMessages('batch4'));
    $this->assertBatchMessages($this->_resultMessages('batch7'));
    $expected_stack = array_merge($this->_resultStack('batch4'), $this->_resultStack('batch7'));
    $this->assertEquals($expected_stack, $batch_test_helper->stack(), 'Execution order was correct.');
    $batch = \Drupal::state()->get('batch_test_nested_order_multiple_batches');
    $this->assertCount(5, $batch['sets']);
    // Ensure correct queue mapping.
    foreach ($batch['sets'] as $index => $batch_set) {
      $this->assertEquals('drupal_batch:' . $batch['id'] . ':' . $index, $batch_set['queue']['name']);
    }
    // Ensure correct order of the nested batches. We reset the indexes in
    // order to directly access the batches by their order.
    $batch_sets = array_values($batch['sets']);
    $this->assertEquals('batch4', $batch_sets[0]['batch_test_id']);
    $this->assertEquals('batch2', $batch_sets[1]['batch_test_id']);
    $this->assertEquals('batch7', $batch_sets[2]['batch_test_id']);
    $this->assertEquals('batch6', $batch_sets[3]['batch_test_id']);
    $this->assertEquals('batch5', $batch_sets[4]['batch_test_id']);
  }

  /**
   * Tests batches defined in a multistep form.
   */
  public function testBatchFormMultistep(): void {
    $batch_test_helper = new BatchTestHelper();
    $this->drupalGet('batch-test/multistep');
    $this->assertSession()->assertNoEscaped('<');
    $this->assertSession()->pageTextContains('step 1');

    // First step triggers batch 1.
    $this->submitForm([], 'Submit');
    $this->assertBatchMessages($this->_resultMessages('batch1'));
    $this->assertEquals($this->_resultStack('batch1'), $batch_test_helper->stack(), 'Execution order was correct.');
    $this->assertSession()->pageTextContains('step 2');
    $this->assertSession()->assertNoEscaped('<');

    // Second step triggers batch 2.
    $this->submitForm([], 'Submit');
    $this->assertBatchMessages($this->_resultMessages('batch2'));
    $this->assertEquals($this->_resultStack('batch2'), $batch_test_helper->stack(), 'Execution order was correct.');
    $this->assertSession()->pageTextContains('Redirection successful.');
    $this->assertSession()->assertNoEscaped('<');

    // Extra query arguments will trigger logic that will add them to the
    // redirect URL. Make sure they are persisted.
    $this->drupalGet('batch-test/multistep', ['query' => ['big_tree' => 'small_axe']]);
    $this->submitForm([], 'Submit');
    $this->assertSession()->pageTextContains('step 2');
    $this->assertStringContainsString('batch-test/multistep?big_tree=small_axe', $this->getUrl(), 'Query argument was persisted and another extra argument was added.');
  }

  /**
   * Tests batches defined in different submit handlers on the same form.
   */
  public function testBatchFormMultipleBatches(): void {
    $batch_test_helper = new BatchTestHelper();
    // Batches 1, 2 and 3 are triggered in sequence by different submit
    // handlers. Each submit handler modify the submitted 'value'.
    $value = rand(0, 255);
    $edit = ['value' => $value];
    $this->drupalGet('batch-test/chained');
    $this->submitForm($edit, 'Submit');
    // Check that result messages are present and in the correct order.
    $this->assertBatchMessages($this->_resultMessages('chained'));
    // The stack contains execution order of batch callbacks and submit
    // handlers and logging of corresponding $form_state->getValues().
    $this->assertEquals($this->_resultStack('chained', $value), $batch_test_helper->stack(), 'Execution order was correct, and $form_state is correctly persisted.');
    $this->assertSession()->pageTextContains('Redirection successful.');
  }

  /**
   * Tests batches defined in a programmatically submitted form.
   *
   * Same as above, but the form is submitted through drupal_form_execute().
   */
  public function testBatchFormProgrammatic(): void {
    $batch_test_helper = new BatchTestHelper();
    // Batches 1, 2 and 3 are triggered in sequence by different submit
    // handlers. Each submit handler modify the submitted 'value'.
    $value = rand(0, 255);
    $this->drupalGet('batch-test/programmatic/' . $value);
    // Check that result messages are present and in the correct order.
    $this->assertBatchMessages($this->_resultMessages('chained'));
    // The stack contains execution order of batch callbacks and submit
    // handlers and logging of corresponding $form_state->getValues().
    $this->assertEquals($this->_resultStack('chained', $value), $batch_test_helper->stack(), 'Execution order was correct, and $form_state is correctly persisted.');
    $this->assertSession()->pageTextContains('Got out of a programmatic batched form.');
  }

  /**
   * Tests form submission during a batch operation.
   */
  public function testDrupalFormSubmitInBatch(): void {
    $batch_test_helper = new BatchTestHelper();
    // Displaying the page triggers a batch that programmatically submits a
    // form.
    $value = rand(0, 255);
    $this->drupalGet('batch-test/nested-programmatic/' . $value);
    $this->assertEquals(['mock form submitted with value = ' . $value], $batch_test_helper->stack(), '\\Drupal::formBuilder()->submitForm() ran successfully within a batch operation.');
  }

  /**
   * Tests batches that return $context['finished'] > 1 do in fact complete.
   *
   * @see https://www.drupal.org/node/600836
   */
  public function testBatchLargePercentage(): void {
    $batch_test_helper = new BatchTestHelper();
    // Displaying the page triggers batch 5.
    $this->drupalGet('batch-test/large-percentage');
    $this->assertBatchMessages($this->_resultMessages('batch5'));
    $this->assertEquals($this->_resultStack('batch5'), $batch_test_helper->stack(), 'Execution order was correct.');
    $this->assertSession()->pageTextContains('Redirection successful.');
  }

  /**
   * Triggers a pass if the texts were found in order in the raw content.
   *
   * @param array $texts
   *   Array of raw strings to look for.
   *
   * @internal
   */
  public function assertBatchMessages(array $texts): void {
    $pattern = '|' . implode('.*', $texts) . '|s';
    $this->assertSession()->responseMatches($pattern);
  }

  /**
   * Returns expected execution stacks for the test batches.
   */
  public function _resultStack($id, $value = 0) {
    $stack = [];
    switch ($id) {
      case 'batch1':
        for ($i = 1; $i <= 10; $i++) {
          $stack[] = "op 1 id $i";
        }
        break;

      case 'batch2':
        for ($i = 1; $i <= 10; $i++) {
          $stack[] = "op 2 id $i";
        }
        break;

      case 'batch3':
        for ($i = 1; $i <= 5; $i++) {
          $stack[] = "op 1 id $i";
        }
        for ($i = 1; $i <= 5; $i++) {
          $stack[] = "op 2 id $i";
        }
        for ($i = 6; $i <= 10; $i++) {
          $stack[] = "op 1 id $i";
        }
        for ($i = 6; $i <= 10; $i++) {
          $stack[] = "op 2 id $i";
        }
        break;

      case 'batch4':
        for ($i = 1; $i <= 5; $i++) {
          $stack[] = "op 1 id $i";
        }
        $stack[] = 'setting up batch 2';
        for ($i = 6; $i <= 10; $i++) {
          $stack[] = "op 1 id $i";
        }
        $stack = array_merge($stack, $this->_resultStack('batch2'));
        break;

      case 'batch5':
        for ($i = 1; $i <= 10; $i++) {
          $stack[] = "op 5 id $i";
        }
        break;

      case 'batch6':
        for ($i = 1; $i <= 10; $i++) {
          $stack[] = "op 6 id $i";
        }
        break;

      case 'batch7':
        for ($i = 1; $i <= 5; $i++) {
          $stack[] = "op 7 id $i";
        }
        $stack[] = 'setting up batch 6';
        $stack[] = 'setting up batch 5';
        for ($i = 6; $i <= 10; $i++) {
          $stack[] = "op 7 id $i";
        }
        $stack = array_merge($stack, $this->_resultStack('batch6'));
        $stack = array_merge($stack, $this->_resultStack('batch5'));
        break;

      case 'chained':
        $stack[] = 'submit handler 1';
        $stack[] = 'value = ' . $value;
        $stack = array_merge($stack, $this->_resultStack('batch1'));
        $stack[] = 'submit handler 2';
        $stack[] = 'value = ' . ($value + 1);
        $stack = array_merge($stack, $this->_resultStack('batch2'));
        $stack[] = 'submit handler 3';
        $stack[] = 'value = ' . ($value + 2);
        $stack[] = 'submit handler 4';
        $stack[] = 'value = ' . ($value + 3);
        $stack = array_merge($stack, $this->_resultStack('batch3'));
        break;
    }
    return $stack;
  }

  /**
   * Returns expected result messages for the test batches.
   */
  public function _resultMessages($id) {
    $messages = [];

    // The elapsed time should be either in minutes and seconds or only seconds.
    $pattern_elapsed = ' \((\d+ mins? )?\d+ secs?\)';
    switch ($id) {
      case 'batch0':
        $messages[] = 'results for batch 0' . $pattern_elapsed . '<div class="item-list"><ul><li>none</li></ul></div>';
        break;

      case 'batch1':
        $messages[] = 'results for batch 1' . $pattern_elapsed . '<div class="item-list"><ul><li>op 1: processed 10 elements</li></ul></div>';
        break;

      case 'batch2':
        $messages[] = 'results for batch 2' . $pattern_elapsed . '<div class="item-list"><ul><li>op 2: processed 10 elements</li></ul></div>';
        break;

      case 'batch3':
        $messages[] = 'results for batch 3' . $pattern_elapsed . '<div class="item-list"><ul><li>op 1: processed 10 elements</li><li>op 2: processed 10 elements</li></ul></div>';
        break;

      case 'batch4':
        $messages[] = 'results for batch 4' . $pattern_elapsed . '<div class="item-list"><ul><li>op 1: processed 10 elements</li></ul></div>';
        $messages = array_merge($messages, $this->_resultMessages('batch2'));
        break;

      case 'batch5':
        $messages[] = 'results for batch 5' . $pattern_elapsed . '<div class="item-list"><ul><li>op 5: processed 10 elements</li></ul></div>';
        break;

      case 'batch6':
        $messages[] = 'results for batch 6' . $pattern_elapsed . '<div class="item-list"><ul><li>op 6: processed 10 elements</li></ul></div>';
        break;

      case 'batch7':
        $messages[] = 'results for batch 7' . $pattern_elapsed . '<div class="item-list"><ul><li>op 7: processed 10 elements</li></ul></div>';
        $messages = array_merge($messages, $this->_resultMessages('batch6'));
        $messages = array_merge($messages, $this->_resultMessages('batch5'));
        break;

      case 'chained':
        $messages = array_merge($messages, $this->_resultMessages('batch1'));
        $messages = array_merge($messages, $this->_resultMessages('batch2'));
        $messages = array_merge($messages, $this->_resultMessages('batch3'));
        break;
    }
    return $messages;
  }

}
