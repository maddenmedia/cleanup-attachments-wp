<?php

/*ini_set('memory_limit', '-1');
set_time_limit(0); 
ini_set('max_execution_time', 0);*/

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

echo "Found Records #".$query->found_posts."\n";

flush();
ob_flush();

$files = [];
$count = 0;

usort($media_files, function($a, $b) {
  return strcmp($a->post_title, $b->post_title);
});

foreach($media_files as $id => $file) {
  $filename = preg_replace('/-[0-9]*$/', '', preg_replace('/\\.[^.\\s]{3,4}$/', '', basename( $file->guid )));
  $files[$filename][$count] = $file;
  $filesize =  @filesize( get_attached_file( $file->ID ));
  $files[$filename][$count]->filesize = $filesize;
  $files[$filename][$count]->path = parse_url($file->guid, PHP_URL_PATH);
  $count++;
}

$files = array_values($files);

/*echo "<pre>";
print_r($files);
echo "</pre>";
die();*/

function r($files, $level, $array = []) {

  $checkForDuplicateMediaCount = count($files);

  echo "\n"."- Running Level ".$level." check for duplicates..."."\n";
  
  foreach ( $files as $i => $file ) { 
    set_time_limit(5600);
    flush();
    ob_flush();
    $duplicateCount = count($files[$level]);
    $nextLevel = FALSE;
    $orginalFile = null;
    if($duplicateCount === 1) {
      foreach($files[$level] as $file) {
        echo "-- ".$file->path." has no duplicate images... nothing to do here..."."\n";
      }
      $nextLevel = TRUE;
    } else {
      $c = 0;
      foreach($files[$level] as $file) {
        if($c === 0) {
          //orginal file always at zero level
          echo "-- Checking ".$file->path." for duplicate files..."."\n";
          $orginalFile = $file;
        } else {
          //duplicates files always post zero level
          $get_posts_pages = get_posts_by_attachment_id($file->ID);
          if(!empty($get_posts_pages['content']) && !empty($get_posts_pages['thumbnail'])) {
            echo "--- [CONTENT] [THUMBNAIL]... ".$file->path." has been replaced with ".$orginalFile->path." and has been deleted... you saved ".formatBytes($file->filesize)."... [COMPLETED]..."."\n";
            foreach($get_posts_pages['content'] as $post_page_id) {
              $content = get_post_field('post_content', $post_page_id);
              $content = runMediaReplace($file, $orginalFile, $content, get_post_mime_type($file->ID));
              update_wp_post($post_page_id, $content, 'content');
              delete_wp_media($file->ID);
              flush();
              ob_flush();
            }
            foreach($get_posts_pages['thumbnail'] as $post_page_id) {
              update_wp_post($post_page_id, $orginalFile->ID, 'id');
              delete_wp_media($file->ID);
              flush();
              ob_flush();
            }
          } else if(!empty($get_posts_pages['content'])) {
            foreach($get_posts_pages['content'] as $post_page_id) {
              $content = get_post_field('post_content', $post_page_id);
              $content = runMediaReplace($file, $orginalFile, $content, get_post_mime_type($file->ID));
              echo "--- [CONTENT]... ".$file->path." has been replaced with ".$orginalFile->path." and has been deleted... you saved ".formatBytes($file->filesize)."... [COMPLETED]..."."\n";
              update_wp_post($post_page_id, $content, 'content');
              delete_wp_media($file->ID);
              flush();
              ob_flush();
            }
          } else if(!empty($get_posts_pages['thumbnail'])) {
            foreach($get_posts_pages['thumbnail'] as $post_page_id) {
              echo "--- [THUMBNAIL]... ".$file->path." has been replaced with ".$orginalFile->path." and has been deleted... you saved ".formatBytes($file->filesize)."... [COMPLETED]..."."\n";
              update_wp_post($post_page_id, $orginalFile->ID, 'id');
              delete_wp_media($file->ID);
              flush();
              ob_flush();
            }
          } else {
            echo "--- [FILE] ".$file->path." is not being used and has been deleted... you saved ".formatBytes($file->filesize)."... [COMPLETED]... - origin: ".$orginalFile->path."\n";
            delete_wp_media($file->ID);
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

    if($checkForDuplicateMediaCount - 1 === $level) {
      die();
    }

    /*unset($files[$level]);
    echo "<pre>";
    echo $level;
    print_r($files[$level]);
    echo "</pre>";
    //debugging only
    if($level === 3) {
      die();
    }*/
   
    if($checkForDuplicateMediaCount !== $level && $nextLevel === TRUE) {
      $level++;
      r($files, $level, []);
    }

    flush();
    ob_flush();
  }
  
  // keep load off of cpu for 1 second
  sleep(1);
}

r($files, 0, []);

ob_end_flush(); 
