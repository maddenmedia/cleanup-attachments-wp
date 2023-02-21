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
    return $ID;
}

function isMediaIndexFile($filename) {
    preg_match('/-\d+x\d+/', $filename, $matches);
    if(!empty($matches)) {
        return true;
    }
    return false;
}

function isFilenameAppenedWithDigit($filename) {
    preg_match('\d+\.\w+/', $filename, $matches);
    print_R($matches);
    if(!empty($matches)) {
        return $matches;
    }
    return false;
}

function getPostContent($fileID, $orginalFile) {
    $get_posts_pages = get_posts_by_attachment_id($fileID);
    if(!empty($get_posts_pages['content']) && !empty($get_posts_pages['thumbnail'])) {

        foreach($get_posts_pages['content'] as $post_page_id) {
            $content = get_post_field('post_content', $post_page_id);
            $content = runMediaReplace($file, $orginalFile, $content, get_post_mime_type($fileID));

            print_r($content);
            /*update_wp_post($post_page_id, $content, 'content');
            delete_wp_media($fileID);*/
          }

          foreach($get_posts_pages['thumbnail'] as $post_page_id) {
            /*update_wp_post($post_page_id, $fileID, 'id');
            delete_wp_media($fileID);*/
          }
    
    } else if(!empty($get_posts_pages['content'])) {

        foreach($get_posts_pages['content'] as $post_page_id) {
            /*$content = get_post_field('post_content', $post_page_id);
            $content = runMediaReplace($file, $orginalFile, $content, get_post_mime_type($fileID));
            update_wp_post($post_page_id, $content, 'content');
            delete_wp_media($fileID);*/
        }

    } else if(!empty($get_posts_pages['thumbnail'])) {

        foreach($get_posts_pages['thumbnail'] as $post_page_id) {
            /*update_wp_post($post_page_id, $fileID, 'id');
            delete_wp_media($fileID);*/
        }
        
    }
}

function doMediaClean($getMediaFiles, $level = 0, $currentPage = 0) {
    
    $count = 0;
    $totalMediaCount = count($getMediaFiles);
    $perChunk = 50;
    $currentLevel = $level;
    $pages = ceil($totalMediaCount / $perChunk);

    output("<br>"."- <b>Checking media file ". $getMediaFiles[$level]['filename']." for duplicates on page ".$currentPage." out of ".$pages." on level ".$level."..."."</b><br>");

    $compareMachine = new compareImages($getMediaFiles[$level]['filename']);

    $getMediaFilesChunk = array_chunk($getMediaFiles, $perChunk);

    foreach($getMediaFilesChunk[$currentPage] as $index => $file) {
       
        set_time_limit(5600);

        if($file['filename'] === $getMediaFiles[$level]['filename']) {

            output("-- Orginal File Not Being Checked... SKIPPING..."."<br>");

        } else {
            
            $diff = $compareMachine->compareWith($file['filename']);

            if($diff < 11) {

                $fileID = doesMediaExistinWP($file['filename']);
                $isFilenameAppenedWithDigit = isFilenameAppenedWithDigit($file['filename']);


                if($fileID) {

                    if(isMediaIndexFile($file['filename'])) {
                       
                        output("-- Media file ". $file['filename']." is a <span style='color:red'>match, but being used in wordpress and is an index file</span>..."."<br>");
                        //unlink($file['filename']);

                    } else if($isFilenameAppenedWithDigit) {

                        $orginalFile = (object) ['ID' => $fileID, 'path' => $getMediaFiles[$level]['filename']];
                        $duplicateFile = (object) ['ID' => $fileID, 'path' => $file['filename']];

                        output("-- Media file ". $file['filename']." is a <span style='color:red'>match, but being used in wordpress</span>..."."<br>");
                        //getPostContent($duplicateFile, $orginalFile);

                    } else {

                        //do nothing with orignal file
                        output("-- Media file ". $file['filename']." is a <span style='color:red'>match, but being used in wordpress.. this should be the orginal file</span>..."."<br>");

                    }


                } else {

                    if(isMediaIndexFile($file['filename'])) {
                        output("-- Media file ". $file['filename']." is a <span style='color:green'>match, not being used in wordpress and is an index file</span>..."."<br>");
                        //unlink($file['filename']);
                    } else {
                        output("-- Media file ". $file['filename']." is a <span style='color:green'>match, not being used in wordpress</span>..."."<br>");
                          //unlink($file['filename']);
                    }

                }

            } else {

                output("-- Media file". $file['filename']." does <span style='color:red'>not match</span>..."."<br>");

            }
        }
        $count++;

        if($count === $perChunk && $currentPage !== $pages) {
            $currentPage++;
            if( $currentPage === 3) {
                die();
            }
            gc_collect_cycles();
            doMediaClean($getMediaFiles, $level, $currentPage);
        } else if($pages === $currentPage -1) {
            $level++;
            ob_end_clean();
            doMediaClean($getMediaFiles, $level, 0);
         } else if($totalMediaCount === $level) {
            //run($indexedMedia);
         }

    }
}

$uploadDir = wp_get_upload_dir();
$basedir = $uploadDir['basedir'];
$getMediaFiles = getMediaFiles($basedir);

doMediaClean($getMediaFiles);

ob_end_flush(); 
