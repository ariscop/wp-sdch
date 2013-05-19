<?php namespace net\ariscop\sdch;

/* VCDIFF Encoder originally written by JaredWilliams
 * https://gist.github.com/886798
 * slightly modified for this project
 * added a refference to the encoding dict and
 * removed the scraping via output buffer
 */

/*
	Implementation of the encoder of http://www.ietf.org/rfc/rfc3284.txt
	Based initially on the encoder in http://code.google.com/p/open-vcdiff/
*/
class SDCHVCDiffEncoder
{
	const VCD_NOOP = 0;
	const VCD_ADD  = 1;
	const VCD_RUN  = 2;
	const VCD_COPY = 3;

	const VCD_SELF_MODE = 0;
	const VCD_HERE_MODE = 1;
	const VCD_FIRST_NEAR_MODE = 2;

	const VCD_SOURCE = "\x01";

	const NEAR_CACHE_SIZE = 4;
	const SAME_CACHE_SIZE = 3;

	private $sourceSize;
	private $dict;

	private $instructions;
	private $lastOpcodeIndex;
	private $addresses;

	private $targetSize;

	/* AddrCache */
	private $nextSlot;
	private $nearAddresses;
	private $sameAddresses;

	/* How much data is in PHP output buffer */
	private $dataSize;

	/* Optimization: buffer copy instructions to see if can combine them. */
	private $copyOffset;
	private $copySize;
	
	/* Data to be added */
	private $addData;


	/*
	 \xff is an invalid opcode here
	*/
	static private $firstOpcodes = array(
			1 => "\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12",
			2 => "\x00",
			3 => "\x13\xff\xff\xff\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x20\x21\x22",
			4 => "\x23\xff\xff\xff\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f\x30\x31\x32",
			5 => "\x33\xff\xff\xff\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f\x40\x41\x42",
			6 => "\x43\xff\xff\xff\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f\x50\x51\x52",
			7 => "\x53\xff\xff\xff\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f\x60\x61\x62",
			8 => "\x63\xff\xff\xff\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f\x70\x71\x72",
			9 => "\x73\xff\xff\xff\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e\x7f\x80\x81\x82",
			10 => "\x83\xff\xff\xff\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f\x90\x91\x92",
			11 => "\x93\xff\xff\xff\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f\xa0\xa1\xa2"
	);

	/*
	 \x00 is an invalid opcode here
	*/
	static private $secondOpcodes = array(
			"\x2" => array(
					3 => "\x00\x00\x00\x00\xa3\xa4\xa5",
					4 => "\x00\x00\x00\x00\xaf\xb0\xb1",
					5 => "\x00\x00\x00\x00\xbb\xbc\xbd",
					6 => "\x00\x00\x00\x00\xc7\xc8\xc9",
					7 => "\x00\x00\x00\x00\xd3\xd4\xd5",
					8 => "\x00\x00\x00\x00\xdf\xe0\xe1",
					9 => "\x00\x00\x00\x00\xeb",
					10 => "\x00\x00\x00\x00\xef",
					11 => "\x00\x00\x00\x00\xf3"
			),
			"\x3" => array(
					3 => "\x00\x00\x00\x00\xa6\xa7\xa8",
					4 => "\x00\x00\x00\x00\xb2\xb3\xb4",
					5 => "\x00\x00\x00\x00\xbe\xbf\xc0",
					6 => "\x00\x00\x00\x00\xca\xcb\xcc",
					7 => "\x00\x00\x00\x00\xd6\xd7\xd8",
					8 => "\x00\x00\x00\x00\xe2\xe3\xe4",
					9 => "\x00\x00\x00\x00\xec",
					10 => "\x00\x00\x00\x00\xf0",
					11 => "\x00\x00\x00\x00\xf4"
			),
			"\x4" => array(
					3 => "\x00\x00\x00\x00\xa9\xaa\xab",
					4 => "\x00\x00\x00\x00\xb5\xb6\xb7",
					5 => "\x00\x00\x00\x00\xc1\xc2\xc3",
					6 => "\x00\x00\x00\x00\xcd\xce\xcf",
					7 => "\x00\x00\x00\x00\xd9\xda\xdb",
					8 => "\x00\x00\x00\x00\xe5\xe6\xe7",
					9 => "\x00\x00\x00\x00\xed",
					10 => "\x00\x00\x00\x00\xf1",
					11 => "\x00\x00\x00\x00\xf5"
			),
			"\x5" => array(
					3 => "\x00\x00\x00\x00\xac\xad\xae",
					4 => "\x00\x00\x00\x00\xb8\xb9\xba",
					5 => "\x00\x00\x00\x00\xc4\xc5\xc6",
					6 => "\x00\x00\x00\x00\xd0\xd1\xd2",
					7 => "\x00\x00\x00\x00\xdc\xdd\xde",
					8 => "\x00\x00\x00\x00\xe8\xe9\xea",
					9 => "\x00\x00\x00\x00\xee",
					10 => "\x00\x00\x00\x00\xf2",
					11 => "\x00\x00\x00\x00\xf6"
			),
			"\x14" => array(1 => "\x00\xf7"),
			"\x24" => array(1 => "\x00\xf8"),
			"\x34" => array(1 => "\x00\xf9"),
			"\x44" => array(1 => "\x00\xfa"),
			"\x54" => array(1 => "\x00\xfb"),
			"\x64" => array(1 => "\x00\xfc"),
			"\x74" => array(1 => "\x00\xfd"),
			"\x84" => array(1 => "\x00\xfe"),
			"\x94" => array(1 => "\x00\xff")
	);

