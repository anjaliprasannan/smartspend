<?php

declare(strict_types=1);

namespace Drupal\Tests\system\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\system\Traits\OffCanvasTestTrait;

/**
 * Base class contains common test functionality for the Off-canvas dialog.
 */
abstract class OffCanvasTestBase extends WebDriverTestBase {

  use OffCanvasTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'off_canvas_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function drupalGet($path, array $options = [], array $headers = []) {
    $return = parent::drupalGet($path, $options, $headers);
    $this->assertPageLoadComplete();
    return $return;
  }

  /**
   * Assert the page is completely loaded.
   *
   * Ajax requests may happen after page loads. Also for users who have access
   * to contextual links the contextual link placeholders will be filled after
   * the page is received.
   */
  protected function assertPageLoadComplete() {
    if ($this->loggedInUser && $this->loggedInUser->hasPermission('access contextual links')) {
      $this->assertAllContextualLinksLoaded();
    }
  }

  /**
   * Assert all contextual link areas have be loaded.
   *
   * Contextual link placeholders will be filled after
   * the page is received.
   *
   * @todo Move this function to https://www.drupal.org/node/2821724.
   */
  protected function assertAllContextualLinksLoaded() {
    $this->assertSession()->assertNoElementAfterWait('css', '[data-contextual-id]:empty');
  }

  /**
   * Enables a theme.
   *
   * @param string $theme
   *   The theme.
   */
  protected function enableTheme($theme) {
    // Enable the theme.
    \Drupal::service('theme_installer')->install([$theme]);
    $theme_config = \Drupal::configFactory()->getEditable('system.theme');
    $theme_config->set('default', $theme);
    $theme_config->save();
  }

  /**
   * Waits for off-canvas dialog to open.
   *
   * @param string $position
   *   The position of the dialog.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function waitForOffCanvasToOpen($position = 'side') {
    $this->waitForOffCanvasArea();
    // Check that the canvas is positioned on the side.
    $this->assertSession()->elementExists('css', '.ui-dialog-position-' . $position);
  }

  /**
   * Waits for off-canvas dialog to close.
   */
  protected function waitForOffCanvasToClose() {
    $this->assertSession()->assertNoElementAfterWait('css', '#drupal-off-canvas');
  }

  /**
   * Gets the off-canvas dialog element.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The off-canvas dialog element.
   */
  protected function getOffCanvasDialog() {
    $off_canvas_dialog = $this->getSession()->getPage()->find('css', '.ui-dialog[aria-describedby="drupal-off-canvas"]');
    $this->assertEquals(FALSE, empty($off_canvas_dialog), 'The off-canvas dialog was found.');
    return $off_canvas_dialog;
  }

  /**
   * Get themes to test.
   *
   * @return string[]
   *   Theme names to test.
   */
  protected static function getTestThemes() {
    return ['claro', 'olivero', 'stable9', 'stark'];
  }

  /**
   * Asserts the specified selector is visible after a wait.
   *
   * @param string $selector
   *   The selector engine name. See ElementInterface::findAll() for the
   *   supported selectors.
   * @param string|array $locator
   *   The selector locator.
   * @param int $timeout
   *   (Optional) Timeout in milliseconds, defaults to 10000.
   */
  protected function assertElementVisibleAfterWait($selector, $locator, $timeout = 10000) {
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($this->assertSession()->waitForElementVisible($selector, $locator, $timeout));
  }

  /**
   * Data provider that returns theme name as the sole argument.
   */
  public static function themeDataProvider() {
    $themes = static::getTestThemes();
    $data = [];
    foreach ($themes as $theme) {
      $data[$theme] = [
        $theme,
      ];
    }
    return $data;
  }

}
