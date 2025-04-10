<?php

namespace Drupal\views\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsStyle;

/**
 * Default style plugin to render an RSS feed.
 *
 * @ingroup views_style_plugins
 */
#[ViewsStyle(
  id: "rss",
  title: new TranslatableMarkup("RSS Feed"),
  help: new TranslatableMarkup("Generates an RSS feed from a view."),
  theme: "views_view_rss",
  display_types: ["feed"],
)]
class Rss extends StylePluginBase {

  /**
   * The RSS namespaces.
   */
  public array $namespaces;

  /**
   * The channel elements.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  public array $channel_elements;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * Attaches the RSS icon and feed link to the view.
   */
  public function attachTo(array &$build, $display_id, Url $feed_url, $title) {
    $url_options = [];
    $input = $this->view->getExposedInput();
    if ($input) {
      $url_options['query'] = $input;
    }
    $url_options['absolute'] = TRUE;

    $url = $feed_url->setOptions($url_options)->toString();

    // Add the RSS icon to the view.
    $this->view->feedIcons[] = [
      '#theme' => 'feed_icon',
      '#url' => $url,
      '#title' => $title,
    ];

    // Attach a link to the RSS feed, which is an alternate representation.
    $build['#attached']['html_head_link'][][] = [
      'rel' => 'alternate',
      'type' => 'application/rss+xml',
      'title' => $title,
      'href' => $url,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['description'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('RSS description'),
      '#default_value' => $this->options['description'],
      '#description' => $this->t('This will appear in the RSS feed itself.'),
      '#maxlength' => 1024,
    ];
  }

  /**
   * Return an array of additional XHTML elements to add to the channel.
   *
   * @return array
   *   A render array.
   */
  protected function getChannelElements() {
    return [];
  }

  /**
   * Get RSS feed description.
   *
   * @return string
   *   The string containing the description with the tokens replaced.
   */
  public function getDescription() {
    $description = $this->options['description'];

    // Allow substitutions from the first row.
    $description = $this->tokenizeValue($description, 0);

    return $description;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $rows = [];

    // This will be filled in by the row plugin and is used later on in the
    // theming output.
    $this->namespaces = ['xmlns:dc' => 'http://purl.org/dc/elements/1.1/'];

    // Fetch any additional elements for the channel and merge in their
    // namespaces.
    $this->channel_elements = $this->getChannelElements();
    foreach ($this->channel_elements as $element) {
      if (isset($element['namespace'])) {
        $this->namespaces = array_merge($this->namespaces, $element['namespace']);
      }
    }

    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $rows[] = $this->view->rowPlugin->render($row);
    }

    $build = [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#rows' => $rows,
      '#attached' => [
        'http_header' => [
          ['Content-Type', 'application/rss+xml; charset=utf-8'],
        ],
      ],
    ];
    unset($this->view->row_index);
    return $build;
  }

}
