<?php

declare(strict_types=1);

namespace Drupal\profile_install_requirements\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;

/**
 * Provides method for checking requirements during install time.
 */
class ProfileInstallRequirementsRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    $requirements['testing_requirements'] = [
      'title' => t('Testing requirements'),
      'severity' => REQUIREMENT_ERROR,
      'description' => t('Testing requirements failed requirements.'),
    ];

    return $requirements;
  }

}
