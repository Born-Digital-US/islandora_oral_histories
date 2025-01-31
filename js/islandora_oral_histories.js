/**
 * @file - islandora_oral_histories.js
 */

(function ($, Drupal, once) {

  Drupal.behaviors.islandora_oral_histories = {
    attach: function (context, settings) {
      var audio = document.getElementsByTagName('audio')[0];
      var video = document.getElementsByTagName('video')[0];
      var media = '';
      var playing_time = 0;
      var last_from_to = '';
      var last_button_id = '';
      if (audio) {
        media = audio;
      }
      if (video) {
        media = video;
      }
      if (media) {
        media.ontimeupdate = function () {
          var play_time = Math.trunc(media.currentTime);
          if (playing_time !== play_time) {
            var time_li = $('[data-start="' + play_time + '"]');
            if (time_li.length) {
              playing_time = play_time;

              //  Highlight the row.
              $(".transcript-container ul li").removeClass('playing');
              time_li.addClass('playing');

              // Scroll to the current line.
              var index = time_li[0].id.split('_')[1];
              var buttonHeight = time_li.outerHeight(true);
              $(".scrolling-transcript-processed").scrollTop(index * buttonHeight);

              //  @todo Need to set this variable: last_button_id.
              var button = time_li.find('button.play-tcu');
              if (button.length) {
                last_button_id = button[0].id;
              }
            }
          }
        };
      }
      // Each section's play button.
      $(once('islandora_oral_histories-play', 'button.play-tcu')).click(function () {
        playing_time = 0;
        var self_id = this.id;
        last_button_id = self_id;
        var from_to = self_id.split("_");
        media.currentTime = from_to[0];
        media.play();
        last_from_to = from_to;
        $(".transcript-container ul li").removeClass('playing');
        var play_li = $(this).parent('div').parent('div').parent('li');
        play_li.addClass('playing');
        var play_li_id = play_li[0].id;
        play_li_id = play_li_id.replace('li_', '');
        var li_selector = '.transcript-container ul li:nth-child(' + play_li_id + ')';
      });
      // Previous line play button.
      $(once('islandora_oral_histories-previous', 'button.previous')).click(function () {
        if (last_button_id) {
          playing_time = 0;
          var prev_button = $("#" + last_button_id).parent('div').parent('div').parent('li').prev('li').find('button.play-tcu');
          if (prev_button.length) {
            var self_id = prev_button[0].id;
            last_button_id = self_id;
            var from_to = self_id.split("_");
            media.currentTime = from_to[0];
            media.play();
            last_from_to = from_to;
          }
        }
      });
      // Next line play button.
      $(once('islandora_oral_histories-next', 'button.next')).click(function () {
        if (last_button_id) {
          playing_time = 0;
          var next_button = $("#" + last_button_id).parent('div').parent('div').parent('li').next('li').find('button.play-tcu');
          if (next_button.length) {
            var self_id = next_button[0].id;
            last_button_id = self_id;
            var from_to = self_id.split("_");
            media.currentTime = from_to[0];
            media.play();
            last_from_to = from_to;
          }
        }
      });
      // Repeat same line button.
      $(once('islandora_oral_histories-same', 'button.sameagain')).click(function () {
        if (last_from_to) {
          playing_time = 0;
          media.currentTime = last_from_to[0];
          media.play();
        }
      });
      // Dropdown click.
      $(once('islandora_oral_histories-dropdown', 'button.dropdown-toggle')).click(function () {
        // $(this).val() will work here
        var showing = ($('div.tier-selector-processed .dropdown-choices').css('display') !== "none");
        console.log(showing);
        if (showing) {
          $('div.tier-selector-processed .dropdown-choices').hide();
        }
        else {
          $('div.tier-selector-processed .dropdown-choices').show();
        }
      });
      // Show/hide speaker names click.
      $(once('islandora_oral_histories-speaker', '.show-speaker')).click(function () {
        var checked = $(".show-speaker i").hasClass('fa-check');
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
          var checked = $(".show-" + tier + " i").hasClass('fa-check');
          if (checked) {
            $(".show-" + tier + " i").removeClass('fa-check');
            $(".show-" + tier).attr('aria-checked', false);
            $(".transcript-container ." + tier + "-tiers").hide();
          }
          else {
            $(".show-" + tier + " i").addClass('fa-check');
              $(".show-" + tier).attr('aria-checked', true);
            $(".transcript-container ." +tier + "-tiers").show();
          }
          $('div.tier-selector-processed .dropdown-choices').hide();
        });
      }
    }
  }
})(jQuery, Drupal, once);
