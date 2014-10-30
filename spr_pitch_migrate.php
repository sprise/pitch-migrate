<?php
/*
Plugin Name: Pitch Engine Content Import
Plugin URI: 
Description: Import posts from Pitch Engine. <a href="plugins.php?page=pitch-migrate.php">Settings</a>
Version: .1
Author: Sprise Media
Author URI: http://www.sprisemedia.com
*/

/*
Pitch Engine Content Import, a plugin for WordPress
Copyright (C) 2014 Sprise Media LLC (http://www.sprisemedia.com/)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

if(!defined('ABSPATH')) exit; // Exit if accessed directly

if(!class_exists('SPR_Pitch_Migrate')) {

class SPR_Pitch_Migrate {	
	var $config = array();
	var $dom = '';
	var $links = array();
	var $posts = array();
	var $nl;
	var $msg = '';
	
	public function __construct(){
		// Default setup
		$this->config['debug'] = 'n';
		$this->config['title'] = 'Pitch Engine Import';
		$this->config['slug'] = 'pitch-migrate.php';
		$this->config['post-wrap'] = '.pitch-post';
		$this->config['post-type'] = 'post';
		$this->config['src'] = __DIR__.'/src.html'; 
		$this->config['limit'] = 0;
		$this->config['sort'] = 'ASC';
		$this->posts = array();
		$this->msg = '';
		
		// Create settings page
		add_action( 'admin_menu', array($this, 'admin_pane') );
	}
	
	
	
	
	/* WP Admin Page */
	
	public function admin_pane() {		
		add_plugins_page(
			$this->config['title'], 
			$this->config['title'], 
			'edit_posts', 
			$this->config['slug'], 
			array($this, 'admin_pane_render') 
		);
	}

	public function admin_pane_render(){	
		// Page Submitted
		if(isset($_POST['submit']) && $_POST['task'] == 'do-import') $this->do_wp_import();
	
		$emstyle = 'style="font-size: .9em;"';
		
		$html = '<h1 style="padding: 40px 0 20px 0;">Pitch Engine Content Import</h1>';
		if($this->msg != '') $html .= '<p style="color:red; font-weight: bold;">'.$this->msg.'</p>';
		
		$html .= '<p>Customize options and click the button below to import from <strong>src.html</strong> (upload this to wp-content/plugins/spr-pitch-migrate).</p>';
		
		$html .= '<form method="post" action="'.site_url().'/wp-admin/admin.php?page='.$this->config['slug'].'">';
		$html .= '<input type="hidden" name="task" value="do-import" />';		
		
		$html .= '<p>Number of posts: 	<input type="text" name="limit" value="1" /></p>';
		$html .= '<p>Post type: 		<input type="text" name="post-type" value="post" /></p>';
		$html .= '<p>Order: <select name="sort"><option value="ASC">Oldest First</option><option value="DESC">Newest First</option></select></p>';
		$html .= '<p>Debug: <select name="debug"><option value="n">Off</option><option value="y">On</option></select></p>';
		$html .= '<p><input class="button-primary button-large" type="submit" name="submit" value="Import Content" /></p>';
		$html .= '</form>';
		
		echo $html;
	}	
	
	
	
	
	
	/* Setup */
	
	public function set_debug($x = 'n'){
		// Explicitly accept y only to activate
		$x = ($x == 'y' ? 'y' : 'n');
		$this->config['debug'] = $x;
	}
	
	public function set_source($src = ''){
		$this->config['src'] = $src;
		$this->showme($this->config['src'],'Source');
	}

	public function set_wrapper($el = ''){
		$this->config['post-wrap'] = $el;
		$this->showme($this->config['post-wrap'],'Wrapper element');
	}
	
	public function set_posttype($type = 'post'){
		$this->config['post-type'] = $type;
		$this->showme($this->config['post-type'],'Post Type');
	}
	


	
	/* Lets do it */
	
	public function do_wp_import(){
		require 'vendor/autoload.php';
		$this->dom = new simple_html_dom();
		
		// How many posts? Post type? Debug?
		$this->_setup_options();
				
		// Squeeze the links from the feed html
		$this->_get_links();
		
		// Get post data from each linked page
		$this->_get_posts($this->config['limit']);
				
		// Do the import
		$qty = $this->_import_posts();
		
		$this->msg = $qty.' new posts were imported.';
	}
	
	protected function _setup_options(){
		if(isset($_POST['limit']))	 $this->config['limit'] = (int) $_POST['limit']; 	 	
		if(isset($_POST['sort'])) 	 $this->config['sort'] = $_POST['sort']; 	
		
		if(isset($_POST['debug']))	 $this->set_debug($this->sanitize($_POST['debug'])); 	
		if(isset($_POST['post-type'])) $this->set_posttype($this->sanitize($_POST['post-type']));
	}
	
	protected function _get_links(){
		if(empty($this->config['src']) || !file_exists($this->config['src'])) 
			die('Invalid source file: '.$this->config['src']);
		
		$html = file_get_html($this->config['src']);
		
		// Add post links to $this->links array
		foreach($html->find($this->config['post-wrap'].' a') as $row){ 
			if($row->href == '#' || in_array($row->href,$this->links)) continue; // no dupes
			
			// Latest first
			if($this->config['sort'] == 'DESC') $this->links[] = $row->href;
		
			// Earliest posts first
			else array_unshift($this->links, $row->href);
		}
		
		$this->showme($this->links,'Links');
	}
	
	protected function _get_posts($limit = 0){
		if(empty($this->links) || $limit == 0) return;
		
		// Iterate over the links and add them to $this->posts array
		for($i = 0; count($this->posts) < $limit; $i++){
			$title = $content = $postdate = '';
			$imgs = array();
			
			// Grab the page
			$page = file_get_html($this->links[$i]);
			
			// Get the content
			$content = $this->sanitize($page->find('.content',0)->innertext);
			if(strpos($content,'{{brand') !== false) continue; // this is the feed page
			
			// Get the title
			$title = $this->sanitize($page->find('.headline-content h1',0)->plaintext);
			
			// Pull any images
			foreach($page->find('.img-container img') as $row) $imgs[] = $this->sanitize($row->src);
			
			$this->posts[] = array(
				'title' => $title,
				'content' => $content,
				//'date' => $postdate, // Pitch Engine does not provide
				'imgs' => $imgs
			);
		}
	}
	
	protected function _import_posts(){	
		$newid = 0;
		$img_base = site_url().'/uploads/'.date('Y').'/'.date('m');
		
		$count = 0;
		foreach($this->posts as $row) {
			$body = sanitize_text_field($row['content']);
			
			// Correct image urls
			if(!empty($row['imgs'])){
				foreach($row['imgs'] as $img){
					$filename = substr($img,strrpos($img,'/'));
					$new = $img_base.$filename;
					
					$body = str_replace($img, $new, $body);
				}
			}

			// Save as post			
			$postdata = array(
				'post_name'      => sanitize_title($row['title']),	
				'post_title'     => sanitize_text_field($row['title']), 				
				'post_status'    => 'publish', 					
				'post_type'      => $this->config['post-type'], 
				'post_author'    => 1,			 				
				'post_date'      => date('Y-m-d H:i:s', mktime()),
				'post_content'	 => $body
			);  
			
			//$this->showme($postdata); die();
			$newid = wp_insert_post($postdata);
			
			// Save images if app
			if(!empty($row['imgs'])){
				$tid = 0;
				
				for($i=0;$i<count($row['imgs']);$i++) {
					if(empty($row['imgs'][$i])) continue;
					
					$tmp = download_url($row['imgs'][$i]);
					
					// Set variables for storage
					// fix file filename for query strings
					preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $row['imgs'][$i], $matches);
					$file_array['name'] = basename($matches[0]);
					$file_array['tmp_name'] = $tmp;

					// If error storing temporarily, unlink
					if ( is_wp_error( $tmp ) ) {
						@unlink($file_array['tmp_name']);
						$file_array['tmp_name'] = '';
					}
					// do the validation and storage stuff
					$thumbid = media_handle_sideload( $file_array, $newid );
					
					// If error storing permanently, unlink
					if ( is_wp_error($thumbid) ) {
						@unlink($file_array['tmp_name']);
						//return $thumbid;
					}

					$args = array();
					
					//$img_id = wp_insert_attachment( $args, $row['imgs'][$i], $newid );
					
					// Save a Featured image
					if($i == 0) set_post_thumbnail( $newid, $thumbid );
				}
			}
			
			$this->showme($row['title'],'New');
			$count++;
		}
		
		$this->showme($count,'Rows Imported');
		
		return $count;		
	}


	
	/* Helpers */
		
	public function showme($x,$m = ''){
		if($this->config['debug'] != 'y') return;
		
		echo $m.'<pre>';
		print_r($x);
		echo '</pre>';
	}
	
	protected function sanitize($var){
		return trim(filter_var($var, FILTER_SANITIZE_STRING, 'FILTER_ENCODE_HIGH'));
	}
	
}

if(!isset($spr_pitch) || !is_object($spr_pitch)) $spr_pitch = new SPR_Pitch_Migrate();  
} // class exists check
