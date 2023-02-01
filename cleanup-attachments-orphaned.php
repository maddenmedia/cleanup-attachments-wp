<!doctype html><html>
<head><style>label { font-weight: bold; }</style></head>
<body>
<?php

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


  // Get all media files
  $args = array(
    'post_type' => 'attachment',
    'post_status' => 'any',
    'posts_per_page' => -1,
  );
  $query = new WP_Query( $args );
  $media_files = $query->posts;

  foreach ( $media_files as $file ) {
  $file_name = basename( $file->guid );
  $ID = $file->ID;
  $filesize =  @filesize( get_attached_file($ID));
  if(!$filesize) {
  echo  "Deleted orphaned media from wp_posts: <b>". $file_name."</b><br>";
  wp_delete_attachment($ID);
  }

  }
?>
</body></html>
