<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\Query\QueryException;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\EntityTestHelper;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;

/**
 * Tests the Entity Query relationship API.
 *
 * @group Entity
 */
class EntityQueryRelationshipTest extends EntityKernelTestBase {

  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['taxonomy'];

  /**
   * Term entities.
   *
   * @var array
   */
  protected $terms;

  /**
   * User entities.
   *
   * @var array
   */
  public $accounts;

  /**
   * The entity_test entities.
   *
   * @var array
   */
  protected $entities;

  /**
   * The name of the taxonomy field used for test.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The results returned by EntityQuery.
   *
   * @var array
   */
  protected $queryResults;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');

    // We want an entity reference field. It needs a vocabulary, terms, a field
    // storage and a field. First, create the vocabulary.
    $vocabulary = Vocabulary::create([
      'vid' => $this->randomMachineName(),
      'name' => 'Tags',
    ]);
    $vocabulary->save();

    // Second, create the field.
    EntityTestHelper::createBundle('test_bundle');
    $this->fieldName = $this->randomMachineName();
    $handler_settings = [
      'target_bundles' => [
        $vocabulary->id() => $vocabulary->id(),
      ],
      'auto_create' => TRUE,
    ];
    $this->createEntityReferenceField('entity_test', 'test_bundle', $this->fieldName, '', 'taxonomy_term', 'default', $handler_settings);

    // Create two terms and also two accounts.
    for ($i = 0; $i <= 1; $i++) {
      $term = Term::create([
        'name' => $this->randomMachineName(),
        'vid' => $vocabulary->id(),
      ]);
      $term->save();
      $this->terms[] = $term;
      $this->accounts[] = $this->createUser();
    }
    // Create three entity_test entities, the 0th entity will point to the
    // 0th account and 0th term, the 1st and 2nd entity will point to the
    // 1st account and 1st term.
    for ($i = 0; $i <= 2; $i++) {
      $entity = EntityTest::create(['type' => 'test_bundle']);
      $entity->name->value = $this->randomMachineName();
      $index = $i ? 1 : 0;
      $entity->user_id->target_id = $this->accounts[$index]->id();
      $entity->{$this->fieldName}->target_id = $this->terms[$index]->id();
      $entity->save();
      $this->entities[] = $entity;
    }
  }

  /**
   * Tests querying.
   */
  public function testQuery(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('entity_test');
    // This returns the 0th entity as that's the only one pointing to the 0th
    // account.
    $this->queryResults = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition("user_id.entity.name", $this->accounts[0]->getAccountName())
      ->execute();
    $this->assertResults([0]);
    // This returns the 1st and 2nd entity as those point to the 1st account.
    $this->queryResults = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition("user_id.entity.name", $this->accounts[0]->getAccountName(), '<>')
      ->execute();
    $this->assertResults([1, 2]);
    // This returns all three entities because all of them point to an
    // account.
    $this->queryResults = $storage->getQuery()
      ->accessCheck(FALSE)
      ->exists("user_id.entity.name")
      ->execute();
    $this->assertResults([0, 1, 2]);
    // This returns no entities because all of them point to an account.
    $this->queryResults = $storage->getQuery()
      ->accessCheck(FALSE)
      ->notExists("user_id.entity.name")
      ->execute();
    $this->assertCount(0, $this->queryResults);
    // This returns the 0th entity as that's only one pointing to the 0th
    // term (test without specifying the field column).
    $this->queryResults = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition("$this->fieldName.entity.name", $this->terms[0]->name->value)
      ->execute();
    $this->assertResults([0]);
    // This returns the 0th entity as that's only one pointing to the 0th
    // term (test with specifying the column name).
    $this->queryResults = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition("$this->fieldName.target_id.entity.name", $this->terms[0]->name->value)
      ->execute();
    $this->assertResults([0]);
    // This returns the 1st and 2nd entity as those point to the 1st term.
    $this->queryResults = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition("$this->fieldName.entity.name", $this->terms[0]->name->value, '<>')
      ->execute();
    $this->assertResults([1, 2]);
    // This returns the 0th entity as that's only one pointing to the 0th
    // account.
    $this->queryResults = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition("user_id.entity:user.name", $this->accounts[0]->getAccountName())
      ->execute();
    $this->assertResults([0]);
    // This returns the 1st and 2nd entity as those point to the 1st account.
    $this->queryResults = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition("user_id.entity:user.name", $this->accounts[0]->getAccountName(), '<>')
      ->execute();
    $this->assertResults([1, 2]);
    // This returns all three entities because all of them point to an
    // account.
    $this->queryResults = $storage->getQuery()
      ->accessCheck(FALSE)
      ->exists("user_id.entity:user.name")
      ->execute();
    $this->assertResults([0, 1, 2]);
    // This returns no entities because all of them point to an account.
    $this->queryResults = $storage->getQuery()
      ->accessCheck(FALSE)
      ->notExists("user_id.entity:user.name")
      ->execute();
    $this->assertCount(0, $this->queryResults);
    // This returns the 0th entity as that's only one pointing to the 0th
    // term (test without specifying the field column).
    $this->queryResults = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition("$this->fieldName.entity:taxonomy_term.name", $this->terms[0]->name->value)
      ->execute();
    $this->assertResults([0]);
    // This returns the 0th entity as that's only one pointing to the 0th
    // term (test with specifying the column name).
    $this->queryResults = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition("$this->fieldName.target_id.entity:taxonomy_term.name", $this->terms[0]->name->value)
      ->execute();
    $this->assertResults([0]);
    // This returns the 1st and 2nd entity as those point to the 1st term.
    $this->queryResults = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition("$this->fieldName.entity:taxonomy_term.name", $this->terms[0]->name->value, '<>')
      ->execute();
    $this->assertResults([1, 2]);
  }

  /**
   * Tests the invalid specifier in the query relationship.
   */
  public function testInvalidSpecifier(): void {
    $this->expectException(PluginNotFoundException::class);
    $this->container
      ->get('entity_type.manager')
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('langcode.language.foo', 'bar')
      ->execute();
  }

  /**
   * Tests a non-existent field name in a complex query relationship.
   *
   * @dataProvider providerTestInvalidFieldName
   */
  public function testInvalidFieldName(string $field_name): void {
    $this->expectException(QueryException::class);
    $this->expectExceptionMessage("'non_existent_field_name' not found");

    // Check that non-existent field names in a complex relationship query
    // throws a meaningful exception.
    $this->container->get('entity_type.manager')
      ->getStorage('entity_test')
      ->getQuery()
      ->accessCheck()
      ->condition($field_name, $this->randomString(), '=')
      ->execute();
  }

  /**
   * Data provider for testInvalidFieldName().
   */
  public static function providerTestInvalidFieldName(): array {
    return [
      ['non_existent_field_name.entity:user.name.value'],
      ['user_id.entity:user.non_existent_field_name.value'],
    ];
  }

  /**
   * Assert the results.
   *
   * @param array $expected
   *   A list of indexes in the $this->entities array.
   *
   * @internal
   */
  protected function assertResults(array $expected): void {
    $expected_count = count($expected);
    $this->assertCount($expected_count, $this->queryResults);
    foreach ($expected as $key) {
      $id = $this->entities[$key]->id();
      $this->assertEquals($id, $this->queryResults[$id]);
    }
  }

}
