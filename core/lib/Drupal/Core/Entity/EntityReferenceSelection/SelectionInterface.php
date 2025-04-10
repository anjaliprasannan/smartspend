<?php

namespace Drupal\Core\Entity\EntityReferenceSelection;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface definition for Entity Reference Selection plugins.
 *
 * @see \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManager
 * @see \Drupal\Core\Entity\Annotation\EntityReferenceSelection
 * @see plugin_api
 *
 * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
 *   Thrown when selection received unexpected parameters and bypasses usual
 *   data validation conditions.
 */
interface SelectionInterface extends PluginFormInterface {

  /**
   * Gets the list of referenceable entities.
   *
   * @param string|null $match
   *   (optional) Text to match the label against. Defaults to NULL.
   * @param string $match_operator
   *   (optional) Operator to be used for string matching. Defaults to
   *   "CONTAINS".
   * @param int $limit
   *   (optional) Limit the query to a given number of items. Defaults to 0,
   *   which indicates no limiting.
   *
   * @return array
   *   A nested array of entities, the first level is keyed by the
   *   entity bundle, which contains an array of entity labels (escaped),
   *   keyed by the entity ID.
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0);

  /**
   * Counts entities that are referenceable.
   *
   * @param string $match
   *   (optional) Text to match the label against. Defaults to NULL.
   * @param string $match_operator
   *   (optional) Operator to be used for string matching. Defaults to
   *   "CONTAINS".
   *
   * @return int
   *   The number of referenceable entities.
   */
  public function countReferenceableEntities($match = NULL, $match_operator = 'CONTAINS');

  /**
   * Validates which existing entities can be referenced.
   *
   * @param array $ids
   *   An array of IDs to validate.
   *
   * @return array
   *   An array of valid entity IDs.
   */
  public function validateReferenceableEntities(array $ids);

  /**
   * Allows altering the SelectQuery generated by EntityFieldQuery.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   A Select Query object.
   */
  public function entityQueryAlter(SelectInterface $query);

}
