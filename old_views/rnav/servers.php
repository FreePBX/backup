<?php
require(dirname(__FILE__) . '/main.php');

if (isset($servers)) {
	foreach ($servers as $s) {
		$li[] = sprintf(
			'<a href="config.php?display=backup_servers&amp;action=edit&amp;id=%d" class="list-group-item %s">%s (%s)</a>',
			$s['id'],
			($id == $s['id'] ? 'active' : ''),
			htmlspecialchars($s['name']),
			htmlspecialchars($s['type'])
		);
	}
}	

foreach ($li as $item) {
	echo $item;
}
