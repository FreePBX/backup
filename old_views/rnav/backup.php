<?php
require(dirname(__FILE__) . '/main.php');

if (isset($backup)) {
	foreach ($backup as $b) {
		$li[] = sprintf(
			'<a href="config.php?display=backup&amp;action=edit&amp;id=%d" class="list-group-item %s">%s</a>',
			$b['id'],
			($id == $b['id'] ? 'active' : ''),
			htmlspecialchars($b['name'])
		);
	}
}

foreach ($li as $item) {
	echo $item;
}
