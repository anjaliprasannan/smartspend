<?php

namespace Drupal\navigation_top_bar\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for navigation_top_bar.
 */
class NavigationTopBarHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.navigation_top_bar':
        $output = '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Navigation Top Bar module is a Feature Flag module which, when enabled, renders the experimental navigation top bar.') . '</p>';
        $output .= '<p>' . $this->t('The top bar provides relevant administrative information and tasks for the current page. It is not feature complete nor fully functional.') . '</p>';
        $output .= '<p>' . $this->t('Leaving this module enabled can affect both admin and front-end pages layouts and blocks like Primary admin actions, whose content might be moved to te top bar.') . '</p>';
        $output .= '<p>' . $this->t('It is recommended to leave this module off while it is under active development and experimental phase.') . '</p>';
        $output .= '<p>' . $this->t('For more information, see the <a href=":docs">online documentation for the Navigation Top Bar module</a>.', [':docs' => 'https://www.drupal.org/project/navigation']) . '</p>';
        return $output;
    }
    return NULL;
  }

}
