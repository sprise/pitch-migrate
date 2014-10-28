<?php

/*
 * This class grabs links from a supplied html file (you grab from a PitchEngine.com account)
 * and collects all posts and images, converting to a Wordpress XML import file.
 */
 

class Pitch_Migrate {	
	var $config = array();
	var $dom = '';
	var $links = array();
	
	public function __construct(){
		// Default setup
		$this->config['debug'] = 'n';
		$this->config['post-wrap'] = '.pitch-post';
		$this->config['src'] = 'src.html'; 
		
		// Instantiate simple_html_dom		
		$this->dom = new simple_html_dom();
	}
	
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
	
	public function get_links(){
		if(empty($this->config['src']) || !file_exists($this->config['src'])) die('Invalid source file.');
		
		$html = file_get_html($this->config['src']);
		
		// Add post links to $this->links array
		foreach($html->find($this->config['post-wrap'].' a') as $row) 
			if($row->href != '#' && !in_array($row->href,$this->links)) $this->links[] = $row->href;
		
		$this->showme($this->links,'Links');
	}
	
	public function showme($x,$m = ''){
		if($this->config['debug'] != 'y') return;
		
		echo $m.'<pre>';
		print_r($x);
		echo '</pre>';
	}
	
	public function create_xml($posts){
		$xml = '';
		
		foreach ($posts as $row) {
			$title = $row[1]; 
			
			$postdata = array(
				'post_name'      => sanitize_title($title),		//[ <string> ] // The name (slug) for your post
				'post_title'     => $title, 					//[ <string> ] // The title of your post.
				'post_status'    => 'publish', 					//[ 'draft' | 'publish' | 'pending'| 'future' | 'private' 
				'post_type'      => 'vendors', 					//[ 'post' | 'page' | 'link' | 'nav_menu_item' | custom 
				'post_author'    => 1,			 				// The user ID number of the author
				'post_date'      => date('Y-m-d H:i:s',mktime()),	// The time post was made.
				'comment_status' => 'closed', 					//[ 'closed' | 'open' ] 
			);  
						
			$newid = wp_insert_post($postdata);
			
			$xml .= '<br>'.$title;
		}	
		
	}
}
