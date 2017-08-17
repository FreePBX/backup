<?php
require(dirname(__FILE__) . '/main.php');
$allowed = array('ftp', 'local', 'ssh','awss3');

if (isset($servers)) {
	foreach ($servers as $s) {
		//only allow servers in $allowed
		if (!in_array($s['type'], $allowed)) { 
			continue;
		}

		$li[] = sprintf(
			'<a href="config.php?display=backup_restore&amp;id=%d" class="list-group-item %s">%s (%s)</a>',
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
