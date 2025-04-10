<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests the Entity Query Aggregation API.
 *
 * @group Entity
 * @see \Drupal\entity_test\Entity\EntityTest
 */
class EntityQueryAggregateTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field_test'];

  /**
   * The entity_test storage to create the test entities.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * The actual query result, to compare later.
   *
   * @var array
   */
  protected $queryResult;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityStorage = $this->entityTypeManager->getStorage('entity_test');

    // Add some fieldapi fields to be used in the test.
    for ($i = 1; $i <= 2; $i++) {
      $field_name = 'field_test_' . $i;
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'entity_test',
        'type' => 'integer',
        'cardinality' => 2,
      ])->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'entity_test',
        'bundle' => 'entity_test',
      ])->save();
    }

    $entity = $this->entityStorage->create([
      'id' => 1,
      'user_id' => 1,
      'field_test_1' => 1,
      'field_test_2' => 2,
    ]);
    $entity->enforceIsNew();
    $entity->save();

    $entity = $this->entityStorage->create([
      'id' => 2,
      'user_id' => 2,
      'field_test_1' => 1,
      'field_test_2' => 7,
    ]);
    $entity->enforceIsNew();
    $entity->save();
    $entity = $this->entityStorage->create([
      'id' => 3,
      'user_id' => 2,
      'field_test_1' => 2,
      'field_test_2' => 1,
    ]);
    $entity->enforceIsNew();
    $entity->save();
    $entity = $this->entityStorage->create([
      'id' => 4,
      'user_id' => 2,
      'field_test_1' => 2,
      'field_test_2' => 8,
    ]);
    $entity->enforceIsNew();
    $entity->save();
    $entity = $this->entityStorage->create([
      'id' => 5,
      'user_id' => 3,
      'field_test_1' => 2,
      'field_test_2' => 2,
    ]);
    $entity->enforceIsNew();
    $entity->save();
    $entity = $this->entityStorage->create([
      'id' => 6,
      'user_id' => 3,
      'field_test_1' => 3,
      'field_test_2' => 8,
    ]);
    $entity->enforceIsNew();
    $entity->save();

  }

  /**
   * Tests aggregation support.
   */
  public function testAggregation(): void {
    // Apply a simple group by.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('user_id')
      ->execute();

    $this->assertResults([
      ['user_id' => 1],
      ['user_id' => 2],
      ['user_id' => 3],
    ]);

    $function_expected = [];
    $function_expected['count'] = [['id_count' => 6]];
    $function_expected['min'] = [['id_min' => 1]];
    $function_expected['max'] = [['id_max' => 6]];
    $function_expected['sum'] = [['id_sum' => 21]];
    $function_expected['avg'] = [['id_avg' => (21.0 / 6.0)]];

    // Apply a simple aggregation for different aggregation functions.
    foreach ($function_expected as $aggregation_function => $expected) {
      $query = $this->entityStorage->getAggregateQuery()
        ->accessCheck(FALSE)
        ->aggregate('id', $aggregation_function);
      $this->queryResult = $query->execute();
      // We need to check that a character exists before and after the table,
      // column and alias identifiers. These would be the quote characters
      // specific for each database system.
      $this->assertMatchesRegularExpression('/' . $aggregation_function . '\(.*entity_test.\..id.\).* AS .id_' . $aggregation_function . './', (string) $query, 'The argument to the aggregation function should be a quoted field.');
      $this->assertEquals($expected, $this->queryResult);
    }

    // Apply aggregation and group by on the same query.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('id', 'COUNT')
      ->groupBy('user_id')
      ->execute();
    $this->assertResults([
      ['user_id' => 1, 'id_count' => 1],
      ['user_id' => 2, 'id_count' => 3],
      ['user_id' => 3, 'id_count' => 2],
    ]);

    // Apply aggregation and a condition which matches.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('id', 'COUNT')
      ->groupBy('id')
      ->conditionAggregate('id', 'COUNT', 8)
      ->execute();
    $this->assertResults([]);

    // Don't call aggregate to test the implicit aggregate call.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('id')
      ->conditionAggregate('id', 'COUNT', 8)
      ->execute();
    $this->assertResults([]);

    // Apply aggregation and a condition which matches.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('id', 'count')
      ->groupBy('id')
      ->conditionAggregate('id', 'COUNT', 6)
      ->execute();
    $this->assertResults([['id_count' => 6]]);

    // Apply aggregation, a group by and a condition which matches partially via
    // the operator '='.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('id', 'count')
      ->conditionAggregate('id', 'count', 2)
      ->groupBy('user_id')
      ->execute();
    $this->assertResults([['id_count' => 2, 'user_id' => 3]]);

    // Apply aggregation, a group by and a condition which matches partially via
    // the operator '>'.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('id', 'count')
      ->conditionAggregate('id', 'COUNT', 1, '>')
      ->groupBy('user_id')
      ->execute();
    $this->assertResults([
      ['id_count' => 2, 'user_id' => 3],
      ['id_count' => 3, 'user_id' => 2],
    ]);

    // Apply aggregation and a sort. This might not be useful, but have a proper
    // test coverage.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('id', 'COUNT')
      ->sortAggregate('id', 'COUNT')
      ->execute();
    $this->assertSortedResults([['id_count' => 6]]);

    // Don't call aggregate to test the implicit aggregate call.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->sortAggregate('id', 'COUNT')
      ->execute();
    $this->assertSortedResults([['id_count' => 6]]);

    // Apply aggregation, group by and a sort descending.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('id', 'COUNT')
      ->groupBy('user_id')
      ->sortAggregate('id', 'COUNT', 'DESC')
      ->execute();
    $this->assertSortedResults([
      ['user_id' => 2, 'id_count' => 3],
      ['user_id' => 3, 'id_count' => 2],
      ['user_id' => 1, 'id_count' => 1],
    ]);

    // Apply aggregation, group by and a sort ascending.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('id', 'COUNT')
      ->groupBy('user_id')
      ->sortAggregate('id', 'COUNT', 'ASC')
      ->execute();
    $this->assertSortedResults([
      ['user_id' => 1, 'id_count' => 1],
      ['user_id' => 3, 'id_count' => 2],
      ['user_id' => 2, 'id_count' => 3],
    ]);

    // Apply aggregation, group by, an aggregation condition and a sort with the
    // operator '='.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('id', 'COUNT')
      ->groupBy('user_id')
      ->sortAggregate('id', 'COUNT')
      ->conditionAggregate('id', 'COUNT', 2)
      ->execute();
    $this->assertSortedResults([['id_count' => 2, 'user_id' => 3]]);

    // Apply aggregation, group by, an aggregation condition and a sort with the
    // operator '<' and order ASC.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('id', 'COUNT')
      ->groupBy('user_id')
      ->sortAggregate('id', 'COUNT', 'ASC')
      ->conditionAggregate('id', 'COUNT', 3, '<')
      ->execute();
    $this->assertSortedResults([
      ['id_count' => 1, 'user_id' => 1],
      ['id_count' => 2, 'user_id' => 3],
    ]);

    // Apply aggregation, group by, an aggregation condition and a sort with the
    // operator '<' and order DESC.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('id', 'COUNT')
      ->groupBy('user_id')
      ->sortAggregate('id', 'COUNT', 'DESC')
      ->conditionAggregate('id', 'COUNT', 3, '<')
      ->execute();
    $this->assertSortedResults([
      ['id_count' => 2, 'user_id' => 3],
      ['id_count' => 1, 'user_id' => 1],
    ]);

    // Test aggregation/group by support for fieldapi fields.

    // Just group by a fieldapi field.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('field_test_1')
      ->execute();
    $this->assertResults([
      ['field_test_1' => 1],
      ['field_test_1' => 2],
      ['field_test_1' => 3],
    ]);

    // Group by a fieldapi field and aggregate a normal property.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('user_id', 'COUNT')
      ->groupBy('field_test_1')
      ->execute();

    $this->assertResults([
      ['field_test_1' => 1, 'user_id_count' => 2],
      ['field_test_1' => 2, 'user_id_count' => 3],
      ['field_test_1' => 3, 'user_id_count' => 1],
    ]);

    // Group by a normal property and aggregate a fieldapi field.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('field_test_1', 'COUNT')
      ->groupBy('user_id')
      ->execute();

    $this->assertResults([
      ['user_id' => 1, 'field_test_1_count' => 1],
      ['user_id' => 2, 'field_test_1_count' => 3],
      ['user_id' => 3, 'field_test_1_count' => 2],
    ]);

    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('field_test_1', 'SUM')
      ->groupBy('user_id')
      ->execute();
    $this->assertResults([
      ['user_id' => 1, 'field_test_1_sum' => 1],
      ['user_id' => 2, 'field_test_1_sum' => 5],
      ['user_id' => 3, 'field_test_1_sum' => 5],
    ]);

    // Aggregate by two different fieldapi fields.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('field_test_1', 'SUM')
      ->aggregate('field_test_2', 'SUM')
      ->groupBy('user_id')
      ->execute();
    $this->assertResults([
      ['user_id' => 1, 'field_test_1_sum' => 1, 'field_test_2_sum' => 2],
      ['user_id' => 2, 'field_test_1_sum' => 5, 'field_test_2_sum' => 16],
      ['user_id' => 3, 'field_test_1_sum' => 5, 'field_test_2_sum' => 10],
    ]);

    // This time aggregate the same field twice.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('field_test_1', 'SUM')
      ->aggregate('field_test_1', 'COUNT')
      ->groupBy('user_id')
      ->execute();
    $this->assertResults([
      ['user_id' => 1, 'field_test_1_sum' => 1, 'field_test_1_count' => 1],
      ['user_id' => 2, 'field_test_1_sum' => 5, 'field_test_1_count' => 3],
      ['user_id' => 3, 'field_test_1_sum' => 5, 'field_test_1_count' => 2],
    ]);

    // Group by and aggregate by a fieldapi field.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('field_test_1')
      ->aggregate('field_test_2', 'COUNT')
      ->execute();
    $this->assertResults([
      ['field_test_1' => 1, 'field_test_2_count' => 2],
      ['field_test_1' => 2, 'field_test_2_count' => 3],
      ['field_test_1' => 3, 'field_test_2_count' => 1],
    ]);

    // Group by and aggregate by a fieldapi field and use multiple aggregate
    // functions.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('field_test_1')
      ->aggregate('field_test_2', 'COUNT')
      ->aggregate('field_test_2', 'SUM')
      ->execute();
    $this->assertResults([
      ['field_test_1' => 1, 'field_test_2_count' => 2, 'field_test_2_sum' => 9],
      ['field_test_1' => 2, 'field_test_2_count' => 3, 'field_test_2_sum' => 11],
      ['field_test_1' => 3, 'field_test_2_count' => 1, 'field_test_2_sum' => 8],
    ]);

    // Apply an aggregate condition for a fieldapi field and group by a simple
    // property.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->conditionAggregate('field_test_1', 'COUNT', 3)
      ->groupBy('user_id')
      ->execute();
    $this->assertResults([
      ['user_id' => 2, 'field_test_1_count' => 3],
      ['user_id' => 3, 'field_test_1_count' => 2],
    ]);

    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('field_test_1', 'SUM')
      ->conditionAggregate('field_test_1', 'COUNT', 2, '>')
      ->groupBy('user_id')
      ->execute();
    $this->assertResults([
      ['user_id' => 2, 'field_test_1_sum' => 5, 'field_test_1_count' => 3],
      ['user_id' => 3, 'field_test_1_sum' => 5, 'field_test_1_count' => 2],
    ]);

    // Apply an aggregate condition for a simple property and a group by a
    // fieldapi field.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->conditionAggregate('user_id', 'COUNT', 2)
      ->groupBy('field_test_1')
      ->execute();
    $this->assertResults([
      ['field_test_1' => 1, 'user_id_count' => 2],
    ]);

    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->conditionAggregate('user_id', 'COUNT', 2, '>')
      ->groupBy('field_test_1')
      ->execute();
    $this->assertResults([
      ['field_test_1' => 1, 'user_id_count' => 2],
      ['field_test_1' => 2, 'user_id_count' => 3],
    ]);

    // Apply an aggregate condition and a group by fieldapi fields.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('field_test_1')
      ->conditionAggregate('field_test_2', 'COUNT', 2)
      ->execute();
    $this->assertResults([
      ['field_test_1' => 1, 'field_test_2_count' => 2],
    ]);
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('field_test_1')
      ->conditionAggregate('field_test_2', 'COUNT', 2, '>')
      ->execute();
    $this->assertResults([
      ['field_test_1' => 1, 'field_test_2_count' => 2],
      ['field_test_1' => 2, 'field_test_2_count' => 3],
    ]);

    // Apply an aggregate condition and a group by fieldapi fields with multiple
    // conditions via AND.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('field_test_1')
      ->conditionAggregate('field_test_2', 'COUNT', 2)
      ->conditionAggregate('field_test_2', 'SUM', 8)
      ->execute();
    $this->assertResults([]);

    // Apply an aggregate condition and a group by fieldapi fields with multiple
    // conditions via OR.
    $this->queryResult = $this->entityStorage->getAggregateQuery('OR')
      ->accessCheck(FALSE)
      ->groupBy('field_test_1')
      ->conditionAggregate('field_test_2', 'COUNT', 2)
      ->conditionAggregate('field_test_2', 'SUM', 8)
      ->execute();
    $this->assertResults([
      ['field_test_1' => 1, 'field_test_2_count' => 2, 'field_test_2_sum' => 9],
      ['field_test_1' => 3, 'field_test_2_count' => 1, 'field_test_2_sum' => 8],
    ]);

    // Group by a normal property and aggregate a fieldapi field and sort by the
    // group by field.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('field_test_1', 'COUNT')
      ->groupBy('user_id')
      ->sort('user_id', 'DESC')
      ->execute();
    $this->assertSortedResults([
      ['user_id' => 3, 'field_test_1_count' => 2],
      ['user_id' => 2, 'field_test_1_count' => 3],
      ['user_id' => 1, 'field_test_1_count' => 1],
    ]);

    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('field_test_1', 'COUNT')
      ->groupBy('user_id')
      ->sort('user_id', 'ASC')
      ->execute();
    $this->assertSortedResults([
      ['user_id' => 1, 'field_test_1_count' => 1],
      ['user_id' => 2, 'field_test_1_count' => 3],
      ['user_id' => 3, 'field_test_1_count' => 2],
    ]);

    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->conditionAggregate('field_test_1', 'COUNT', 2, '>')
      ->groupBy('user_id')
      ->sort('user_id', 'ASC')
      ->execute();
    $this->assertSortedResults([
      ['user_id' => 2, 'field_test_1_count' => 3],
      ['user_id' => 3, 'field_test_1_count' => 2],
    ]);

    // Group by a normal property, aggregate a fieldapi field, and sort by the
    // aggregated field.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->sortAggregate('field_test_1', 'COUNT', 'DESC')
      ->groupBy('user_id')
      ->execute();
    $this->assertSortedResults([
      ['user_id' => 2, 'field_test_1_count' => 3],
      ['user_id' => 3, 'field_test_1_count' => 2],
      ['user_id' => 1, 'field_test_1_count' => 1],
    ]);

    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->sortAggregate('field_test_1', 'COUNT', 'ASC')
      ->groupBy('user_id')
      ->execute();
    $this->assertSortedResults([
      ['user_id' => 1, 'field_test_1_count' => 1],
      ['user_id' => 3, 'field_test_1_count' => 2],
      ['user_id' => 2, 'field_test_1_count' => 3],
    ]);

    // Group by and aggregate by fieldapi field, and sort by the group by field.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('field_test_1')
      ->aggregate('field_test_2', 'COUNT')
      ->sort('field_test_1', 'ASC')
      ->execute();
    $this->assertSortedResults([
      ['field_test_1' => 1, 'field_test_2_count' => 2],
      ['field_test_1' => 2, 'field_test_2_count' => 3],
      ['field_test_1' => 3, 'field_test_2_count' => 1],
    ]);

    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('field_test_1')
      ->aggregate('field_test_2', 'COUNT')
      ->sort('field_test_1', 'DESC')
      ->execute();
    $this->assertSortedResults([
      ['field_test_1' => 3, 'field_test_2_count' => 1],
      ['field_test_1' => 2, 'field_test_2_count' => 3],
      ['field_test_1' => 1, 'field_test_2_count' => 2],
    ]);

    // Group by and aggregate by Field API field, and sort by the aggregated
    // field.
    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('field_test_1')
      ->sortAggregate('field_test_2', 'COUNT', 'DESC')
      ->execute();
    $this->assertSortedResults([
      ['field_test_1' => 2, 'field_test_2_count' => 3],
      ['field_test_1' => 1, 'field_test_2_count' => 2],
      ['field_test_1' => 3, 'field_test_2_count' => 1],
    ]);

    $this->queryResult = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('field_test_1')
      ->sortAggregate('field_test_2', 'COUNT', 'ASC')
      ->execute();
    $this->assertSortedResults([
      ['field_test_1' => 3, 'field_test_2_count' => 1],
      ['field_test_1' => 1, 'field_test_2_count' => 2],
      ['field_test_1' => 2, 'field_test_2_count' => 3],
    ]);

  }

  /**
   * Tests preparing a query and executing twice.
   */
  public function testRepeatedExecution(): void {
    $query = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('user_id');

    $this->queryResult = $query->execute();
    $this->assertResults([
      ['user_id' => 1],
      ['user_id' => 2],
      ['user_id' => 3],
    ]);

    $entity = $this->entityStorage->create([
      'id' => 7,
      'user_id' => 4,
      'field_test_1' => 42,
      'field_test_2' => 68,
    ]);
    $entity->enforceIsNew();
    $entity->save();

    $this->queryResult = $query->execute();
    $this->assertResults([
      ['user_id' => 1],
      ['user_id' => 2],
      ['user_id' => 3],
      ['user_id' => 4],
    ]);
  }

  /**
   * Tests that entity query alter hooks are invoked for aggregate queries.
   *
   * Hook functions in field_test.module add additional conditions to the query
   * removing entities with specific ids.
   */
  public function testAlterHook(): void {
    $basicQuery = $this->entityStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('id');

    // Verify assumptions about the unaltered result.
    $query = clone $basicQuery;
    $this->queryResult = $query->execute();
    $this->assertResults([
      ['id' => 1],
      ['id' => 2],
      ['id' => 3],
      ['id' => 4],
      ['id' => 5],
      ['id' => 6],
    ]);

    // field_test_entity_query_alter() removes the entity with id '5'.
    $query = clone $basicQuery;
    $this->queryResult = $query
      // Add a tag that no hook function matches.
      ->addTag('entity_query_alter_hook_test')
      ->execute();
    $this->assertResults([
      ['id' => 1],
      ['id' => 2],
      ['id' => 3],
      ['id' => 4],
      ['id' => 6],
    ]);
  }

  /**
   * Asserts the results as expected regardless of order between and in rows.
   *
   * @param array $expected
   *   An array of the expected results.
   * @param bool $sorted
   *   (optiOnal) Whether the array keys of the expected are sorted, defaults to
   *   FALSE.
   *
   * @internal
   */
  protected function assertResults(array $expected, bool $sorted = FALSE): void {
    $found = TRUE;
    $expected_keys = array_keys($expected);
    foreach ($this->queryResult as $key => $row) {
      $keys = $sorted ? [$key] : $expected_keys;
      foreach ($keys as $key) {
        $expected_row = $expected[$key];
        if (!array_diff_assoc($row, $expected_row) && !array_diff_assoc($expected_row, $row)) {
          continue 2;
        }
      }
      $found = FALSE;
      break;
    }
    $this->assertTrue($found, strtr('!expected expected, !found found', ['!expected' => print_r($expected, TRUE), '!found' => print_r($this->queryResult, TRUE)]));
  }

  /**
   * Asserts the results as expected regardless of order in rows.
   *
   * @param array $expected
   *   An array of the expected results.
   *
   * @internal
   */
  protected function assertSortedResults(array $expected): void {
    $this->assertResults($expected, TRUE);
  }

}
