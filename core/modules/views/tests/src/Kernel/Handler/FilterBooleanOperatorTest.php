<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;

/**
 * Tests the core Drupal\views\Plugin\views\filter\BooleanOperator handler.
 *
 * @group views
 * @see \Drupal\views\Plugin\views\filter\BooleanOperator
 */
class FilterBooleanOperatorTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view'];

  /**
   * Map column names.
   *
   * @var array
   */
  protected $columnMap = [
    'views_test_data_id' => 'id',
  ];

  /**
   * {@inheritdoc}
   */
  protected function dataSet() {
    $dataset = parent::dataSet();
    $dataset[] = [
      'name' => 'Null',
      'age' => 0,
      'job' => 'Null',
      'created' => 0,
      'status' => NULL,
    ];
    return $dataset;
  }

  /**
   * {@inheritdoc}
   */
  protected function schemaDefinition() {
    $schema = parent::schemaDefinition();
    $schema['views_test_data']['fields']['status']['not null'] = FALSE;
    return $schema;
  }

  /**
   * Tests the BooleanOperator filter.
   */
  public function testFilterBooleanOperator(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    // Add a the status boolean filter.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'status' => [
        'id' => 'status',
        'field' => 'status',
        'table' => 'views_test_data',
        'value' => 0,
      ],
    ]);
    $this->executeView($view);

    $expected_result = [
      ['id' => 2],
      ['id' => 4],
    ];

    $this->assertCount(2, $view->result);
    $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);

    $view->destroy();
    $view->setDisplay();

    // Add the status boolean filter.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'status' => [
        'id' => 'status',
        'field' => 'status',
        'table' => 'views_test_data',
        'value' => 1,
      ],
    ]);
    $this->executeView($view);

    $expected_result = [
      ['id' => 1],
      ['id' => 3],
      ['id' => 5],
    ];

    $this->assertCount(3, $view->result);
    $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);

    $view->destroy();
    $view->setDisplay();

    // Testing the same scenario but using the reverse status and operation.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'status' => [
        'id' => 'status',
        'field' => 'status',
        'table' => 'views_test_data',
        'value' => 0,
        'operator' => '!=',
      ],
    ]);
    $this->executeView($view);

    $expected_result = [
      ['id' => 1],
      ['id' => 3],
      ['id' => 5],
    ];

    $this->assertCount(3, $view->result);
    $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);
  }

  /**
   * Tests the BooleanOperator empty/not empty filters.
   */
  public function testEmptyFilterBooleanOperator(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    // Add an "empty" boolean filter on status.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'status' => [
        'id' => 'status',
        'field' => 'status',
        'table' => 'views_test_data',
        'operator' => 'empty',
      ],
    ]);
    $this->executeView($view);

    $expected_result = [
      ['id' => 6],
    ];

    $this->assertCount(1, $view->result);
    $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);

    $view->destroy();
    $view->setDisplay();

    // Add a "not empty" boolean filter on status.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'status' => [
        'id' => 'status',
        'field' => 'status',
        'table' => 'views_test_data',
        'operator' => 'not empty',
      ],
    ]);
    $this->executeView($view);

    $expected_result = [
      ['id' => 1],
      ['id' => 2],
      ['id' => 3],
      ['id' => 4],
      ['id' => 5],
    ];

    $this->assertCount(5, $view->result);
    $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);

    $view->destroy();
  }

  /**
   * Tests the boolean filter with grouped exposed form enabled.
   */
  public function testFilterGroupedExposed(): void {
    $filters = $this->getGroupedExposedFilters();
    $view = Views::getView('test_view');

    $view->setExposedInput(['status' => 1]);
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('filters', $filters);

    $this->executeView($view);

    $expected_result = [
      ['id' => 1],
      ['id' => 3],
      ['id' => 5],
    ];

    $this->assertCount(3, $view->result);
    $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);
    $view->destroy();

    $view->setExposedInput(['status' => 2]);
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('filters', $filters);

    $this->executeView($view);

    $expected_result = [
      ['id' => 2],
      ['id' => 4],
    ];

    $this->assertCount(2, $view->result);
    $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);

    $view->destroy();

    // Expecting the same results as for ['status' => 1].
    $view->setExposedInput(['status' => 3]);
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('filters', $filters);

    $this->executeView($view);

    $expected_result = [
      ['id' => 1],
      ['id' => 3],
      ['id' => 5],
    ];

    $this->assertCount(3, $view->result);
    $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);
  }

  /**
   * Provides grouped exposed filter configuration.
   *
   * @return array
   *   An array of grouped exposed filter configuration.
   */
  protected function getGroupedExposedFilters(): array {
    $filters = [
      'status' => [
        'id' => 'status',
        'table' => 'views_test_data',
        'field' => 'status',
        'relationship' => 'none',
        'exposed' => TRUE,
        'expose' => [
          'operator' => 'status_op',
          'label' => 'status',
          'identifier' => 'status',
        ],
        'is_grouped' => TRUE,
        'group_info' => [
          'label' => 'status',
          'identifier' => 'status',
          'default_group' => 'All',
          'group_items' => [
            1 => [
              'title' => 'Active',
              'operator' => '=',
              'value' => '1',
            ],
            2 => [
              'title' => 'Blocked',
              'operator' => '=',
              'value' => '0',
            ],
            // This group should return the same results as group 1, because it
            // is the negation of group 2.
            3 => [
              'title' => 'Active (reverse)',
              'operator' => '!=',
              'value' => '0',
            ],
          ],
        ],
      ],
    ];
    return $filters;
  }

}
