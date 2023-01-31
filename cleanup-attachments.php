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

echo "Found Records #: ".$query->found_posts."<br/><br>";

flush();
ob_flush();

$files = [];
$count = 0;
foreach($media_files as $id => $file) {
  $fileName = basename( $file->guid );
  $files[$count]['filename'] = substr($fileName, 0, strpos($fileName, '.'));
  $filesize =  @filesize( get_attached_file( $file->ID ));
  $files[$count]['ID'] = $file->ID;
  $files[$count]['guid'] = $file->guid;
  $files[$count]['post_parent'] = $file->post_parent;
  $files[$count]['post_date'] = $file->post_date;
  $files[$count]['filesize'] = $filesize;
  $count++;
}

sort($files);

function checkForDuplicateMedia($files, $level = 0,  $outputList = []) {
  $count = count($files);
  $lastFileName = '';
  foreach($files as $id => $file) {

    $filename = $file['filename'];
    
    if($filename === $lastFileName) {
      $idPOS = $id;
    }

    $filename_remove_last_dash = preg_replace('/-[0-9]*$/', '', $filename);
    $lastFileName_remove_last_dash = preg_replace('/-[0-9]*$/', '', $lastFileName);

    if ($lastFileName_remove_last_dash === $filename_remove_last_dash && $filename !== $lastFileName) {
      $outputList[$idPOS]['duplicates'][] = $files[$id];
    } else {
      $outputList[$id] = $files[$id];
    }

    $lastFileName =  $files[$level]['filename'];

  }

  return $outputList;

}

function r($files, $level, $array = []) {

  $count = count($files);

  echo "Running Level ".$level." check for duplicates...\n";

  foreach ( checkForDuplicateMedia($files, $level, $array)  as $i => $file) { 

    $orginal_file = $file;

    set_time_limit(5600);

    flush();
    ob_flush();

    if (array_key_exists('duplicates', $file)) {

      foreach($file['duplicates'] as $i => $duplicate_file) {
        $get_posts_pages = get_posts_by_attachment_id($duplicate_file['ID']);

        if(!empty($get_posts_pages['content'])) {

          foreach($get_posts_pages['content'] as $post_page_id) {
            if($orginal_file['guid']) {
              $content = get_post_field('post_content', $post_page_id);
              $content = runMediaReplace($duplicate_file, $orginal_file, $content,  get_post_mime_type($duplicate_file['ID']));
              //echo "<li>".$duplicate_file['guid']." has been replaced with ".$orginal_file['guid']." and has been deleted... you saved ".formatBytes($duplicate_file['filesize'])."... [COMPLETED]...</li>";
              wp_update_post(array(
                'ID' => $post_page_id,
                'post_content' => $content
              ));
              delete_wp_media($file['ID']);
            }
          }
    
        } else if(!empty($get_posts_pages['thumbnail'])) {

          foreach($get_posts_pages['thumbnail'] as $post_page_id) {
            if($orginal_file['guid']) {
              //echo "<li>".$duplicate_file['guid']." has been replaced with ".$orginal_file['guid']." and has been deleted... you saved ".formatBytes($duplicate_file['filesize'])."... [COMPLETED]...</li>";
              wp_update_post(array(
                'ID' => $post_page_id,
                'post_parent' => $orginal_file['ID']
              ));
              delete_wp_media($file['ID']);
            }
          }
        
        } else {

          if($orginal_file['guid']) {
            echo "-- ".$duplicate_file['guid']." is not being used and has been deleted... you saved ".formatBytes($duplicate_file['filesize'])."... [COMPLETED]... - origin: ".$orginal_file['guid']."\n";
            delete_wp_media($duplicate_file['ID']);
          }

        }
      }

    }

    flush();
    ob_flush();

    if($count-1 === $i && $level !== $count) {
        $level++;
        r($files, $level, []);
    }


  }

}

r($files, 0, []);

ob_end_flush(); 
?>
