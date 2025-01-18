<?php

namespace Drupal\islandora_oral_histories\Plugin\Block;

use Drupal\islandora_oral_histories\Plugin\Block\OralHistoriesTranscriptBlock;

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
      $return['#has_speakers'] = $this->transcriptContainsElement($transcript_sections, 'speaker');

      // Determine if there are any other transcript tiers besides 'transcript' or 'transcriptFull'.
      $return['#has_tiers'] = $this->transcriptContainsElement($transcript_sections, 'title')
        || $this->transcriptContainsElement($transcript_sections, 'summary')
        || $this->transcriptContainsElement($transcript_sections, 'keywords');
    }

    return $return;
  }

}
