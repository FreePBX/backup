<?php 
dbug($_REQUEST);
$get_vars = array(
				'action'			=> '',
				'bu_server'			=> '',
				'cron_dom'			=> array(),
				'cron_dow'			=> array(),
				'cron_hour'			=> array(),
				'cron_minute'		=> array(),
				'cron_month'		=> array(),
				'cron_random'		=> '',
				'cron_schedule'		=> '',
				'desc'				=> '',
				'delete_amount'		=> '',
				'delete_time_type'	=> '',
				'delete_time'		=> '',
				'display'			=> '',
				'exclude'			=> '',
				'host'				=> '',
				'id'				=> '',
				'items'				=> array(),
				'menu'				=> '',
				'name'				=> '',
				'path'				=> '',
				'postbu_hook'		=> '',
				'postre_hook'		=> '',
				'prebu_hook'		=> '',
				'prere_hook'		=> '',
				'restore'			=> '',
				'storage_servers'	=> array(),
				'submit'			=> '',
				'type'				=> ''	
				);

foreach ($get_vars as $k => $v) {
	$var[$k] = isset($_REQUEST[$k]) ? $_REQUEST[$k] : $v;
}

//set action to delete if delete was pressed instead of submit
if ($var['submit'] == _('Delete') && $var['action'] == 'save') {
	$var['action'] = 'delete';
}

//action actions
switch ($var['action']) {
	case 'save':
		$var['id'] = backup_put_backup($var);
		break;
	case 'delete':
		$var['id'] = backup_del_backup($var['id']);
		break;
}

//rnav
//this needs to be he so that we can display rnav's reflecting any actions in the 'action actions' switch statement
$var['backup'] = backup_get_backup('all');
echo load_view(dirname(__FILE__) . '/views/rnav/backup.php', $var);

//view action
switch ($var['action']) {
	case 'edit':
	case 'save':
		$var['servers'] = backup_get_server('all');
		$var['templates'] = backup_get_template('all_detailed');
		$var = array_merge($var, backup_get_backup($var['id']));
		echo load_view(dirname(__FILE__) . '/views/backup/backup.php', $var);
		break;
	default:
		echo load_view(dirname(__FILE__) . '/views/backup/backups.php', $var);
		break;
}

?>