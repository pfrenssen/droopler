<?php

namespace Drupal\d_media;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Template\Attribute;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base for the provider plugins.
 */
abstract class ProviderPluginBase extends PluginBase implements ProviderPluginInterface, ContainerFactoryPluginInterface {

  /**
   * An array of settings from formatter for player.
   *
   * @var array
   */
  protected $playerSettings = [];

  /**
   * An array of settings from formatter for video.
   *
   * @var array
   */
  protected $videoSettings = [];

  /**
   * Base URL of the provider, with a placeholder for video ID.
   *
   * @var string
   */
  protected $baseUrl;

  /**
   * Fragment part of the URL.
   *
   * @var string
   */
  protected $fragment;

  /**
   * The ID of the video.
   *
   * @var string
   */
  protected $videoId;

  /**
   * The input that caused the embed provider to be selected.
   *
   * @var string
   */
  protected $input;

  /**
   * A http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Create a plugin with the given input.
   *
   * @param array $configuration
   *   The configuration of the plugin.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An HTTP client.
   *
   * @throws \Exception
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ClientInterface $http_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    if (!static::isApplicable($configuration['input'])) {
      throw new \Exception('Tried to create a video provider plugin with invalid input.');
    }

    $this->input = $configuration['input'];
    $this->videoId = $this->getIdFromInput($configuration['input']);
    $this->fragment = $this->getFragmentFromInput();
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('http_client'));
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable($input) {
    $id = static::getIdFromInput($input);

    return !empty($id);
  }

  /**
   * {@inheritdoc}
   */
  public function renderEmbedCode() {
    $output = [
      '#theme' => 'd_media_video_embed',
      '#attributes' => new Attribute([
        'src' => $this->constructSrc(),
        'frameborder' => '0',
        'allowfullscreen' => 'allowfullscreen',
        'data-provider' => $this->getPluginDefinition()['id'],
        'data-aspect-ratio' => $this->calculateAspectRatio(),
        'class' => [
          'video-embed',
        ],
      ]),
    ];

    if (!empty($this->videoSettings['cover'])) {
      $output['#attributes']->addClass('video-embed--cover');
      $output['#attached']['library'][] = 'd_media/responsive-video';
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->t('@provider Video (@id)', ['@provider' => $this->getPluginDefinition()['title'], '@id' => $this->getVideoId()]);
  }

  /**
   * {@inheritdoc}
   */
  public function setPlayerSettings(array $settings) {
    $this->playerSettings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function setVideoSettings(array $settings) {
    $this->videoSettings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getFragmentFromInput() {
    return '';
  }

  /**
   * Get the ID of the video.
   *
   * @return string
   *   The video ID.
   */
  protected function getVideoId() {
    return $this->videoId;
  }

  /**
   * Get the input which caused this plugin to be selected.
   *
   * @return string
   *   The raw input from the user.
   */
  protected function getInput() {
    return $this->input;
  }

  /**
   * Create query string.
   *
   * @return string
   *   Query string to be added to url.
   */
  protected function constructQuery() {
    return http_build_query($this->playerSettings);
  }

  /**
   * Construct source attribute.
   *
   * @return string
   *   Src attribute.
   */
  protected function constructSrc() {
    $url = sprintf($this->baseUrl, $this->getVideoId());

    $query = $this->constructQuery();
    if (!empty($query)) {
      $url .= "?$query";
    }

    if (!empty($this->fragment)) {
      $url .= '#' . $this->fragment;
    }

    return $url;
  }

  /**
   * Calculate aspect ratio of the video.
   *
   * @return float|int
   *   Aspect ratio.
   */
  protected function calculateAspectRatio() {
    $video_data = $this->oEmbedData();
    return (isset($video_data->height) && isset($video_data->width)) ? $video_data->height / $video_data->width : 1;
  }

}
