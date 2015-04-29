<?php

require(dirname(__FILE__) . '/main.php');

if (isset($templates)){
	foreach ($templates as $t) {
		$li[] = '<a ' 
			. ( $id == $t['id'] ? ' class="list-group-item current" ' : ' class="list-group-item"') 
			. '" href="config.php?display=backup_templates&action=edit&id=' 
			. $t['id'] . '">' 
			. $t['name'] 
			.'</a>';
	}
}	

 foreach ($li as $item) {
 	echo $item;
 }
