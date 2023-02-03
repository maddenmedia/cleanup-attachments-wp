<?php

function delete_wp_media($id) {

    return wp_delete_attachment($id, true);

}

function endsWithNumber($string){

    $len = strlen($string);

    if($len === 0){
        return false;
    }

    return is_numeric($string[$len-1]);
}

function formatBytes($size, $precision = 2) {

    $base = log($size, 1024);
    $suffixes = array('', 'K', 'M', 'G', 'T');   

    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];

}

function runMediaReplace($duplicated_file, $orginal_file, $content, $type) {

    if(str_contains($type, "image/")) {
      $content =  preg_replace("/<!-- wp:image {\"id\":".$duplicated_file['ID']."/", "<!-- wp:image {\"id\":".$orginal_file['ID'], $content);
      $content =  preg_replace("/class=\"wp-image-".$duplicated_file['ID']."/","/class=\"wp-image-".$orginal_file['ID']."/", $content);
      $content = str_replace($duplicated_file['guid'], $orginal_file['guid'], $content);
      return $content;
    }

    return $content;

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
      
      $attachment_url = parse_url($attachment_url, PHP_URL_PATH);

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

function dirToArray($dir) {
    
    $result = array();

    $cdir = scandir($dir);
 
    foreach ($cdir as $key => $value) {
       
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);

        if (!in_array($value,array(".",".."))) {
 
            if (is_dir($path)) {

                $result[] = dirToArray($path);

            } else {

                $result[] = $path;
    
            } 
 
        }
 
    }
 
    return $result;

 }
