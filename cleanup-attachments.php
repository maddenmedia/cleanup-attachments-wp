<!doctype html><html>
<head><style>label { font-weight: bold; }</style></head>
<body>
<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

set_time_limit(0); 
/*ignore_user_abort(true);*/
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
echo "<br/><hr><code>";
echo "<br/>"."Start: ".date("r", $startTime->getTimestamp())."<br/>";

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

function groupMediaFilesByFileName($media_files) {
    // Group media files by file name
     $files = [];

      foreach ( $media_files as $file ) {
        $file_name = basename( $file->guid );
        if ( !isset( $files[$file_name] ) ) {
          $files[$file_name] = array();
        }
        $filesize =  @filesize( get_attached_file( $file->ID ));
        $files[$file_name][$file->ID]['ID'] = $file->ID;
        $files[$file_name][$file->ID]['guid'] = $file->guid;
        $files[$file_name][$file->ID]['post_parent'] = $file->post_parent;
        $files[$file_name][$file->ID]['post_date'] = $file->post_date;
        $files[$file_name][$file->ID]['filesize'] = $filesize;
      }
    return $files;
}

 function findDuplicateFiles($files) {
   // Find duplicate files
    $duplicates = [];
    foreach ( $files as $file_name => $file_group ) {
      if ( count( $file_group ) > 1 ) {
        $duplicates[$file_name] = $file_group;
      }
    }
    return  $duplicates;
  }

function date_compare($a, $b) {
  $t1 = strtotime($a['post_date']);
  $t2 = strtotime($b['post_date']);
  return $t1 - $t2;
}

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

      $mimeA =  mime_content_type($pathA);
      $mimeB =  mime_content_type($pathB);

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
doHashCompareImages($media_files, 0);
die();
}

foreach ( findDuplicateFiles(groupMediaFilesByFileName($media_files)) as $file_name => $file_group ) { 


  set_time_limit(5600);
  
  flush();
  ob_flush();

  echo "<b> Checking media file ".$file_name." for duplicates...</b><br>";

  $prev_post_parent = null;

  usort( $file_group, 'date_compare');

   $count = 1;
   $orginal_guid = null;
   foreach ( $file_group as $id => $file ) {
    
    set_time_limit(5600);

		flush();
		ob_flush();
    echo "<ul>";

    if($count === 1) {

      $orginal_file = $file;

      echo "<li>Located the orginal media file...
      <ul>
      <li>ID: ".$file['ID']."</li>
      <li>GUID: ".$file['guid']."</li>
      <li>Date Created: ".$file['post_date']."</li>
      <li>File Size: ".$file['filesize']." bytes</li>
      <li>Action: Keep Media</li>
      </ul>
      </li>";

    } else {

      //replace in pages and posts

      $get_posts_pages = get_posts_by_attachment_id($file['ID']);

      if(!empty($get_posts_pages['content'])) {

        echo "<li>Located a in use duplicate media file...
        <ul>
        <li>ID: ".$file['ID']."</li>
        <li>GUID: ".$file['guid']."</li>
        <li>Page/Post ID(s): ".implode(",", $get_posts_pages['content'])."</li>
        <li>Date Created: ".$file['post_date']."</li>
        <li>File Size: ".$file['filesize']." bytes</li>
        <li>Action: Replace Media and Delete Media</li>
        </ul>
        </li>";

        echo "<li><b>Replacing and Deleting Media...</b></li>";

        foreach($get_posts_pages['content'] as $post_page_id) {
          $content = get_post_field('post_content', $post_page_id);
          $content = runMediaReplace($file, $orginal_file, $content,  get_post_mime_type($file['ID']));
          echo "<li>".$file['guid']." has been replaced with ".$orginal_file['guid']." and has been deleted... you saved ".formatBytes($file['filesize'])."... [COMPLETED]...</li>";
          wp_update_post(array(
            'ID' => $post_page_id,
            'post_content' => $content
          ));
          wp_delete_attachment($file['ID']);
        }

      } else if(!empty($get_posts_pages['thumbnail'])) {

        echo "<li>Located a in use duplicate thumbnail file...
        <ul>
        <li>ID: ".$file['ID']."</li>
        <li>GUID: ".$file['guid']."</li>
        <li>Page/Post ID(s): ".implode(",", $get_posts_pages['thumbnail'])."</li>
        <li>Date Created: ".$file['post_date']."</li>
        <li>File Size: ".$file['filesize']." bytes</li>
        <li>Action: Replace Thumbnail and Delete Thumbnail</li>
        </ul>
        </li>";

        echo "<li><b>Replacing and Deleting Thumbnail...</b></li>";

        foreach($get_posts_pages['thumbnail'] as $post_page_id) {
          echo "<li>".$file['guid']." has been replaced with ".$orginal_file['guid']." and has been deleted... you saved ".formatBytes($file['filesize'])."... [COMPLETED]...</li>";
          wp_update_post(array(
            'ID' => $post_page_id,
            'post_parent' => $orginal_file['ID']
          ));
          wp_delete_attachment($file['ID']);
        }

      } else {
        echo "<li>".$file['guid']." is not being used and has been deleted... you saved ".formatBytes($file['filesize'])."... [COMPLETED]...</li>";
        wp_delete_attachment($file['ID']);
      }

    }

      echo "</ul>";

    $count++;

   }

 }

// duration
$interval = $startTime->diff(new DateTime());

// end our output
echo "<br /><br/>"."Finish: ".date("r")."<br/>";
echo $interval->format("Duration: %H:%I:%S")."<br/>";
	
echo "Entries fully processed: ".$counter."<br/>";
echo "</code>";
ob_end_flush(); 
?>
</body></html>
