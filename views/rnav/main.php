<?php
$list = array(
			'backup' 			=> _('Backups'),
			'backup_restore'	=> _('Restore'),
			'backup_servers'	=> _('Servers'),
			'backup_templates'	=>  _('Templates')
		);
		
foreach ($list as $k => $v) {
	$li[] = '<a href="config.php?display=' . $k . '"'
			. ( $display == $k ? ' class="current" ' : '')
			. '>' 
			. $v . '</a>';
}
$li[] = '<hr />';

?>