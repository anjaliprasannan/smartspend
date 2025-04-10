<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate\Kernel;

use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Database\StatementInterface;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\Core\Database\Database;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Tests the functionality of SqlBase.
 *
 * @group migrate
 */
class SqlBaseTest extends MigrateTestBase {

  /**
   * The (probably mocked) migration under test.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migration;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->migration = $this->createMock(MigrationInterface::class);
    $this->migration->method('id')->willReturn('foo');
  }

  /**
   * Tests different connection types.
   */
  public function testConnectionTypes(): void {
    $sql_base = new TestSqlBase([], $this->migration);

    // Verify that falling back to the default 'migrate' connection (defined in
    // the base class) works.
    $this->assertSame('default', $sql_base->getDatabase()->getTarget());
    $this->assertSame('migrate', $sql_base->getDatabase()->getKey());

    // Verify the fallback state key overrides the 'migrate' connection.
    $target = 'test_fallback_target';
    $key = 'test_fallback_key';
    $config = ['target' => $target, 'key' => $key];
    $database_state_key = 'test_fallback_state';
    \Drupal::state()->set($database_state_key, $config);
    \Drupal::state()->set('migrate.fallback_state_key', $database_state_key);
    // Create a test connection using the default database configuration.
    Database::addConnectionInfo($key, $target, Database::getConnectionInfo('default')['default']);
    $this->assertSame($sql_base->getDatabase()->getTarget(), $target);
    $this->assertSame($sql_base->getDatabase()->getKey(), $key);

    // Verify that setting explicit connection information overrides fallbacks.
    $target = 'test_db_target';
    $key = 'test_migrate_connection';
    $config = ['target' => $target, 'key' => $key];
    $sql_base->setConfiguration($config);
    Database::addConnectionInfo($key, $target, Database::getConnectionInfo('default')['default']);

    // Validate we have injected our custom key and target.
    $this->assertSame($sql_base->getDatabase()->getTarget(), $target);
    $this->assertSame($sql_base->getDatabase()->getKey(), $key);

    // Now test we can have SqlBase create the connection from an info array.
    $sql_base = new TestSqlBase([], $this->migration);

    $target = 'test_db_target2';
    $key = 'test_migrate_connection2';
    $database = Database::getConnectionInfo('default')['default'];
    $config = ['target' => $target, 'key' => $key, 'database' => $database];
    $sql_base->setConfiguration($config);

    // Call getDatabase() to get the connection defined.
    $sql_base->getDatabase();

    // Validate the connection has been created with the right values.
    $this->assertSame(Database::getConnectionInfo($key)[$target], $database);

    // Now, test this all works when using state to store db info.
    $target = 'test_state_db_target';
    $key = 'test_state_migrate_connection';
    $config = ['target' => $target, 'key' => $key];
    $database_state_key = 'migrate_sql_base_test';
    \Drupal::state()->set($database_state_key, $config);
    $sql_base->setConfiguration(['database_state_key' => $database_state_key]);
    Database::addConnectionInfo($key, $target, Database::getConnectionInfo('default')['default']);

    // Validate we have injected our custom key and target.
    $this->assertSame($sql_base->getDatabase()->getTarget(), $target);
    $this->assertSame($sql_base->getDatabase()->getKey(), $key);

    // Now test we can have SqlBase create the connection from an info array.
    $sql_base = new TestSqlBase([], $this->migration);

    $target = 'test_state_db_target2';
    $key = 'test_state_migrate_connection2';
    $database = Database::getConnectionInfo('default')['default'];
    $config = ['target' => $target, 'key' => $key, 'database' => $database];
    $database_state_key = 'migrate_sql_base_test2';
    \Drupal::state()->set($database_state_key, $config);
    $sql_base->setConfiguration(['database_state_key' => $database_state_key]);

    // Call getDatabase() to get the connection defined.
    $sql_base->getDatabase();

    // Validate the connection has been created with the right values.
    $this->assertSame(Database::getConnectionInfo($key)[$target], $database);

    // Verify that falling back to 'migrate' when the connection is not defined
    // throws a RequirementsException.
    \Drupal::state()->delete('migrate.fallback_state_key');
    $sql_base->setConfiguration([]);
    Database::renameConnection('migrate', 'fallback_connection');
    $this->expectException(RequirementsException::class);
    $this->expectExceptionMessage('No database connection configured for source plugin');
    $sql_base->getDatabase();
  }

