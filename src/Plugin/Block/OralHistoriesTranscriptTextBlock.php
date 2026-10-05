<?php

namespace Drupal\islandora_oral_histories\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\file\Entity\File;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides an 'Oral Histories Transcript Text' Block.
 *
 * @Block(
 *   id = "oral_histories_transcript_text_block",
 *   admin_label = @Translation("Oral Histories Transcript text block"),
 *   category = @Translation("Views")
 * )
 */
class OralHistoriesTranscriptTextBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The routeMatch definition.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The entityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * IslandoraUtils class.
   */
  protected $islandoraUtils;

  /**
   * MediaSourceService class.
   */
  protected $mediaSourceService;

  /**
   * Constructor for About this Collection Block.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager definition.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param mixed $islandora_utils
   *   IslandoraUtils Utility class.
   * @param mixed $media_source_service
   *   MediaSourceService Utility class.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, EntityTypeManagerInterface $entityTypeManager, AccountProxy $current_user, $islandora_utils, $media_source_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $current_user;
    $this->islandoraUtils = $islandora_utils;
    $this->mediaSourceService = $media_source_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('islandora.utils'),
      $container->get('islandora.media_source_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if ($this->routeMatch->getParameter('node')) {
      $node = $this->routeMatch->getParameter('node');
      $nid = (is_string($node) ? $node : $node->id());
      if (is_string($node)) {
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
      }
    }
    // The variable will be populated if there is a transcript.
    $transcript_sections = [];
    $user_roles = $this->currentUser->getRoles();

    $transcript_term = $this->islandoraUtils->getTermForUri('http://pcdm.org/use#TranscriptText');
    $transcriptMedia = $this->entityTypeManager->getStorage('media')->loadByProperties([
      'field_media_use' => ['target_id' => $transcript_term->id()],
      'field_media_of' => ['target_id' => $nid],
    ]);
    if (count($transcriptMedia) > 0) {
      $transcriptMedia = reset($transcriptMedia);
    }
    else {
      $transcriptMedia = NULL;
    }

    $transcript = (is_object($transcriptMedia)) ?
      $this->_get_transcript_text_file($transcriptMedia) : '';

    $return = [];
    if ($transcript) {
      // Good to read a transcript and populate a block render array.
      $return = [
        '#transcript_text_content' => $transcript,
        '#theme' => 'oral_histories_transcript_text_block',
        '#attached' => [
          'library' => [
            'islandora_oral_histories/islandora_oral_histories.interact',
          ],
        ],
      ];
    }

    return $return;
  }

  function _get_transcript_text_file($transcriptMedia) {
    $transcript_file = [];
    if ($transcriptMedia && $transcriptMedia->bundle() == 'file') {
      $media_file = $this->mediaSourceService->getSourceFile($transcriptMedia);
      $target_id = $media_file->id();
      $transcript = File::load($target_id);
      $file_uri = $transcript->getFileUri();
      $drupal_file_uri = $file_uri;
      $transcript_file = nl2br((string) file_get_contents($drupal_file_uri));
    }
    return $transcript_file;
  }
  
  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $user = $this->currentUser;
    $parentTags = parent::getCacheTags();
    $tags = Cache::mergeTags($parentTags, ['user:' . $user->id()]);
    $block_config = BlockBase::getConfiguration();
    if (is_array($block_config) && array_key_exists('child_node_id', $block_config)) {
      $nid = $block_config['child_node_id'];
    }
    else {
      if ($this->routeMatch->getParameter('node')) {
        $node = $this->routeMatch->getParameter('node');
        $nid = (is_string($node) ? $node : $node->id());
      }
    }
    if (isset($nid)) {
      // If there is node add its cachetag.
      return Cache::mergeTags($tags, ['node:' . $nid]);
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // If you depends on \Drupal::routeMatch().
    // You must set context of this block with 'route' context tag.
    // Every new route this block will rebuild.
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }
}
