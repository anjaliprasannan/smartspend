<?php

declare(strict_types=1);

namespace Drupal\content_moderation_test_resave\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for content_moderation_test_resave.
 */
class ContentModerationTestResaveHooks {

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    /** @var \Drupal\content_moderation\ModerationInformationInterface $content_moderation */
    $content_moderation = \Drupal::service('content_moderation.moderation_information');
    if ($content_moderation->isModeratedEntity($entity)) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      // Saving the passed entity object would populate its loaded revision ID,
      // which we want to avoid. Thus, save a clone of the original object.
      $cloned_entity = clone $entity;
      // Set the entity's syncing status, as we do not want Content Moderation
      // to create new revisions for the re-saving. Without this call Content
      // Moderation would end up creating two separate content moderation state
      // entities: one for the re-save revision and one for the initial
      // revision.
      $cloned_entity->setSyncing(TRUE)->save();
      // Record the fact that a re-save happened.
      \Drupal::state()->set('content_moderation_test_resave', TRUE);
    }
  }

}
