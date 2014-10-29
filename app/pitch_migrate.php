<?php

/*
 * This class grabs links from a supplied html file (you grab from a PitchEngine.com account)
 * and collects all posts and images, converting to a Wordpress XML import file.
 */
 

class Pitch_Migrate {	
	var $config = array();
	var $dom = '';
	var $links = array();
	var $posts = array();
	
	public function __construct(){
		// Default setup
		$this->config['debug'] = 'n';
		$this->config['post-wrap'] = '.pitch-post';
		$this->config['post-type'] = 'post';
		$this->config['src'] = 'src.html'; 
		
		// Instantiate simple_html_dom		
		$this->dom = new simple_html_dom();
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
	
	public function create_wp_import($limit = 0){
		$xml = "\r\n".'<?xml version="1.0" encoding="UTF-8" ?>';
		$xml .= "\r\n\t".'<channel>';
		$xml .= "\r\n\t\t".'<wp:wxr_version>1.2</wp:wxr_version>';
	
		
		// Setup and get post data
		$this->_get_links();
		$this->_get_posts();
		
		// Build XML 
		if(!empty($this->posts)) $xml .= $this->_posts_to_xml($limit);
		
		$xml .= "\r\n\t".'</channel>';
		$xml .= "\r\n".'</xml>';
		
		return $xml;
	}
	
	public function _get_links(){
		if(empty($this->config['src']) || !file_exists($this->config['src'])) die('Invalid source file.');
		
		$html = file_get_html($this->config['src']);
		
		// Add post links to $this->links array
		foreach($html->find($this->config['post-wrap'].' a') as $row) 
			if($row->href != '#' && !in_array($row->href,$this->links)) $this->links[] = $row->href;
		
		$this->showme($this->links,'Links');
	}
	
	public function _get_posts(){
		$this->posts = array();
		if(empty($this->links)) return;
		
		$this->posts[] = array(
			'title' => 'test',
			'content' => 'blah blah blah',
			'imgs' => array(
				'http://localhost/steelcrest/wp-content/uploads/2014/06/Smaller-Bronze-2.jpg'
			)
		);
	}
	
	public function _posts_to_xml($limit = 0){
		$xml = '';
		$count = 0;
		
		$idcount= 1;
		
		foreach($this->posts as $row) {
			if($limit > 0 && $count == $limit) continue;
			$imgs = '';
			$xml .= '
		<item>
			<pubDate>'.date('Y-m-d H:i:s',mktime()).'</pubDate>
			<wp:post_date>'.date('Y-m-d H:i:s',mktime()).'</wp:post_date>
			<wp:post_name>'.'asd'.'</wp:post_name>
			<wp:status>publish</wp:status>
			<title>'.$row['title'].'</title>
			<wp:post_type>'.$this->config['post-type'].'</wp:post_type>
			<content:encoded><![CDATA['.$row['content'].']]></content:encoded>';
			
			if(!empty($row['imgs'])) {
				foreach($row['imgs'] as $img){
					$imgs .= $this->_image_attachment($img, $idcount);
					$idcount++;
				}
			}
			
			$xml .='			
		</item>';
			$xml .= $imgs;	
				
			$idcount++;	
			$count++;
		}	
		
		return $xml;
	}

	public function _image_attachment($src,$id = 0){
		$img = '
			<wp:attachment_url>'.$src.'</wp:attachment_url>
			<wp:postmeta>
				<wp:meta_key>_wp_attached_file</wp:meta_key>
				<wp:meta_value><![CDATA['.$src.']]></wp:meta_value>
			</wp:postmeta>';
		return $img;
	}

	
	/* Helpers */
		
	public function showme($x,$m = ''){
		if($this->config['debug'] != 'y') return;
		
		echo $m.'<pre>';
		print_r($x);
		echo '</pre>';
	}
	
	public function sanitize($var){
		return filter_var($var, FILTER_SANITIZE_STRING);		
	}
}
