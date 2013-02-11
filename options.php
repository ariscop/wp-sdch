<?php namespace wp\plugin\ariscop\sdch;

?>
<div class="wrap">
<h2>SDCH Encoder</h2>
<?php

if(!empty($_GET['action']))
switch ($_GET['action']) {
	case 'delete':
		$sdch->removeDictionary($_GET['id']);
		break;
	default:
}

if(!empty($_FILES['new_dict'])) {
	$file = $_FILES['new_dict'];
	if($file['error'] !== 0) {
		echo "There was a problem when uploading {$files['name']}</br>\n";
	} else {
		$content = file_get_contents($file['tmp_name']);
		try {
			$sdch->addDictionary(unserialize($content));
		} catch(Exception $e) {
			echo "An exception occoured while processing {$files['name']}:</br>\n",
			     $e->getMessage(),"</br>\n";
		}
	}	 
}



$sdch->save();

class _sdch_list extends \WP_List_Table {

	function get_columns() {
		return array(
			'id'   => 'Client ID',
			'domain' => 'Domain',
			'path'   => 'Path',
			'size'   => 'Size'
		);
	}

	function get_sortable_columns() {
		return array(
			'id' => array('id', false),
			'domain' => array('domain', false),
			'path' => array('path', false)
		);
	}

	function usort_reorder( $a, $b ) {
		$order   = (!empty($_GET['order'])  ) ? $_GET['order']   : 'asc';
		$orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'domain';
		
		$result = 0;
		
		switch ($orderby) {
			case 'domain':
				$result = strcmp($a->domain(), $b->domain());
				if($result === 0)
					$result = strcmp($a->path(), $b->path());
				break;
			case 'path':
				$result = strcmp($a->path(), $b->path());
				if($result === 0)
					$result = strcmp($a->domain(), $b->domain());
				break;
			case 'id':
				$result = strcmp($a->ClientId(), $b->ClientId());
		}

		return ( $order === 'asc' ) ? $result : -$result;
	}

	function column_id($item) {
		$action = $this->row_actions(array('delete' => 
			"<a href=\"?page={$_REQUEST['page']}&".
			"action=delete&id={$item->ClientId()}\">Delete</a>"
		), False);
		$ref = "<a href=\"/@dict/{$item->ClientID()}\"> {$item->ClientID()}</a>";
		
		return "$ref $action";
	}

	function column_domain($item) {
		return $item->domain();
	}
	
	function column_path($item) {
		return $item->path();
	}
	
	function column_size($item) {
 		return $item->length();
	}

	function column_default($item, $column_name) { return ''; }

	function prepare_items() {
		global $sdch;

		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);
		
		$this->items = $sdch->getAll();

		usort( $this->items, array( &$this, 'usort_reorder' ) );
	}
}


$table = new _sdch_list();
$table->prepare_items();
$table->display();

?>

<form enctype="multipart/form-data" action="/wp-admin/options-general.php?page=sdch/options.php" method="POST">
    <input type="hidden" name="MAX_FILE_SIZE" value="1000000" />
    Dictionary: <input name="new_dict" type="file" />
    <input type="submit" value="Upload new" />
</form>

</div>