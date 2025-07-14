<?php

namespace Drupal\islandora_oral_histories\Plugin\search_api\processor;

use Drupal\search_api\Annotation\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\file\Entity\File;


/**
 * Adds the item's linked agent separately by type.
 *
 * @SearchApiProcessor(
 *   id = "transcript_search_index",
 *   label = @Translation("Transcript for search index"),
 *   description = @Translation("adds the transcript as text to search index"),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = false,
 * )
 */
class TranscriptSearchIndex extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Transcript for search index'),
        'description' => $this->t('Transcript media as text for search index'),
        'type' => 'search_api_text',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['transcript'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $node = $item->getOriginalObject()->getValue();

    // If node has a media with media_use = "Transcript" convert that to
    // text and set that value for use as a search index field.
    if ($node) {
//        && $node->hasField('field_edtf_date_created')
//        && !$node->field_edtf_date_created->isEmpty()) {
      $islandoraUtils = \Drupal::service('islandora.utils');
      $nid = $node->id();
      $transcript_term = $islandoraUtils->getTermForUri('http://pcdm.org/use#Transcript');
      $tid = $transcript_term->id();
      $transcriptMedia = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties([
        'field_media_use' => ['target_id' => $transcript_term->id()],
        'field_media_of' => ['target_id' => $node->id()],
      ]);
      if (count($transcriptMedia) > 0) {
        $transcriptMedia = reset($transcriptMedia);
      }
      else {
        $transcriptMedia = NULL;
      }

      $transcript = (is_object($transcriptMedia)) ?
        $this->_get_transcript_text_file($transcriptMedia) : '';

      $fields = $item->getFields(FALSE);
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($fields, NULL, 'transcript');
      foreach ($fields as $field) {
        $field->addValue($transcript);
      }
    }
  }

  function _get_transcript_text_file($transcriptMedia) {
    $transcript_file = [];
    if ($transcriptMedia && $transcriptMedia->bundle() == 'file') {
      $mediaSourceService = \Drupal::service('islandora.media_source_service');
      $media_file = $mediaSourceService->getSourceFile($transcriptMedia);
      $target_id = $media_file->id();
      $transcript = File::load($target_id);
      $file_uri = $transcript->getFileUri();
      $drupal_file_uri = $file_uri;
      $file_contents = file_get_contents($drupal_file_uri);
      $transcript_sections = $this->_parse_transcript_file($file_contents, $drupal_file_uri);
      foreach ($transcript_sections as $key => $line_arr) {
        foreach (['transcript', 'transcriptFull', 'title', 'annotation', 'keywords'] as $transcript_type_line) {
          if (is_array($line_arr) && array_key_exists($transcript_type_line, $line_arr) && is_string($line_arr[$transcript_type_line])) {
            $transcript_file[] = strip_tags(trim($line_arr[$transcript_type_line]));
          }
        }
      }
    }
    return implode(' ', $transcript_file);
  }

  /*
   * This helper function will need to handle the transcript file based on the
   * file mime type or simply file extension. XML files are easiest to put into the
   * structure that is expected by the display twig template.
   */
  function _parse_transcript_file($file_contents, $drupal_file_uri = '') {
    $transcript_sections = [];
    $start = $end = $speaker = NULL;
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
      $lines = explode('
', $file_contents);
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
              $start = $time_parts[0];
              $end = $time_parts[1];
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
              'start' => $start, 'end' => $end, 'speaker' => $speaker, 'transcript' => implode(' ', $transcript_lines)
            ];
          }
          $transcript_lines = [];
          $start = $end = '';
        }
      }
    }
    // Finished with the loop -- add the values from the final section if still need to be added.
    if (!empty($transcript_lines) && count($transcript_lines) > 0) {
      $transcript_sections[] = [
        'start' => $start, 'end' => $end, 'speaker' => $speaker, 'transcript' => implode(' ', $transcript_lines)
      ];
    }
    // Finally iterate in order to convert the time to a human-readable format.
    foreach ($transcript_sections as $key => $transcript_section) {
      // $seconds doesn't seem to be used?
      if (is_array($transcript_section)) {
        $seconds = array_key_exists('start', $transcript_section) ? $transcript_section['start'] : 0;
        $seconds = array_key_exists('end', $transcript_section) ? $transcript_section['end'] : 0;
      }
    }
    return $transcript_sections;
  }
}
