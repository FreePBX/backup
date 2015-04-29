<?php
require(dirname(__FILE__) . '/main.php');

if (isset($servers)){
	foreach ($servers as $s) {
		$li[] = '<a ' 
				. ( $id == $s['id'] ? ' class="list-group-item current" ' : ' class="list-group-item"') 
				. '" href="config.php?display=backup_servers&action=edit&id=' 
				. $s['id'] . '">' 
				. $s['name'] 
				. ' (' . $s['type'] . ')'
				.'</a>';
	}

}	

 foreach ($li as $item) {
 	echo $item;
 }
