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
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Oral Histories Transcript' Block.
 *
 * @Block(
 *   id = "oral_histories_transcript_block",
 *   admin_label = @Translation("Oral Histories Transcript block"),
 *   category = @Translation("Views")
 * )
 */
class OralHistoriesTranscriptBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * Constructor for Oral Histories Transcript Block.
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

    $transcript_sections = $this->getTranscriptSections();

    $return = [];
    if (count($transcript_sections) > 0) {
      // Good to read a transcript and populate a block render array.
      $return = [
        '#transcript_content' => $transcript_sections,
        '#theme' => 'oral_histories_transcript_block',
        '#attached' => [
          'library' => [
            'islandora_oral_histories/islandora_oral_histories.interact',
          ],
        ],
      ];

      // Determine if there's any ['speaker'] values in the transcript_sections array.
      $return['#has_speakers'] = $this->transcriptContainsElement($transcript_sections, 'speaker');
    }

    return $return;
  }

  /**
   * Get the transcript media and parse it into an array of sections
   *
   * @return array
   *   The parsed transcript sections
   */
  public function getTranscriptSections() {
    if ($this->routeMatch->getParameter('node')) {
      $node = $this->routeMatch->getParameter('node');
      $nid = (is_string($node) ? $node : $node->id());
      if (is_string($node)) {
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
      }
    }
    \Drupal::logger('islandora_oral_histories')->info("build with nid = " . $node->id());
    // The variable will be populated if there is a transcript.
    $transcript_sections = [];
    $user_roles = $this->currentUser->getRoles();

    $transcript_term = $this->islandoraUtils->getTermForUri('http://pcdm.org/use#Transcript');
    \Drupal::logger('islandora_oral_histories')->info("build with transcript_term = " . $transcript_term->id());
    $transcriptMedia = $this->entityTypeManager->getStorage('media')->loadByProperties([
      'field_media_use' => ['target_id' => $transcript_term->id()],
      'field_media_of' => ['target_id' => $nid],
    ]);
    // \Drupal::logger('islandora_oral_histories')->info("build with transcriptMedia = " . $transcriptMedia->id());
    if (count($transcriptMedia) > 0) {
      $transcriptMedia = reset($transcriptMedia);
      if ($transcriptMedia && $transcriptMedia->bundle() == 'file') {
        $media_file = $this->mediaSourceService->getSourceFile($transcriptMedia);
        $target_id = $media_file->id();
        $transcript = File::load($target_id);
        $file_uri = $transcript->getFileUri();
        \Drupal::logger('islandora_oral_histories')->info("build with file_uri = " . $file_uri);
        $drupal_file_uri = $file_uri; // str_replace("fedora://", \Drupal::request()->getSchemeAndHttpHost() . "/_flysystem/fedora/", $file_uri);
        $file_contents = file_get_contents($drupal_file_uri);
        $transcript_sections = $this->_parse_transcript_file($file_contents, $drupal_file_uri);
      }
    }
    elseif ($node->hasField('field_model') && !$node->get('field_model')->isEmpty()) {
      $model_term = $node->get('field_model')->referencedEntities()[0];
      $model = $model_term->getName();
      if ($model == 'Video' || $model == 'Audio') {
        $media_use = $this->islandoraUtils->getTermForUri('http://pcdm.org/use#OriginalFile');
        $av_Media = $this->entityTypeManager->getStorage('media')->loadByProperties([
          'field_media_use' => ['target_id' => $media_use->id()],
          'field_media_of' => ['target_id' => $nid],
        ]);
        if (count($av_Media) > 0) {
          $av_Media = reset($av_Media);
        }
        // $av_Media is either an empty array or an object.
        if (!empty($av_Media) && $av_Media->hasField('field_captions')) {
          $field_captions = $av_Media->get('field_captions');
          if (!is_null($av_Media->get('field_captions')->entity)) {
            // get the uri that points to this media's field_captions file and pass
            // this into the same parsing function as above.
            $drupal_file_uri = \Drupal::request()->getSchemeAndHttpHost() . $av_Media->get('field_captions')->entity->createFileUrl();
            $file_contents = file_get_contents($drupal_file_uri, false);
            $transcript_sections = $this->_parse_transcript_file($file_contents, $drupal_file_uri);
          }
        }
      }
    }

    return $transcript_sections;
  }

  /*
   * This helper function will need to handle the transcript file based on the
   * file mime type or simply file extension. XML files are easiest to put into the
   * structure that is expected by the display twig template.
   */
  function _parse_transcript_file($file_contents, $drupal_file_uri = '') {
    $transcript_sections = [];
    if (strstr($drupal_file_uri, ".xml")) {
      // XML file -- simple, load the file as simplexml and take appropriate 'cue' section.
      $xml = simplexml_load_string($file_contents, "SimpleXMLElement", LIBXML_NOCDATA);
      $json = json_encode($xml);
      $transript_as_array = json_decode($json,TRUE);
      $transcript_sections = (is_array($transript_as_array) && array_key_exists('cue', $transript_as_array) ? $transript_as_array['cue'] : []);
    }
    elseif (strstr($drupal_file_uri, ".vtt")) {
      // split apart at empty lines -- these are the sections then, iterate and each
      // line is subsequently:
      //  section identifier
      //  time range separated by -->
      //  transcript line
      //  "WEBVTT
      //
      //  1
      //  00:00:04.600 --> 00:00:06.850
      //  Hi everyone. My name's Becca Baader and I'm
      //
      $start = $end = 0;
      $lines = explode("\n", $file_contents);
      $transcript_lines = [];
      $skipnext = FALSE;
      foreach ($lines as $line) {
        $first6 = substr($line, 0, 6);
        if ($first6 == 'WEBVTT') {}
        elseif ($line) {
          if ($skipnext) {
            $skipnext = FALSE;
            $transcript_lines = [];
            $start = $end = 0;
          }
          elseif ($line == '') {
            $skipnext = TRUE;
            $transcript = '';
          }
          elseif (strstr($line, " --> ")) {
            $time_parts = explode(" --> ", $line);
            if (count($time_parts) == 2) {
              $start = $this->_strtotime($time_parts[0]);
              $end = $this->_strtotime($time_parts[1]);
            }
          }
          elseif ($line && $start && $end) {
            $lbr = strpos($line, '<');
            $rbr = strpos($line, '>');
            if ($lbr < $rbr) {
              $speaker = substr($line, 1, $rbr - 1 - $lbr);
              // do not set to html tag values.
              if ($speaker == 'b' || $speaker == 'i' || $speaker == 'u') {
                $speaker = '';
              }
              $transcript_lines[] = strip_tags(ltrim(ltrim(substr($line, $rbr + 1), '- ')));
            }
            else {
              $speaker = '';
              $transcript_lines[] = strip_tags(ltrim(ltrim($line, '- ')));
            }
          }
        }
        else {
          // blank line, add the values from the last section.
          if ($start && $end && count($transcript_lines) > 0) {
            $transcript_sections[] = [
              'start' => $start, 'end' => $end, 'speaker' => $speaker, 'transcript' => implode("\n", $transcript_lines)
            ];
          }
          $transcript_lines = [];
          $start = $end = '';
        }
      }
    }
    // Finished with the loop -- add the values from the final section if still need to be added.
    if (isset($transcript_lines) && !empty($transcript_lines)) {
      $transcript_sections[] = [
        'start' => $start, 'end' => $end, 'speaker' => $speaker, 'transcript' => implode("\n", $transcript_lines)
      ];
    }

    // Normalize into a nested array structure if there's only one 'cue' section.
    if (!array_is_list($transcript_sections)) {
      $transcript_sections = [$transcript_sections];
    }

    // Finally iterate in order to convert the time to a human-readable format.
    foreach ($transcript_sections as $key => $transcript_section) {
      $seconds = array_key_exists('start', $transcript_section) ? floor($transcript_section['start']) : 0;
      $transcript_sections[$key]['start'] = $seconds;
      $transcript_sections[$key]["start_h"] = $this->_seconds_to_time($seconds);
      $seconds = array_key_exists('end', $transcript_section) ? floor($transcript_section['end']) : 0;
      $transcript_sections[$key]['end'] = $seconds;
      $transcript_sections[$key]["end_h"] = $this->_seconds_to_time($seconds);
    }
    return $transcript_sections;
  }

  /*
   * Helper function to convert a time string into seconds.
   *
   * Expected value would look like: 00:00:06.850
   */
  function _strtotime($hh_mm_s) {
    @list($keep, $junk) = explode(" ", $hh_mm_s);
    $parts = explode(":", $keep);
    if (count($parts) == 3) {
      @list($h, $m, $s) = $parts;
    }
    else {
      @list($m, $s) = $parts;
      $h = 0;
    }
    return $h * 3600 + $m * 60 + $s;
  }

  function _seconds_to_time($seconds) {
    $secs = $seconds % 60;
    $hrs = $seconds / 60;
    $mins = (int)$hrs % 60;
    $hrs = $hrs / 60;
    return ($hrs ? (int)$hrs . ':' : '') . (($mins > 9) ? $mins : '0' . $mins) .
      ":" . (($secs > 9) ? (int)$secs : '0' . $secs);
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

  protected function transcriptContainsElement($transcript_sections, $element)  {
    foreach ($transcript_sections as $transcript_section) {
      if (array_key_exists($element, $transcript_section) && !empty($transcript_section[$element])) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
