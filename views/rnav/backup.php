<?php
require(dirname(__FILE__) . '/main.php');
if (isset($backup)){
	foreach ($backup as $b) {
		$li[] = '<a '
			. ( $id == $b['id'] ? ' class="list-group-item current" ' : ' class="list-group-item"')
			. '" href="config.php?display=backup&action=edit&id='
			. $b['id'] . '">'
			. $b['name']
			.'</a>';
	}
}
 foreach ($li as $item) {
 	echo $item;
 }
