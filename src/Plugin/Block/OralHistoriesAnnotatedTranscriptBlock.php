<?php

namespace Drupal\islandora_oral_histories\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\islandora_oral_histories\Plugin\Block\OralHistoriesTranscriptBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Oral Histories Transcript' Block with Title, Summary and Keywords annotations.
 *
 * @Block(
 *   id = "oral_histories_annotated_transcript_block",
 *   admin_label = @Translation("Oral Histories Transcript block with Title, Summary and Keywords annotations"),
 *   category = @Translation("Views")
 * )
 */
class OralHistoriesAnnotatedTranscriptBlock extends OralHistoriesTranscriptBlock {

  /**
   * {@inheritDoc}
   */
  // public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, EntityTypeManagerInterface $entityTypeManager, AccountProxy $current_user, $islandora_utils, $media_source_service) {
  //   parent::__construct($configuration, $plugin_id, $plugin_definition, $route_match, $entityTypeManager, $current_user, $islandora_utils, $media_source_service);
  // }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $transcript_sections = $this->getTranscriptSections();

    $return = [];
    if (count($transcript_sections) > 0) {
      // Good to read a transcript and populate a block render array.
      $return = [
        '#transcript_content' => $transcript_sections,
        '#theme' => 'oral_histories_annotated_transcript_block',
        '#attached' => [
          'library' => [
            'islandora_oral_histories/islandora_oral_histories.interact',
          ],
        ],
      ];
      // Determine if there's any ['speaker'] values in the transcript_sections array.
      $return['#has_speakers'] = FALSE;
      foreach ($transcript_sections as $transcript_section) {
        if (array_key_exists('speaker', $transcript_section) && !empty($transcript_section['speaker'])) {
          $return['#has_speakers'] = TRUE;
          break;
        }
      }
    }
    return $return;
  }

}
