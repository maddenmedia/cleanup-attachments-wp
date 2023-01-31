<?php
//ini_set('display_errors',1);
//error_reporting(E_ALL);
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
require_once "compare-images.lib.php";

global $wpdb;

ob_start();

// start our process dump
$startTime = new DateTime();
$counter = 0;

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

function get_posts_by_attachment_id( $attachment_id ) {
  $used_as_thumbnail = array();

  if ( wp_attachment_is_image( $attachment_id ) ) {
    $thumbnail_query = new WP_Query( array(
      'meta_key'       => '_thumbnail_id',
      'meta_value'     => $attachment_id,
      'post_type'      => 'any',	
      'fields'         => 'ids',
      'no_found_rows'  => true,
      'posts_per_page' => -1,
    ) );

    $used_as_thumbnail = $thumbnail_query->posts;
  }

  $attachment_urls = array( wp_get_attachment_url( $attachment_id ) );

  if ( wp_attachment_is_image( $attachment_id ) ) {
    foreach ( get_intermediate_image_sizes() as $size ) {
      $intermediate = image_get_intermediate_size( $attachment_id, $size );
      if ( $intermediate ) {
        $attachment_urls[] = $intermediate['url'];
      }
    }
  }

  $used_in_content = array();

  foreach ( $attachment_urls as $attachment_url ) {
    $content_query = new WP_Query( array(
      's'              => $attachment_url,
      'post_type'      => 'any',	
      'fields'         => 'ids',
      'no_found_rows'  => true,
      'posts_per_page' => -1,
    ) );

    $used_in_content = array_merge( $used_in_content, $content_query->posts );
  }

  $used_in_content = array_unique( $used_in_content );

  $posts = array(
    'thumbnail' => $used_as_thumbnail,
    'content'   => $used_in_content,
  );

  return $posts;
}

function runMediaReplace($duplicated_file, $orginal_file, $content, $type) {

    if(str_contains($type, "image/")) {
      $content =  preg_replace("/<!-- wp:image {\"id\":".$duplicated_file['ID']."/", "<!-- wp:image {\"id\":".$orginal_file['ID'], $content);
      $content =  preg_replace("/class=\"wp-image-".$duplicated_file['ID']."/","/class=\"wp-image-".$orginal_file['ID']."/", $content);
      return str_replace($duplicated_file['guid'], $orginal_file['guid'], $content);
    }

}

function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('', 'K', 'M', 'G', 'T');   

    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
}


function compareImages($pathA, $pathB) {

      if($pathA) {
        $mimeA =  mime_content_type($pathA);
      }
      
      if($pathB) {
        $mimeB =  mime_content_type($pathB);

      }

      if($mimeA === "image/svg+xml") {
        return -1;
      } 
   
      if($mimeB === "image/svg+xml") {
        return -1;
      } 

      $compareMachine = new compareImages($pathA);
      $diff = $compareMachine->compareWith($pathB);

      if($diff < 11){
        return TRUE;
      } else{
        return FALSE;
      }

      
}

function doHashCompareImages($media_files, $level = 0) {
  $count = 0;
  flush();
  ob_flush();

  echo "<b> Checking media file ".basename($media_files[$level]->guid)." for duplicates...</b><br>";

  foreach($media_files as $files) {
    flush();
    ob_flush();

    set_time_limit(5600);

    $matchStatus = "[DUPLICATE]";
    
    $isSame = round(compareImages(get_attached_file($media_files[$level]->ID), get_attached_file($files->ID)));

    if(!$isSame) {
      $matchStatus = "[NOT DUPLICATE]";
    } else if($media_files[$level]->ID === $files->ID) {
      $matchStatus = "[ORIGINAL IMAGE]";
    } else if($isSame <= -1) {
      $matchStatus = "[NOT SUPPORTED... POSSIBLY A SVG FILE...]";
    }

      if($matchStatus !== "[NOT SUPPORTED... POSSIBLY A SVG FILE...]") {
      
        $get_posts_pages = get_posts_by_attachment_id($files->ID);

        $actionStatus = "[KEEP]";
        if(!empty($get_posts_pages['content'])) {
          $actionStatus = "[REPLACE] [UPDATE] [IMAGE] [DELETE DUPLICATE]";
          foreach($get_posts_pages['content'] as $post_page_id) {
            $content = get_post_field('post_content', $post_page_id);
            $content = runMediaReplace($files, $media_files[$level], $content,  get_post_mime_type($files->ID));
            wp_update_post(array(
              'ID' => $post_page_id,
              'post_content' => $content
            ));
            wp_delete_attachment($file['ID']);
          }
        } else if(!empty($get_posts_pages['thumbnail'])) {
          $actionStatus = "[REPLACE] [THUMBNAIL] [DELETE DUPLICATE]";

          foreach($get_posts_pages['thumbnail'] as $post_page_id) {
            wp_update_post(array(
              'ID' => $post_page_id,
              'post_parent' => $media_files[$level]->ID
            ));
            wp_delete_attachment($files->ID);
          }

        } else {
          $actionStatus = "[KEEP FILE]";
        }
    }

    echo "- Image ". $media_files[$level]->guid." compare to ". $files->guid."... ".$matchStatus."... ".$actionStatus."... <br>";

    flush();
    ob_flush();

    if($count === 10) {
     //die();
    }
    $count++;
    if(count($media_files) === $count) {
      flush();
      ob_flush();
      $level++;
      doHashCompareImages($media_files, $level);
    }
    flush();
    ob_flush();
  }

}
flush();
ob_flush();
if($_GET['hashCompareCleanUp'] == "y") {
//doHashCompareImages($media_files, 0);
die();
}

function filter($name){
  global $carousel;
  // create array to store match counts
  $matches = array();
  foreach($carousel as $image) {
      if(!array_key_exists($name, $matches))
          $matches[$name] = 0; // initialize array keys

      if(strpos($image, $name) === 0)
          $matches[$name]++; // add to the count
  }
  // got the counts, do the outputs
  foreach($carousel as $image) {
      $class_name = 'container'; // default (only one match)
      if($matches[$name] > 1) // get number of matches from previous loop
          $class_name = 'box';
      $html = "<div class='%s'><img src='imgs/%s'/></div>" . PHP_EOL;
      echo sprintf($html, $class_name, $image); // output formatted string
  }
}


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

function endsWithNumber($string){
  $len = strlen($string);
  if($len === 0){
      return false;
  }
  return is_numeric($string[$len-1]);
}

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

function delete_wp_media($id) {
  return wp_delete_attachment($id, true);;
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