	function __construct($sourceDict)
	{
		if(!($sourceDict instanceof SDCHDictionary))
			throw new \InvalidArgumentException('Encoder requires an SDCHDictionary object ');
		
		$this->sourceSize = $sourceDict->length();
		$this->dict = $sourceDict;

		$this->nearAddresses = array(0);
		$this->sameAddresses = array_fill(0, self::SAME_CACHE_SIZE << 8, 0);
		$this->nextSlot = 0;

		$this->targetSize = 0;

		$this->instructions = '';
		$this->lastOpcodeIndex = -1;
		$this->addresses = '';

		$this->dataSize = 0;
		$this->copyOffset = 0;
		$this->copySize = 0;

		$this->addData = '';

		//ob_start();
		//ob_implicit_flush(false);
	}

	static private function encodeInteger($i)
	{
		if ($i < 0)
			throw new \InvalidArgumentException('Negative value '.$i.' passed to '.__METHOD__.', which requires non-negative argument');

		$r = chr($i & 0x7f);
		while($i >>= 7)
			$r = chr(0x80 | ($i & 0x7f)).$r;
		return $r;
	}

	private function encodeAddress($address)
	{
		$sameCachePos = $address % (self::SAME_CACHE_SIZE << 8);
		if ($this->sameAddresses[$sameCachePos] === $address)
		{
			$this->addresses .= chr($sameCachePos & 0xff);
			$mode = self::VCD_FIRST_NEAR_MODE + self::NEAR_CACHE_SIZE + ($sameCachePos >> 8);
		}
		else
		{
			$encodedAddress = $this->sourceSize + $this->targetSize - $address;
			if ($encodedAddress < $address)
			{
				$mode = self::VCD_HERE_MODE;
			}
			else
			{
				$mode = self::VCD_SELF_MODE;
				$encodedAddress = $address;
			}

			foreach($this->nearAddresses as $i => $nearAddress)
			{
				$nearEncodedAddress = $address - $nearAddress;
				if ($nearEncodedAddress >= 0
						&& $nearEncodedAddress < $encodedAddress)
				{
					$mode = self::VCD_FIRST_NEAR_MODE + $i;
					$encodedAddress = $nearEncodedAddress;
				}
			}
			$this->addresses .= self::encodeInteger($encodedAddress);

			/* Update same cache */
			$this->sameAddresses[$sameCachePos] = $address;
		}
		/* Update near cache */
		$this->nearAddresses[$this->nextSlot++] = $address;
		$this->nextSlot %= self::NEAR_CACHE_SIZE;

		return $mode;
	}

