<?php 
/**
 * Plugin Name: Meta Data Importer
 * Description: This plugin allows you to upload meta data from a CSV file to your Yoast settings for all your pages/posts. This is NOT an official Yoast plugin/addon.
 * Version: 1.2.2
 * Requires PHP: 5.6.39
 * Author: Matthew Sudekum
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */


$csv_arr = array();
$column_count = 0;

add_action( 'admin_menu', 'mdi_menu_setup' );
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'mdi_add_tool_link' );

function mdi_add_tool_link( $settings ) {
   $settings[] = '<a href="'. get_admin_url(null, 'tools.php?page=meta-data-importer') .'"><b>Use Tool</b></a>';
   return array_reverse($settings);
}

function mdi_menu_setup() {
    add_submenu_page( 'tools.php', 'Meta Data Importer', 'Meta Data Importer','manage_options', 'meta-data-importer', 'mdi_display' );
}

function mdi_display() {
	include_once("style.php");
    ?>
    <h1 style="margin-bottom:40px">Meta Data Importer for Yoast</h1>
    <h2>Import a CSV file:</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="file" id="file" name="file" accept=".csv" >
        <br>
        <input type="submit" style="margin-top:10px;" class="button button-primary" value="Import">
    </form>

    <?php mdi_main(); ?>
   
    <div style="margin-top: 40px; padding: 5px; border:1px solid grey; width: 300px; background-color:rgba(0,0,200,0.025);">
        <h3 style="color:grey; margin:auto; text-decoration:underline;">Note</h3>
        <p>When making the CSV file, include a header line like this and make sure the columns follow this order:</p>
        <table>
            <tr><th>ID</th><th>Keyphrase</th><th>URL</th><th>Title</th><th>Description</th></tr>
        </table>
        <hr style="margin-top:13px;">
        <p>Also make sure to fill out every column for each page/post you edit, even if some meta data is to remain the same. Otherwise, don't make a row for the page(s) you don't want edited.</p>
    </div>
    <?php
}

function mdi_main(){
    if(isset($_FILES["file"])){
        if(mdi_validFileType($_FILES["file"])){ //if file type is csv..
            global $column_count;
            mdi_extract_data();
            if($column_count != 5){
                return;
            }
            mdi_update_meta();
            echo "<p style='color:green;'>Yoast meta data update was a success!</p>";
        }
        else {
            echo "<p style='color:red;'>Not a supported file type.</p>";
        }
    }
}

function mdi_validFileType($file){
    return str_contains($file["type"], "csv");
}

function mdi_extract_data(){
    $f_pointer = fopen($_FILES["file"]["tmp_name"],"r");
    global $csv_arr, $column_count;
    $counted_columns = false;
    while(!feof($f_pointer)){ //while moving through csv, assign each line as element of array
        $line = fgetcsv($f_pointer);
        if($counted_columns == false){
            $column_count = count($line);
            if($column_count != 5){
                echo "<p style='color:red;'>Unexpected number of columns in file.</p>";
                return;
            }
        }
        array_push($csv_arr, $line);
    }
    $csv_arr = array_slice($csv_arr, 1);//removes header line of file from array
}

function mdi_update_meta(){ 
    global $csv_arr;
    foreach($csv_arr as $post){
        if(!empty($post)){
            $post = mdi_sanitize_data($post);
			if(get_post($post[0])!= null){
				mdi_update_title($post[0], $post[3]);
				mdi_update_desc($post[0], $post[4]);
				mdi_update_keywords($post[0], $post[1]);
				mdi_update_url($post[0],$post[2]);
			}
        }
    }
}

function mdi_sanitize_data($array){ //sanitizes any data to be used
    $array = [
		preg_replace("/[^0-9]/", "", $array[0]),
    		htmlspecialchars($array[1]),
		filter_var($array[2], FILTER_SANITIZE_URL),
    		htmlspecialchars($array[3]),
		htmlspecialchars($array[4])
	];
    return $array;
}

function mdi_update_url($id, $url) {
    //makes sure only the slug is passed on
    $url = explode("/", $url);
    $url = array_reverse($url);
    $url = ($url[0] != "" ? $url[0] : $url[1]);

    if($url != get_post($id)->post_name){//updates if new slug differs from current slug
        wp_update_post([
            "ID" => $id,
            "post_name" => $url,
        ]);
    }
}

function mdi_update_title($id, $title) {
    update_post_meta( $id, '_yoast_wpseo_title', $title );
	update_post_meta( $id, '_yoast_wpseo_opengraph-title', $title );
	update_post_meta( $id, '_yoast_wpseo_twitter-title', $title );
}

function mdi_update_desc($id, $desc) {
    update_post_meta( $id, '_yoast_wpseo_metadesc', $desc );
	update_post_meta( $id, '_yoast_wpseo_opengraph-description', $desc );
	update_post_meta( $id, '_yoast_wpseo_twitter-description', $desc );
}

function mdi_update_keywords($id, $kw) {
    update_post_meta( $id, '_yoast_wpseo_focuskw', $kw );
}
