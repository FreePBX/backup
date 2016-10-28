<?php
require(dirname(__FILE__) . '/main.php');

if (isset($templates)) {
	foreach ($templates as $t) {
		$li[] = sprintf(
			'<a href="config.php?display=backup_templates&amp;action=edit&amp;id=%s" class="list-group-item %s">%s</a>',
			htmlspecialchars($t['id']),
			($id == $t['id'] ? 'active' : ''),
			htmlspecialchars($t['name'])
		);
	}
}	

foreach ($li as $item) {
	echo $item;
}
