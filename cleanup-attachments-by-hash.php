<?php

ini_set('memory_limit', '-1');
set_time_limit(0); 
ini_set('max_execution_time', 0);

$wpRoot = null;
$wpLoadFile = "wp-load.php";

// attempt to find the load file
$wpRoot = localFindWordPressRoot();
if ( ($wpRoot != null) && (! is_file("{$wpRoot}/{$wpLoadFile}")) ) {
	exit(
		"Error: {$wpRoot}/{$wpLoadFile} was not found. Exiting."."<br/>"
	);
} else if ($wpRoot == null) {
	exit(
		"Error: Could not find a WordPress wp-load.php file. Exiting."."<br/>"
	);
}

/**
 * Returns the discovered WordPress root relative to where this script is
 * 
 * @return mixed
 */
function localFindWordPressRoot () {
	$dir = dirname(__FILE__);
	do {
		if (file_exists("{$dir}/wp-load.php")) {
			// MAY EXIT THIS BLOCK
			return $dir;
		}
	} while ($dir = realpath("{$dir}/.."));

	return null;
}

define('WP_USE_THEMES', false);
require_once("{$wpRoot}/{$wpLoadFile}");
require_once ABSPATH."wp-admin/includes/media.php";
require_once ABSPATH."wp-admin/includes/file.php";
require_once ABSPATH."wp-admin/includes/image.php";
require_once "lib/compare-images.lib.php";
require_once "lib/helpers.php";

global $wpdb;

ob_start();

flush();
ob_flush();

// Get all media files
$args = array(
  'post_type' => 'attachment',
  'post_status' => 'any',
  'posts_per_page' => -1,
);

$query = new WP_Query( $args );
$media_files = $query->posts;

echo "Found Records #: ".$query->found_posts."\n\n";

flush();
ob_flush();

function doHashCompareImages($media_files, $level = 0) {
  
  $count = 0;

  echo "- Checking media file ".basename($media_files[$level]->guid)." for duplicates... \n";

  foreach($media_files as $files) {

    set_time_limit(5600);
    
    flush();
    ob_flush();

    $matchStatus = "[DUPLICATE]";
    
    $isSame = round(compareImages(get_attached_file($media_files[$level]->ID), get_attached_file($files->ID)));

    if(!$isSame) {
      $matchStatus = "[NOT DUPLICATE]";
    } else if($media_files[$level]->ID === $files->ID) {
      $matchStatus = "[ORIGINAL IMAGE]";
    } else if($isSame <= -1) {
      $matchStatus = "[NOT SUPPORTED... POSSIBLY A SVG FILE...]";
    }

      if($matchStatus !== "[NOT SUPPORTED... POSSIBLY A SVG FILE...]" && $matchStatus !== "[ORIGINAL IMAGE]" && $matchStatus !== "[NOT DUPLICATE]") {
      
        $get_posts_pages = get_posts_by_attachment_id($files->ID);

        $actionStatus = "[KEEP]";
        if(!empty($get_posts_pages['content'])) {
          $actionStatus = "[REPLACE] [UPDATE] [IMAGE] [DELETE DUPLICATE]";
          foreach($get_posts_pages['content'] as $post_page_id) {
            $content = get_post_field('post_content', $post_page_id);
            //$content = runMediaReplace($files, $media_files[$level], $content,  get_post_mime_type($files->ID));
            /*wp_update_post(array(
              'ID' => $post_page_id,
              'post_content' => $content
            ));
            wp_delete_attachment($file['ID']);*/
          }
          die();
        } else if(!empty($get_posts_pages['thumbnail'])) {
          $actionStatus = "[REPLACE] [THUMBNAIL] [DELETE DUPLICATE]";

          foreach($get_posts_pages['thumbnail'] as $post_page_id) {
            /*wp_update_post(array(
              'ID' => $post_page_id,
              'post_parent' => $media_files[$level]->ID
            ));
            wp_delete_attachment($files->ID);*/
          }

        } else {
          $actionStatus = "[KEEP FILE]";
        }
    }

    echo "-- Comparing Image #".$count." at ".$media_files[$level]->guid." to ". $files->guid."... ".$matchStatus."... ".$actionStatus."... \n";

    flush();
    ob_flush();

    if($count === 10) {
     //die();
    }
    $count++;
    if(count($media_files) === $count) {
      $level++;
      doHashCompareImages($media_files, $level);
    }
    flush();
    ob_flush();
  }

}


doHashCompareImages($media_files, 0);

