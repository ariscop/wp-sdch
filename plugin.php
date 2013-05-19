<?php namespace wp\plugin\ariscop\sdch;
/**
 * @package WP_SDCH
 * @version 0.01
 */
/*
Plugin Name: SDCH
Plugin URI: https://www.ariscop.net/
Description: Add sdch compression suport to wordpress
Author: Andrew Cook
Version: 0.01
Author URI: https://www.ariscop.net/
*/

require('sdch.php');

/**
 * PLAN:
 * Dicitonary data collector
 * 
 * given a path (and probably other parameters) it will
 * collect generated pages from anon users and from these
 * generate a suitable dictionary.
 * 
 * data stored in database.
 * 
 * path, domain, data
 * /theater/twilight-sparkle, www.bronystate.net, (html)/(fs path)?
 */


add_action('admin_menu', function() {
	add_options_page('Sdch', 'SDCH', 'manage_options', 'sdch/options.php');
});


/**
 * PLAN:
 *
 * //instance
 * $sdch = new SDCH();
 *
 * //SDCH Object handles files, stored in rainbow-cache and/or database
 * //database probably would perform better than re-reading every dict
 * //on every request
 *
 * //emit Get-Dictionary headers if apropriate
 * $sdch->advertise();
 * [do site work]
 * //how to handle encoding
 *
 * //and respond if possible
 * if($sdch->canCompress()) {
 *     $response = $sdch->compress($page)
 * }
 *
 *
 * //DICTIONARY CREATION
 * $dict = $sdch->newDict()
 *
 * //Required (will be gleaned from $_SERVER if not provided)
 * $dict->domain('domain fragment')
 *
 * //optional
 * $dict->path('url fragment')
 * $dict->maxAge(age)
 *
 * //and finally the data
 * $dict->addData(data)
 *
 * //this can be done in any order
 * //save will commit the dictionary to disk with a path
 * //based off its hash
 * //TODO: save logic on dict object or the 'managment' object
 * $dict->save()
 *
 * note: emit "X-Sdch-Encode: 0" when not encoding
 */

class WP_Sdch extends \net\ariscop\sdch\SDCH {
// 	public function __construct() {
// 		parent::__construct();
// 		//maybe?
// 		//$this->load();
// 	}
	
	public function save() {
		update_option('sdch_dicts', $this->dicts);
	}
	
	public function load() {
		$dicts = get_option('sdch_dicts', array());
		
		if(is_array($dicts))
			$this->dicts = $dicts;
	}
}

$sdch = new WP_Sdch();
$sdch->load();

//Commence the Hackery, hijack requests here if it's for a dictionary
preg_match(':^/@dict/([A-Za-z0-9+/]+):', $_SERVER['REQUEST_URI'], $match);
if(count($match) > 1) {
	$dict = $sdch->get($match[1]);
	if($dict !== False) {
		$raw = $dict->__toString();
		$len = strlen($raw);
		header('Content-Type: application/x-sdch-dictionary');
		header("Content-Length: {$len}");
		print($raw);
		exit();
	}
}
	

if(is_admin()) {
	//don't compress admin pages
	header('X-Sdch-Encode: 0');
	return;
}

//TODO: Cache-Control: private=Get-Dictionary
header('Vary: Accept-Encoding, Avail-Dictionary');

if(strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'sdch') === False)
	//no sdch suport
	return;

$sdch->advertise();

ob_start(function($html) use ($sdch) {
	$data = $sdch->compress($html);
	if(is_string($data)) {
		$data = gzencode($data);
		$len = strlen($data);
		header('Content-Encoding: sdch, gzip');
		header("Content-Length: {$len}");
		return $data;
	}
	//no dictionary or something broke
	//either way, signal that we are 
	//intentionally not encoding
	header('X-Sdch-Encode: 0');
	return $html;
	
});
