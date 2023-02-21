<?php
ob_implicit_flush(true);
ob_start();

error_reporting(1);


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

function getMediaFiles($dir) {
    $rootDir = realpath($_SERVER["DOCUMENT_ROOT"]);

    $files = [];
    $di = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
    $i = 0;
    foreach (new RecursiveIteratorIterator($di) as $filename => $file) {
        $files[$i]['filename'] = "..".str_replace($rootDir, "", $filename);
        $files[$i]['bytes'] =  $file->getSize();
        $i++;
    }
    asort($files);
    return array_values($files);
}

function output($str) {
    echo $str;
    ob_end_flush();
    ob_flush();
    flush();
    ob_start();
}

function doesMediaExistinWP($filename) {
    global $wpdb;
    $filename = str_replace("../", "", $filename);
    $image_src =  _wp_relative_upload_path( $filename );
    $query = "SELECT ID FROM {$wpdb->posts} WHERE guid LIKE '%$image_src%'";
    $ID = intval($wpdb->get_var($query));
    $wpdb->close();
    return $ID;
}

function doMediaClean($getMediaFiles, $currentPage = 0) {
    
    $count = 0;
    $totalMediaCount = count($getMediaFiles);
    $perChunk = 50;
    $pages = ceil($totalMediaCount / $perChunk);

    output("<br>"."- <b>Checking for duplicates on page ".$currentPage." out of ".$pages."..."."</b><br><br>");

    $getMediaFilesChunk = array_chunk($getMediaFiles, $perChunk);

    foreach($getMediaFilesChunk[$currentPage] as $index => $file) {
       
        set_time_limit(5600);

        $fileID = doesMediaExistinWP($file['filename']);

        if($fileID) {
            output("-- Media file". $file['filename']." does <span style='color:green'> exists</span>..."."<br>");
        } else {
            output("-- Media file". $file['filename']." does <span style='color:red'>not exist</span>..."."<br>");
            unlink($file['filename']);
        }

        $count++;

        if($count === $perChunk && $currentPage !== $pages) {
            $currentPage++;
            /*if( $currentPage === 1) {
                die();
            }*/
            gc_collect_cycles();
            sleep(1);
            doMediaClean($getMediaFiles, $currentPage);
        } 

    }
}

$uploadDir = wp_get_upload_dir();
$basedir = $uploadDir['basedir'];
$getMediaFiles = getMediaFiles($basedir);

echo "<h1>PLEASE RUN THE CLI COMMAND <code style='color:red'>wp media regenerate</code> AFTER USING THIS TOOL!</h1>";

doMediaClean($getMediaFiles);

ob_end_flush(); 
