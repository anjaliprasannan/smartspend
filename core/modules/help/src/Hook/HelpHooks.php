<?php

namespace Drupal\help\Hook;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for help.
 */
class HelpHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): string|array|null {
    switch ($route_name) {
      case 'help.main':
        $output = '<h2>' . $this->t('Getting Started') . '</h2>';
        $output .= '<p>' . $this->t('Follow these steps to set up and start using your website:') . '</p>';
        $output .= '<ol>';
        $output .= '<li>' . $this->t('<strong>Configure your website</strong> Once logged in, visit the <a href=":admin">Administration page</a>, where you may <a href=":config">customize and configure</a> all aspects of your website.', [
          ':admin' => Url::fromRoute('system.admin')->toString(),
          ':config' => Url::fromRoute('system.admin_config')->toString(),
        ]) . '</li>';
        $output .= '<li>' . $this->t('<strong>Enable additional functionality</strong> Next, visit the <a href=":modules">Extend page</a> and install modules that suit your specific needs. You can find additional modules at the <a href=":download_modules">Drupal.org modules page</a>.', [
          ':modules' => Url::fromRoute('system.modules_list')->toString(),
          ':download_modules' => 'https://www.drupal.org/project/modules',
        ]) . '</li>';
        $output .= '<li>' . $this->t('<strong>Customize your website design</strong> To change the "look and feel" of your website, visit the <a href=":themes">Appearance page</a>. You may choose from one of the included themes or download additional themes from the <a href=":download_themes">Drupal.org themes page</a>.', [
          ':themes' => Url::fromRoute('system.themes_page')->toString(),
          ':download_themes' => 'https://www.drupal.org/project/themes',
        ]) . '</li>';
        // Display a link to the create content page if Node module is
        // installed.
        if (\Drupal::moduleHandler()->moduleExists('node')) {
          $output .= '<li>' . $this->t('<strong>Start posting content</strong> Finally, you may <a href=":content">add new content</a> to your website.', [':content' => Url::fromRoute('node.add_page')->toString()]) . '</li>';
        }
        $output .= '</ol>';
        $output .= '<p>' . $this->t('For more information, refer to the help listed on this page or to the <a href=":docs">online documentation</a> and <a href=":support">support</a> pages at <a href=":drupal">drupal.org</a>.', [
          ':docs' => 'https://www.drupal.org/documentation',
          ':support' => 'https://www.drupal.org/support',
          ':drupal' => 'https://www.drupal.org',
        ]) . '</p>';
        return ['#markup' => $output];

      case 'help.page.help':
        $help_home = Url::fromRoute('help.main')->toString();
        $module_handler = \Drupal::moduleHandler();
        $locale_help = $module_handler->moduleExists('locale') ? Url::fromRoute('help.page', ['name' => 'locale'])->toString() : '#';
        $search_help = $module_handler->moduleExists('search') ? Url::fromRoute('help.page', ['name' => 'search'])->toString() : '#';
        $output = '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Help module generates <a href=":help-page">Help topics and reference pages</a> to guide you through the use and configuration of modules, and provides a Help block with page-level help. The reference pages are a starting point for <a href=":handbook">Drupal.org online documentation</a> pages that contain more extensive and up-to-date information, are annotated with user-contributed comments, and serve as the definitive reference point for all Drupal documentation. For more information, see the <a href=":help">online documentation for the Help module</a>.', [
          ':help' => 'https://www.drupal.org/documentation/modules/help/',
          ':handbook' => 'https://www.drupal.org/documentation',
          ':help-page' => Url::fromRoute('help.main')->toString(),
        ]) . '</p>';
        $output .= '<p>' . $this->t('Help topics provided by modules and themes are also part of the Help module. If the core Search module is installed, these topics are searchable. For more information, see the <a href=":online">online documentation, Help Topic Standards</a>.', [
          ':online' => 'https://www.drupal.org/docs/develop/managing-a-drupalorg-theme-module-or-distribution-project/documenting-your-project/help-topic-standards',
        ]) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Providing a help reference') . '</dt>';
        $output .= '<dd>' . $this->t('The Help module displays explanations for using each module listed on the main <a href=":help">Help reference page</a>.', [':help' => Url::fromRoute('help.main')->toString()]) . '</dd>';
        $output .= '<dt>' . $this->t('Providing page-specific help') . '</dt>';
        $output .= '<dd>' . $this->t('Page-specific help text provided by modules is displayed in the Help block. This block can be placed and configured on the <a href=":blocks">Block layout page</a>.', [
          ':blocks' => \Drupal::moduleHandler()->moduleExists('block') ? Url::fromRoute('block.admin_display')->toString() : '#',
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Viewing help topics') . '</dt>';
        $output .= '<dd>' . $this->t('The top-level help topics are listed on the main <a href=":help_page">Help page</a>. Links to other topics, including non-top-level help topics, can be found under the "Related" heading when viewing a topic page.', [':help_page' => $help_home]) . '</dd>';
        $output .= '<dt>' . $this->t('Providing help topics') . '</dt>';
        $output .= '<dd>' . $this->t("Modules and themes can provide help topics as Twig-file-based plugins in a project sub-directory called <em>help_topics</em>; plugin meta-data is provided in YAML front matter within each Twig file. Plugin-based help topics provided by modules and themes will automatically be updated when a module or theme is updated. Use the plugins in <em>core/modules/help/help_topics</em> as a guide when writing and formatting a help topic plugin for your theme or module.") . '</dd>';
        $output .= '<dt>' . $this->t('Translating help topics') . '</dt>';
        $output .= '<dd>' . $this->t('The title and body text of help topics provided by contributed modules and themes are translatable using the <a href=":locale_help">Interface Translation module</a>. Topics provided by custom modules and themes are also translatable if they have been viewed at least once in a non-English language, which triggers putting their translatable text into the translation database.', [':locale_help' => $locale_help]) . '</dd>';
        $output .= '<dt>' . $this->t('Configuring help search') . '</dt>';
        $output .= '<dd>' . $this->t('To search help, you will need to install the core Search module, configure a search page, and add a search block to the Help page or another administrative page. (A search page is provided automatically, and if you use the core Claro administrative theme, a help search block is shown on the main Help page.) Then users with search permissions, and permission to view help, will be able to search help. See the <a href=":search_help">Search module help page</a> for more information.', [':search_help' => $search_help]) . '</dd>';
        $output .= '</dl>';
        return ['#markup' => $output];

      case 'help.help_topic':
        $help_home = Url::fromRoute('help.main')->toString();
        return '<p>' . $this->t('See the <a href=":help_page">Help page</a> for more topics.', [':help_page' => $help_home]) . '</p>';
    }
    return NULL;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path) : array {
    return [
      'help_section' => [
        'variables' => [
          'title' => NULL,
          'description' => NULL,
          'links' => NULL,
          'empty' => NULL,
        ],
      ],
      'help_topic' => [
        'variables' => [
          'body' => [],
          'related' => [],
        ],
      ],
    ];
  }

  /**
   * Implements hook_block_view_BASE_BLOCK_ID_alter().
   */
  #[Hook('block_view_help_block_alter')]
  public function blockViewHelpBlockAlter(array &$build, BlockPluginInterface $block): void {
    // Assume that most users do not need or want to perform contextual actions
    // on the help block, so don't needlessly draw attention to it.
    unset($build['#contextual_links']);
  }

  /**
   * Implements hook_modules_uninstalled().
   */
  #[Hook('modules_uninstalled')]
  public function modulesUninstalled(array $modules): void {
    _help_search_update($modules);
  }

  /**
   * Implements hook_themes_uninstalled().
   */
  #[Hook('themes_uninstalled')]
  public function themesUninstalled(array $themes): void {
    \Drupal::service('plugin.cache_clearer')->clearCachedDefinitions();
    _help_search_update();
  }

  /**
   * Implements hook_modules_installed().
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules, $is_syncing): void {
    _help_search_update();
  }

  /**
   * Implements hook_themes_installed().
   */
  #[Hook('themes_installed')]
  public function themesInstalled(array $themes): void {
    \Drupal::service('plugin.cache_clearer')->clearCachedDefinitions();
    _help_search_update();
  }

}
