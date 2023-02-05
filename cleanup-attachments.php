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

echo "- Found Records #: ".$query->found_posts."<Br><Br>";

flush();
ob_flush();

$files = [];
$count = 0;
foreach($media_files as $id => $file) {
  $fileName = basename( $file->guid );
  $files[$count]['filename'] = substr($fileName, 0, strpos($fileName, '.'));
  $files[$count]['filename_raw'] = str_replace(".jpg", "", $fileName);
  $filesize =  @filesize( get_attached_file( $file->ID ));
  $files[$count]['ID'] = $file->ID;
  $files[$count]['guid'] = parse_url($file->guid, PHP_URL_PATH);
  $files[$count]['url'] = $file->guid;
  $files[$count]['post_parent'] = $file->post_parent;
  $files[$count]['post_date'] = $file->post_date;
  $files[$count]['filesize'] = $filesize;
  $count++;
}

sort($files);

function checkForDuplicateMedia($files, $level = 0,  $outputList = []) {
  $lastFileName = null;
  $o = [];
  foreach($files as $id => $file) {
    $filename = preg_replace('/-[0-9]*$/', '', $file['filename_raw']);
    $outputList[$filename][] = $file;
  }
  $c = 0;
  foreach($outputList as $id => $file) {
    $o[$c] = $file;
    $c++;
  }
  return $o;
}

function r($files, $level, $array = []) {

  echo "\n"."Running Level ".$level." check for duplicates..."."\n\n";
  
  //echo "<pre>"; print_r(checkForDuplicateMedia($files, $level, $array)); echo "</pre>"; die();

  $checkForDuplicateMedia = checkForDuplicateMedia($files, $array);
  $checkForDuplicateMediaCount = count($checkForDuplicateMedia);

  foreach ( checkForDuplicateMedia($files, $level, $array) as $i => $file ) { 
    set_time_limit(5600);
    flush();
    ob_flush();
    $duplicateCount = count($checkForDuplicateMedia[$level]);
    $nextLevel = FALSE;
    $orginalFile = null;
    if($duplicateCount === 1) {
      echo "-- ".$checkForDuplicateMedia[$level][0]['filename_raw']." has no duplicate images... nothing to do here..."."\n";
      $nextLevel = TRUE;
    } else {
      $c = 0;
      foreach($checkForDuplicateMedia[$level] as $file) {
        if($c === 0) {
          //orginal file always at zero level
          echo $file['filename_raw']."::". $duplicateCount." "."\n";
          $orginalFile = $file;
        } else {
          //duplicates files always post zero level
          $get_posts_pages = get_posts_by_attachment_id($file['ID']);
          if(!empty($get_posts_pages['content']) && !empty($get_posts_pages['thumbnail'])) {
            echo "-- [CONTENT] [THUMBNAIL]... ".$file['url']." has been replaced with ".$orginalFile['url']." and has been deleted... you saved ".formatBytes($file['filesize'])."... [COMPLETED]..."."\n";
            update_wp_post($post_page_id, $content, 'content');
            update_wp_post($post_page_id, $orginalFile['ID'], 'id');
            delete_wp_media($file['ID']);
            flush();
            ob_flush();
          } else if(!empty($get_posts_pages['content'])) {
            foreach($get_posts_pages['content'] as $post_page_id) {
              $content = get_post_field('post_content', $post_page_id);
              $content = runMediaReplace($file, $orginalFile, $content, get_post_mime_type($file['ID']));
              echo "-- [CONTENT]... ".$file['url']." has been replaced with ".$orginalFile['url']." and has been deleted... you saved ".formatBytes($file['filesize'])."... [COMPLETED]..."."\n";
              update_wp_post($post_page_id, $content, 'content');
              delete_wp_media($file['ID']);
              flush();
              ob_flush();
            }

          } else if(!empty($get_posts_pages['thumbnail'])) {
            foreach($get_posts_pages['thumbnail'] as $post_page_id) {
              echo "-- [THUMBNAIL]... ".$file['url']." has been replaced with ".$orginalFile['url']." and has been deleted... you saved ".formatBytes($file['filesize'])."... [COMPLETED]..."."\n";
              update_wp_post($post_page_id, $orginalFile['ID'], 'id');
              delete_wp_media($file['ID']);
              flush();
              ob_flush();
            }
          } else {
            echo "-- [FILE] ".$file['url']." is not being used and has been deleted... you saved ".formatBytes($file['filesize'])."... [COMPLETED]... - origin: ".$orginalFile['url']."\n";
            delete_wp_media($file['ID']);
          }
        }
        $c++;
        flush();
        ob_flush();
        if($c === $duplicateCount) {
          $nextLevel = TRUE;
        }
      }
    }

    //debugging only
    /*if($level === 609) {
      die();
    }*/

    if($checkForDuplicateMediaCount !== $level && $nextLevel === TRUE) {
      $level++;
      r($files, $level, []);
    }

    flush();
    ob_flush();

  }
}

r($files, 0, []);

ob_end_flush(); 