	private function encodeInstruction($inst, $size)
	{
		$this->targetSize += $size;

		if ($size <= 18)		// Was 0xFF, but no opcodes defined with size > 18
		{
			if ($size <= 6 && $this->lastOpcodeIndex >= 0) // no compound opcodes defined with size > 6
			{
				$lastOpcode = $this->instructions[$this->lastOpcodeIndex];

				if (isset(self::$secondOpcodes[$lastOpcode][$inst][$size])
						&& self::$secondOpcodes[$lastOpcode][$inst][$size] !== "\0")
				{
					$this->instructions = substr_replace($this->instructions, self::$secondOpcodes[$lastOpcode][$inst][$size], $this->lastOpcodeIndex, 1);
					$this->lastOpcodeIndex = -1;
					return;
				}
				/* No compound opcodes defined at size 0 */
			}
			if (isset(self::$firstOpcodes[$inst][$size]) && self::$firstOpcodes[$inst][$size] !== "\xFF")
			{
				$this->lastOpcodeIndex = strlen($this->instructions);
				$this->instructions .= self::$firstOpcodes[$inst][$size];
				return;
			}
		}
		/* There always is a first opcode with size 0 */
		$this->lastOpcodeIndex = strlen($this->instructions);
		$this->instructions .= self::$firstOpcodes[$inst][0].self::encodeInteger($size);
	}

	function copy($offset, $size)
	{
		/* See if any new data has been put in the output buffer,
		 if so have to generate an ADD opcode
		before the COPY opcode
		*/
		$addSize = strlen($this->addData) - $this->dataSize;
		if ($addSize > 0)
		{
			if ($this->copySize > 0)
				$this->encodeInstruction(self::VCD_COPY + $this->encodeAddress($this->copyOffset), $this->copySize);

			$this->encodeInstruction(self::VCD_ADD, $addSize);
			$this->dataSize += $addSize;
		}
		else if ($offset === ($this->copyOffset + $this->copySize))
		{
			/* Combine 2 copies into a longer single copy */
			$this->copySize += $size;
			return;
		}
		else if ($this->copySize > 0)
			$this->encodeInstruction(self::VCD_COPY + $this->encodeAddress($this->copyOffset), $this->copySize);

		$this->copyOffset = $offset;
		$this->copySize = $size;
	}
	
	function add($data) {
		if(!is_string($data))
			throw new \InvalidArgumentException('add requires a string');
		
		/* Adds are handled in a lazy way by copy() */
		$this->addData .= $data;
	}

	function __toString()
	{
		/* Flush any outstanding instructions */
		$this->copy(0, 0);

		/* Grab the data section */
		$data = $this->addData;
		$this->addData = '';

		$data = self::encodeInteger($this->targetSize)
				."\0"
				.self::encodeInteger(strlen($data))
				.self::encodeInteger(strlen($this->instructions))
				.self::encodeInteger(strlen($this->addresses))
				.$data
				.$this->instructions
				.$this->addresses;

		return $this->dict->serverId()
		       ."\0"
		       ."\xd6\xc3\xc4\x00\x00".self::VCD_SOURCE
 		       .self::encodeInteger($this->sourceSize)
		       .self::encodeInteger(0)
		       .self::encodeInteger(strlen($data))
		       .$data;
	}
}

/* TODO: seperate these into a mutable 'factory' and imutable dictionary?
         it's starting to get quite messy handling mutability */
class SDCHDictionary {
	//Raw dictionary, suitable for saving to disk
	private $raw;
	//and id's
	private $client_id;
	private $server_id;
	private $hash;
	
	private $changed = True;
	
	//length of the dictionary data
	private $length = False;
	
	//headers
	private $domain = False;
	private $path = False;
	
	private $data = array();
	
	public function __construct() {
	}
	
	public function addData($data) {
		$arr = explode("\n", $data);
		
		//don't handle anything without a newline yet
		if(count($arr) < 2)
			return;
		
		$this->changed = True;
		
		//remove last element, it doesnt have a newline
		array_pop($arr);
		
		foreach($arr as $v) {
			//reattach newline
			//this is so contiguious areas can be reduced to single
			//copy opcodes
			$v .= "\n";
			
			//and place in the array, key is 8 chars of md5
			$this->data[substr(md5($v), 0, 8)] = array (
				'data' => $v,
				'pos'  => False,
				'len'  => strlen($v)
			);
		}
	}
	
	//create raw dictionary suitable for passing to the browser
	//as well as updating metadata
	private function generate() {
		if($this->changed == False) {
			//nothing needs to be done
			return;
		}
		
		$raw = &$this->raw;
		
		$raw  = "Format-Version: 1.0\n";
		$raw .= "Domain: {$this->domain}\n";
		if($this->path)
		$raw .= "Path: {$this->path}\n";
		
		$raw .= "\n";
		$pos = 0;
		foreach($this->data as &$v) {
			$raw .= $v['data'];
			$v['pos'] = $pos;
			$pos = $pos + $v['len'];
		}
		
		$this->length = $pos;
		$this->hash = base64_encode(hash('sha256', $this->raw, true));
		$this->client_id = substr($this->hash, 0, 8);
		$this->server_id = substr($this->hash, 8, 8);
		
		//dictionary created and metadata synced
		$this->changed = False;
	}
	
