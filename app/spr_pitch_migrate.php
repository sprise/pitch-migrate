<?php

/*
 * This class grabs links from a supplied html file (you grab from a PitchEngine.com account)
 * and collects all posts and images, preparing them for use.
 */
 

class SPR_Pitch_Migrate {	
	var $config = array();
	var $dom = '';
	var $links = array();
	var $posts = array();
	var $nl;
	
	public function __construct(){
		// Default setup
		$this->config['debug'] = 'n';
		$this->config['post-wrap'] = '.pitch-post';
		$this->config['post-type'] = 'post';
		$this->config['src'] = 'src.html'; 
		$this->nl = "\n";
		$this->posts = array();
		
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
	
	public function do_wp_import($limit = 0){
		$limit = (int) $limit;
				
		// Squeeze the links from the feed html
		$this->_get_links();
		
		// Get post data from each linked page
		$this->_get_posts($limit);
				
		// Do the import
		$this->_import_posts();
				
		return $this->posts;
	}
	
	public function do_wp_import_xml($limit = 0){
		$xml  = $this->nl.'<?xml version="1.0" encoding="UTF-8" ?>';
		$xml .= $this->nl.$this->tab(1).'<channel>';
		$xml .= $this->nl.$this->tab(2).'<wp:wxr_version>1.2</wp:wxr_version>';
		
		// Setup and get post data
		$this->_get_links();
		$this->_get_posts();
		
		// Build XML 
		if(!empty($this->posts)) $xml .= $this->_posts_to_xml($limit);
		
		$xml .= $this->nl.$this->tab(1).'</channel>';
		$xml .= $this->nl.'</xml>';
		
		return $xml;
	}
	
	protected function _get_links(){
		if(empty($this->config['src']) || !file_exists($this->config['src'])) die('Invalid source file.');
		
		$html = file_get_html($this->config['src']);
		
		// Add post links to $this->links array
		foreach($html->find($this->config['post-wrap'].' a') as $row) 
			if($row->href != '#' && !in_array($row->href,$this->links)) $this->links[] = $row->href;
		
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
			
			$xml .= $this->nl.$this->tab(2).'</item>';
			$xml .= $imgs;	
				
			$idcount++;	
			$count++;
		}	
		
		return $xml;
	}
	
	public function _posts_to_rss($limit = 0){
		$ret = '';
		$count = 0;
		
		$idcount= 1;
		
		foreach($this->posts as $row) {
			if($limit > 0 && $count == $limit) continue;
			$imgs = '';
			$ret .= '
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
			
			$ret .= $this->nl.$this->tab(2).'</item>';
			$ret .= $imgs;	
				
			$idcount++;	
			$count++;
		}	
		
		return $ret;
	}

	protected function _image_attachment($src,$id = 0){
		$img = '
			<wp:attachment_url>'.$src.'</wp:attachment_url>
			<wp:postmeta>
				<wp:meta_key>_wp_attached_file</wp:meta_key>
				<wp:meta_value><![CDATA['.$src.']]></wp:meta_value>
			</wp:postmeta>';
		return $img;
	}

	protected function _import_posts(){
		$temp = $this->posts;
		rsort($temp); // Earliest first
		$newid = 0;
		
		$count = 0;
		foreach($temp as $row) {
			// Save as post			
			$postdata = array(
				'post_name'      => sanitize_title($row['title']),	
				'post_title'     => sanitize_text_field($row['title']), 				
				'post_status'    => 'publish', 					
				'post_type'      => $this->config['post-type'], 
				'post_author'    => 1,			 				
				'post_date'      => date('Y-m-d H:i:s', mktime())
			);  
			//$newid = wp_insert_post($postdata);
			
			// Save images if app
			if(!empty($row['imgs'])){
				$tid = 0;
				foreach($row['imgs'] as $img) {
					$img_id = wp_insert_attachment( $args, $file = false, $newid )
			}
			
			$this->showme($row['title'],'New');
			$count++;
		}
		
		$this->showme($count,'Rows Imported');		
	}


	
	/* Helpers */
		
	public function showme($x,$m = ''){
		if($this->config['debug'] != 'y') return;
		
		echo $m.'<pre>';
		print_r($x);
		echo '</pre>';
	}
	
	public function sanitize($var){
		$var = trim(strip_tags($var, '<p><a><b><em><strong><br><hr>'));
		//$var = filter_var($var, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_ENCODE_HIGH);		
		
		//$var = str_replace('â€™','&rsquo;', $var);
	
		return $var;
	}
	
	public function tab($x = 0){
		$ret = "";
		$x = (int) $x;
		
		if($x == 0) return $ret;
		
		for($i = 0; $i < $x; $i++) $ret .= "\t";
		
		return $ret;
	}
	
}


	function sanitize_text_field() {} 
	function sanitize_title() {} 
