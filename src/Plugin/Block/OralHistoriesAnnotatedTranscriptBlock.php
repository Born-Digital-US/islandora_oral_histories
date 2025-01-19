<?php

namespace Drupal\islandora_oral_histories\Plugin\Block;

use Drupal\islandora_oral_histories\Plugin\Block\OralHistoriesTranscriptBlock;

/**
 * Provides an 'Oral Histories Transcript' Block with Title, synopsis and Keywords annotations.
 *
 * @Block(
 *   id = "oral_histories_annotated_transcript_block",
 *   admin_label = @Translation("Oral Histories Transcript block with Title, Synopsis and Keywords annotations"),
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

    $all_tiers = [
      'title' => $this->t('Title'),
      'annotation' => $this->t('Synopsis'),
      'transcript' => $this->t('Partial transcript'),
      'transcriptFull' => $this->t('Transcript'),
      'keywords' => $this->t('Keywords'),
    ];

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


      $return['#tiers'] = $this->getTiers($transcript_sections, $all_tiers);
      $return['#has_tiers'] = count($return['#tiers']) > 1;
    }

    return $return;
  }

  /**
   * Filter the transcript secion to get all existing transcript tiers
   *
   * @param array $transcript_sections
   *   The transcript sections
   * @param array $all_tiers
   *   The set of available tiers
   *
   * @return array
   *   All tiers found in the transcript sections
   */
  protected function getTiers($transcript_sections, $all_tiers) {
    $tiers = [];
    foreach ($transcript_sections as $transcript_section) {
      foreach ($all_tiers as $tier_key => $tier_label) {
        if (array_key_exists($tier_key, $transcript_section)) {
          $tiers[$tier_key] = $tier_label;
        }
      }
    }
    return $tiers;
  }

}