	public function compress($input) { $this->generate();
		$lines = explode("\n", $input);
		$append = array_pop($lines);
		
		$data = $this->data;
		
		$encoder = new SDCHVCDiffEncoder($this);
		foreach($lines as $v) {
			$v .= "\n";
			$id = substr(md5($v), 0, 8);
			
			//TODO: verify that the line and the dictionary match?
			//colisions unlikely but still possible, potential
			//injection exploit?
			//maybe just use the lines themselves as array keys
			if($data[$id]) {
				$a = $data[$id];
				$encoder->copy($a['pos'], $a['len']);
			}
			else
			{
				$encoder->add($v);
			}
		}
		//last line appended seperately, may not contain newline
		$encoder->add($append);
		
		//encoder should now contain the information
		//needed to generate a diff
		
		return $encoder->__toString();
	}
	
	public function __toString() { $this->generate();
		return $this->raw;
	}
	
	//getters
	public function serverId() { $this->generate();
		return $this->server_id;
	}
	public function clientId() { $this->generate();
		return $this->client_id;
	}
	public function length() { $this->generate();
		return $this->length;
	}
	
	//getseters
	public function domain($value = null) {
		return $this->_getset('domain', $value);
	}	
	public function path($value = null) {
		return $this->_getset('path', $value);
	}
	
	private function _getset($name, $value) {
		$ret = $this->{$name};
		if($value != null) {
			$this->{$name} = $value;
			$this->changed = True;
		}
		return $ret;
	}
	
	//Returns false if it does not match
	//otherwise returns a 'score', perfect match will be 1
	//lesser matches will be greater than zero
	public function match($domain=null, $path=null) {
		if(!is_string($domain))
			$domain = $_SERVER["HTTP_HOST"];
		if(!is_string($path))
			$path = $_SERVER["REQUEST_URI"];
		
		$score = 1;
		
		/* *
		 * Domain Comparison:
		 * http://www.ietf.org/rfc/rfc2965.txt specified here
		 * */		
		/* TODO: exactly how should this be handled?
		         googles spec doesnt match their browser 
		         wich i think matches the previous rfc instead */
		/* 
		if($domain[0] !== '.')
			$domain = '.' . $domain;
		
		$matchDomain = $this->domain;
		if($matchDomain[0] !== '.')
			$matchDomain = '.' . $matchDomain; 
		*/
		
		$len = strlen($this->domain);
		if(substr($domain, strlen($domain) - $len) !== $this->domain)
			//does not end with this dicts domain, not a match
			return False;
		
		//penalize non-matching domain
		//a match should be favored unless the path is VASTLY different
		//TODO: count domain components instead of characters?
		$score += (strlen($domain) - strlen($this->domain)) * 5;   
		
		/* *
		 * Path Comparison from spec:
		 * Must be either:
		 *   1. P2 is equal to P1
		 *   2. P2 is a prefix of P1 and either the final character in P2 is "/" or the
		 *      character following P2 in P1 is "/".
		 *
		 * */
		//2 is achived by appending any missing /, then comparing
		$len = strlen($this->path);
		$myPath = $this->path;
		if($path !== $myPath) {
			if($myPath[$len-1] !== '/') {
				$myPath .= '/';
				$len++;
				//penalize, so /foo/ scores better than /foo
				$score++;
			}
			
			if(strncmp($myPath, $path, $len) !== 0)
				return False;
		}
		
		$score += strlen($path) - strlen($myPath);
		
		//all these comments are here cause if this is wrong
		//chrome will throw a tantrum and decide not use sdch
		//TODO: rethink score
		
		return $score;
	}
}


//TODO: stop using $dicts as a local var for too many things
class SDCH {
	protected $dicts = array();

	protected function findDicts($domain=null, $path=null) {
		$res = array();

		//TODO: two equivilent dicts will clobber each other based on load order
		foreach($this->dicts as $v) {
			$match = $v->match($domain, $path);
			if($match !== False)
				$res[$match] = $v;
		}

		sort($res);
		return $res;
	}

