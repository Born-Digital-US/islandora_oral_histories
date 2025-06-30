/**
 * @file - islandora_oral_histories.js
 */

(function ($, Drupal, once) {

  Drupal.behaviors.islandora_oral_histories = {
    attach: function (context, settings) {
      const audio = document.getElementsByTagName('audio')[0];
      const video = document.getElementsByTagName('video')[0];
      let media = audio || video;

      if (!media) return;

      // Preload media to reduce buffering delays.
      media.preload = 'auto';

      // Cache all transcript segments on init.
      const transcriptSegments = $('[data-start]').map(function () {
        return {
          time: parseFloat($(this).attr('data-start')),
          el: $(this)
        };
      }).get();

      let playing_time = 0;
      let last_button_id = transcriptSegments[0].el.find('button.play-tcu')[0].id;
      let last_from_to = last_button_id.split("_");

      media.ontimeupdate = function () {
        let play_time = media.currentTime;

        let selected = null;

        for (let i = 0; i < transcriptSegments.length; i++) {
          let segment = transcriptSegments[i];
          if (segment.time <= play_time) {
            selected = segment;
          } else {
            break;
          }
        }

        if (selected && !selected.el.hasClass('playing')) {
          playing_time = Math.round(play_time);
          let button = selected.el.find('button.play-tcu');

          $(".transcript-container ul li").removeClass('playing');
          selected.el.addClass('playing');

          if (button.length) {
            last_button_id = button[0].id;
            navigateToSegment(button, false);
          }
        }
      };


      // Helper function for transcript segment navigation.
      function navigateToSegment(button, setTime = true) {
        if (!button.length) return;

        playing_time = 0;
        const self_id = button[0].id;
        last_button_id = self_id;

        if (setTime) {
          const from_to = self_id.split("_");
          media.currentTime = parseFloat(from_to[0]);
          media.play();
          last_from_to = from_to;
        }

        $(".transcript-container ul li").removeClass('playing');
        const play_li = button.closest('li');
        play_li.addClass('playing');

        // Scroll to the new position
        const transcriptContainer = $(".scrolling-transcript-processed");
        const liOffset = play_li.offset().top;
        const containerOffset = transcriptContainer.offset().top;
        const scrollTo = liOffset - containerOffset + transcriptContainer.scrollTop();

        transcriptContainer.stop().animate({
          scrollTop: scrollTo
        }, 200);
      }

      // Each section's play button.
      $(once('islandora_oral_histories-play', 'button.play-tcu')).click(function () {
        navigateToSegment($(this));
      });

      // Previous line play button.
      $(once('islandora_oral_histories-previous', 'button.previous')).click(function () {
        if (last_button_id) {
          var prev_button = $("#" + last_button_id).closest('li').prev('li').find('button.play-tcu');
          navigateToSegment(prev_button);
        }
      });

      // Next line play button.
      $(once('islandora_oral_histories-next', 'button.next')).click(function () {
        if (last_button_id) {
          var next_button = $("#" + last_button_id).closest('li').next('li').find('button.play-tcu');
          navigateToSegment(next_button);
        }
      });

      // Repeat same line button.
      $(once('islandora_oral_histories-same', 'button.sameagain')).click(function () {
        if (last_from_to) {
          playing_time = 0;
          media.currentTime = parseFloat(last_from_to[0]);
          media.play();
        }
      });

      // Dropdown click.
      $(once('islandora_oral_histories-dropdown', 'button.dropdown-toggle')).click(function () {
        const showing = ($('div.tier-selector-processed .dropdown-choices').css('display') !== "none");
        if (showing) {
          $('div.tier-selector-processed .dropdown-choices').hide();
        }
        else {
          $('div.tier-selector-processed .dropdown-choices').show();
        }
      });

      // Show/hide speaker names click.
      $(once('islandora_oral_histories-speaker', '.show-speaker')).click(function () {
        const checked = $(".show-speaker i").hasClass('fa-check');
        if (checked) {
          $(".show-speaker i").removeClass('fa-check');
          $(".transcript-container .speaker-name").hide();
        }
        else {
          $(".show-speaker i").addClass('fa-check');
          $(".transcript-container .speaker-name").show();
        }
        $('div.tier-selector-processed .dropdown-choices').hide();
      });

      for (const tier of ['title', 'transcript', 'transcriptFull', 'annotation', 'keywords']) {
        // Show/hide tiers click.
        $(once('islandora_oral_histories-' + tier, '.show-' + tier)).click(function () {
          const checked = $(".show-" + tier + " i").hasClass('fa-check');
          if (checked) {
            $(".show-" + tier + " i").removeClass('fa-check');
            $(".show-" + tier).attr('aria-checked', false);
            $(".transcript-container ." + tier + "-tiers").hide();
          }
          else {
            $(".show-" + tier + " i").addClass('fa-check');
            $(".show-" + tier).attr('aria-checked', true);
            $(".transcript-container ." + tier + "-tiers").show();
          }
          $('div.tier-selector-processed .dropdown-choices').hide();
        });
      }
    }
  };
})(jQuery, Drupal, once);