  /**
   * Tests the exception when a connection is defined but not available.
   */
  public function testBrokenConnection(): void {
    if (Database::getConnection()->driver() === 'sqlite') {
      $this->markTestSkipped('Not compatible with sqlite');
    }

    $sql_base = new TestSqlBase([], $this->migration);
    $target = 'test_state_db_target2';
    $key = 'test_state_migrate_connection2';
    $database = Database::getConnectionInfo('default')['default'];
    $database['database'] = 'godot';
    $config = ['target' => $target, 'key' => $key, 'database' => $database];
    $database_state_key = 'migrate_sql_base_test2';
    \Drupal::state()->set($database_state_key, $config);
    $sql_base->setConfiguration(['database_state_key' => $database_state_key]);

    // Call checkRequirements(): it will call getDatabase() and convert the
    // exception to a RequirementsException.
    $this->expectException(RequirementsException::class);
    $this->expectExceptionMessage('No database connection available for source plugin sql_base');
    $sql_base->checkRequirements();
  }

  /**
   * Tests that SqlBase respects high-water values.
   *
   * @param mixed $high_water
   *   (optional) The high-water value to set.
   * @param array $query_result
   *   (optional) The expected query results.
   *
   * @dataProvider highWaterDataProvider
   */
  public function testHighWater($high_water = NULL, array $query_result = []): void {
    $configuration = [
      'high_water_property' => [
        'name' => 'order',
      ],
    ];
    $source = new TestSqlBase($configuration, $this->migration);

    if ($high_water) {
      \Drupal::keyValue('migrate:high_water')->set($this->migration->id(), $high_water);
    }

    $statement = $this->createMock(StatementInterface::class);
    $statement->expects($this->atLeastOnce())->method('setFetchMode')->with(FetchAs::Associative);
    $query = $this->createMock(SelectInterface::class);
    $query->method('execute')->willReturn($statement);
    $query->expects($this->atLeastOnce())->method('orderBy')->with('order', 'ASC');

    $condition_group = $this->createMock(ConditionInterface::class);
    $query->method('orConditionGroup')->willReturn($condition_group);

    $source->setQuery($query);
    $source->rewind();
  }

  /**
   * Data provider for ::testHighWater().
   *
   * @return array
   *   The scenarios to test.
   */
  public static function highWaterDataProvider() {
    return [
      'no high-water value set' => [],
      'high-water value set' => [33],
    ];
  }

  /**
   * Tests prepare query method.
   */
  public function testPrepareQuery(): void {
    $this->prepareSourceData();
    $this->enableModules(['migrate_sql_prepare_query_test', 'entity_test']);

    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->container->get('plugin.manager.migration')
      ->createStubMigration([
        'source' => ['plugin' => 'test_sql_prepare_query'],
        'process' => ['id' => 'id', 'name' => 'name'],
        'destination' => ['plugin' => 'entity:entity_test'],
      ]);

    // One item is excluded by the condition defined in the source plugin.
    // @see \Drupal\migrate_sql_prepare_query_test\Plugin\migrate\source\TestSqlPrepareQuery
    $count = $migration->getSourcePlugin()->count();
    $this->assertEquals(2, $count);

    // Run the migration and verify that the number of migrated items matches
    // the initial source count.
    (new MigrateExecutable($migration, new MigrateMessage()))->import();
    $this->assertEquals(2, $migration->getIdMap()->processedCount());
  }

  /**
   * Creates a custom source table and some sample data.
   */
  protected function prepareSourceData(): void {
    $this->sourceDatabase->schema()->createTable('migrate_source_test', [
      'fields' => [
        'id' => ['type' => 'int'],
        'name' => ['type' => 'varchar', 'length' => 32],
      ],
    ]);

    // Add some data in the table.
    $this->sourceDatabase->insert('migrate_source_test')
      ->fields(['id', 'name'])
      ->values(['id' => 1, 'name' => 'foo'])
      ->values(['id' => 2, 'name' => 'bar'])
      ->values(['id' => 3, 'name' => 'baz'])
      ->execute();
  }

}

/**
 * A dummy source to help with testing SqlBase.
 *
 * @package Drupal\migrate\Plugin\migrate\source
 */
class TestSqlBase extends SqlBase {

  /**
   * The query to execute.
   *
   * @var \Drupal\Core\Database\Query\SelectInterface
   */
  protected $query;

  /**
   * Overrides the constructor so we can create one easily.
   *
   * @param array $configuration
   *   The plugin instance configuration.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   (optional) The migration being run.
   */
  public function __construct(array $configuration = [], ?MigrationInterface $migration = NULL) {
    parent::__construct($configuration, 'sql_base', ['requirements_met' => TRUE], $migration, \Drupal::state());
  }

  /**
   * Gets the database without caching it.
   */
  public function getDatabase() {
    $this->database = NULL;
    return parent::getDatabase();
  }

  /**
   * Allows us to set the configuration from a test.
   *
   * @param array $config
   *   The config array.
   */
  public function setConfiguration($config): void {
    $this->configuration = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    throw new \RuntimeException(__METHOD__ . " not implemented for " . __CLASS__);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->query;
  }

  /**
   * Sets the query to execute.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The query to execute.
   */
  public function setQuery(SelectInterface $query): void {
    $this->query = $query;
  }

}