	public function advertise($domain=null, $path=null) {
		$dicts = $this->findDicts($domain, $path);

		if(count($dicts) < 1) {
			//nothing to advertise
			return;
		}

		list($score, $dict) = each($dicts);
		$id = $dict->clientId();

		//TODO: Custom paths for dictionarys
		if(strpos($_SERVER['HTTP_AVAIL_DICTIONARY'], $id) === False)
			header("Get-Dictionary: /@dict/{$id}");

	}

	//TODO: do i want a seperate check?
	// 	public function canCompress() {
	// 		$ids = explode(',', $_SERVER['HTTP_AVAIL_DICTIONARY']);

	// 		foreach($ids as $v)
	// 			if(array_key_exists(trim($v), $this->dicts))
	// 			return True;

	// 		return False;
	// 	}

	public function compress($data) {
		$ids = explode(',', $_SERVER['HTTP_AVAIL_DICTIONARY']);
		if(!is_array($ids) || count($ids) < 1) return;

		$score = PHP_INT_MAX;
		$dict = False;

		foreach($ids as $v)
			if(array_key_exists($v, $this->dicts))
		{
			$vScore = $this->dicts[$v]->match();
			if($vScore !== False && $vScore < $score) {
				$score = $vScrore;
				$dict = $this->dicts[$v];
			}
		}

		if($dict !== False)
			return $dict->compress($data);
		
		return False;
	}

	public function get($id=False) {
		if(!is_string($id))
			return new SDCHDictionary();

		if(array_key_exists($id, $this->dicts))
			return clone $this->dicts[$id];

		return False;
	}
	
	public function getAll() {
		//clone the dict array
		//dicts are mutable and we can't let them be changed
		//lest they lose sync with their id's
		$ret = array();
		foreach($this->dicts as $k => $v)
			$ret[$k] = clone $v;
		
		return $ret;
	}

	public function addDictionary($dict) {
		if(!($dict instanceof SDCHDictionary)) {
			throw new \InvalidArgumentException(
					'SDCH::addDictionary requires a dictionary object'
			);
		}
		/* TODO: handle raw dicts? */
		$this->dicts[$dict->clientId()] = $dict;
	}
	
	public function removeDictionary($dict) {
		if($dict instanceof SDCHDictionary)
			unset($this->dicts[$dict->ClientId]);
		else
			unset($this->dicts[$dict]);
	}
}


//so the file can run inside tests
function main($argv) {
	function printHelp() {
		echo 'Usage: -d [domain] -a [data file] -c [compress file]', "\n",
		     '       -u [path] -p (print dict), -w [file, write dict]', "\n",
		     '       -v for var dump or -h for this message, THIS INFO IS INCORRECT'."\n";
		exit();
	}
	
	if(count($argv) < 2)
		printHelp();
	
	$argv = array_reverse($argv);
	//ignore first parameter, is filename
	array_pop($argv);
	$cmd = array_pop($argv);
	
	$dict = new SDCHDictionary();
	
	while($cmd) {
		switch($cmd) {
		case '-d':
		case '--domain':
			$cmd = array_pop($argv);
			$dict->domain($cmd);
			break;
		case '-p':
		case '--path':
			$cmd = array_pop($argv);
			$dict->path($cmd);
			break;
		case '-e':
		case '--print':
			echo $dict->__toString();
			break;
		case '-s':
		case '--save':
			$cmd = array_pop($argv);
			$raw = serialize($dict);
			file_put_contents($cmd, $raw);
			break;
		case '-l':
		case '--load':
			$cmd = array_pop($argv);
			$raw = file_get_contents($cmd);
			$dict = unserialize($raw);
			break;
		case '-r':
		case '--raw':
			$cmd = array_pop($argv);
			$raw = $dict->__toString();
			file_put_contents($cmd, $raw);
			break;
		case '--var_dump':
			//ensure harden() is called
			$dict->__toString();
			var_dump($dict);
			break;
		case '-a':
		case '--add':
			$cmd = array_pop($argv);
			$data = file_get_contents($cmd);
			$dict->addData($data);
			break;
		case '-c':
		case '--compress':
			$cmd = array_pop($argv);
			$data = file_get_contents($cmd);
			echo $dict->compress($data);
			break;
		case '-h':
		case '--help':
			printHelp();
		}
		$cmd = array_pop($argv);
	}
	$dict->__toString();
}

if (!empty($argc) && strstr($argv[0], basename(__FILE__))) {
	main($argv);
	exit(0);
}