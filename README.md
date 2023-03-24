# islandora-oral-histories
Oral Histories Functionality for Islandora 2.0+

### Install and configuration
Install and enable this module as you would for any other Drupal module. Incorporating this into the site's composer.json is outside the scope of this document.

The module will provide several field formatters and all of the module code with some javascript and CSS to parse a transcript. A subsequent version of this module will include minimal configurations, but it will have to be a manual process to configure the dependencies for content to use the features of this module.

##### Manual configurations
The srt, vtt or txt format of a time-based transcript is stored as a File media related to the audio or video item.  This uses the "Transcript" Islandora Media Use taxonomy term. If your site doesn't already have this term, you will need to add it, and make sure the External URI's "URL" field value is: `http://pcdm.org/use#Transcript`.

The File Media type needs to be configured to allow the File extensions on the field_file. This is needed if you have transcript files with either vtt, xml (srt), or asc file formats. 

To store a text version of the entire transcript (without any time encoding information), a new Islandora Media Use taxonomy term must be created called "Transcript Text." The External URI's "URL" value must be `http://pcdm.org/use#TranscriptText`.

A new display mode called "Oral Histories" will need to be added and a corresponding context to trigger this display mode. Separate context rules will need to be made for Audio and for Video. The context rules should have the conditions of "Node has term with URI" = "Moving Image" and "Video" for video oral histories, or "Node has term with URI" = "Sound" and "Audio" for audio oral histories.

![image](https://user-images.githubusercontent.com/19391126/226608409-ea63ad4b-4cd9-4f69-ae42-921281d16777.png)

For the "Oral Histories" display of the transcript, a Quicktabs called "Oral History Quicktabs" is used to configure the display of the transcript as well as the Islandora metadata. 

The "Oral Histories" display mode will need to be configured to display the previously mentioned "Oral History Quicktabs" as well as the Media EVA for the player.

![image](https://user-images.githubusercontent.com/19391126/226612042-b2766393-b534-45b9-a432-b07e17b3cd1a.png)


##### Usage
Of course, none of this would work unless an audio or video file has a Transcript media at least. Oral History objects must use the Model = "Video" and Resource Type = "Moving Image" (for video objects) or Model = "Audio" and Resource Type = "Sound" (for audio objects) -- and must have srt (xml), vtt, or txt file media which is set to Media Use = "Transcript" and/or txt or asc file media set to Media Use = "Transcript Text."
  
![image](https://user-images.githubusercontent.com/19391126/226374188-a748864f-bc69-4e79-a566-7b39b161762d.png)
