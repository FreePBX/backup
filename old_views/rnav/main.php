<?php
$list = array(
			'backup' 			=> _('Backups'),
			'backup_restore'	=> _('Restore'),
			'backup_servers'	=> _('Servers'),
			'backup_templates'	=>  _('Templates')
		);

$li = array();
		
foreach ($list as $k => $v) {
	// If current user does not have access to this sub-menu then don't display it
	//
	if (is_object($_SESSION["AMP_user"]) && !$_SESSION["AMP_user"]->checkSection($k)) {
		continue;
	}
	$li[] = sprintf(
		'<a href="config.php?display=%s" class="list-group-item %s">%s<a/>',
		$k,
		($display == $k ? 'active' : ''),
		htmlspecialchars($v)
	);

}
$li[] = '<hr />';
