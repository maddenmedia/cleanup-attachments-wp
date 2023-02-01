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
  $parsed = parse_url( wp_get_attachment_url( $file->ID ) );
  $url = dirname( $parsed [ 'path' ] ) . '/' . rawurlencode( basename( $parsed[ 'path' ] ) );
  $files[$count]['file_media'] = $url;
  $count++;
}

sort($files);

$directory = '../wp-content/uploads/';
$scanned_directory = dirToArray(dirname(__FILE__)."/".$directory);

$scannedFilesTmp = (object) array('aFlat' => array());
array_walk_recursive($scanned_directory, create_function('&$v, $k, &$t', '$t->aFlat[] = $v;'), $scannedFilesTmp);

$filesTmp = (object) array('aFlat' => array());
array_walk_recursive($files, create_function('&$v, $k, &$t', '$t->aFlat[] = $v;'), $filesTmp);

$c = 0;
foreach ( $scannedFilesTmp->aFlat  as $i => $file) {
    
    $filePath = explode("/web/", $file);

    if (in_array("/".$filePath[1], $filesTmp->aFlat)) {
        //do nothing
    } else {

       echo $file." is being deleted...<br>";
       unlink($file);
    }

}

ob_end_flush(); 
