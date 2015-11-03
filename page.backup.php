<?php

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
$get_vars = array(
				'action'			=> '',
				'applyconfigs'		=> '',
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
				'disabletrunks'		=> '',
				'skipnat'		=> '',
				'display'			=> '',
				'exclude'			=> '',
				'host'				=> '',
				'id'				=> '',
				'items'				=> array(),
				'menu'				=> '',
				'name'				=> '',
				'email'				=> '',
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
} elseif($var['submit'] == _('Run now') && $var['action'] == 'save') {
	$var['action'] = 'run';
}

$var['brand'] = $amp_conf['DASHBOARD_FREEPBX_BRAND']?$amp_conf['DASHBOARD_FREEPBX_BRAND']:_('FreePBX');

//action actions
switch ($var['action']) {
	case 'ajax_save':
		//clear all buffers, we dont want to return any html
		while (ob_get_level()) {
			ob_end_clean();
		}
		$var['id'] = backup_put_backup($var);
		exit();//no need to do anything else, get out
	case 'save':
		$var['id'] = backup_put_backup($var);
		break;
	case 'run':
		//dont stop untill were all done
		//backup will compelte EVEN IS USER NAVIGATES AWAY FROM PAGE!!
		ignore_user_abort(true);

		//clear all buffers, those will interfere with the stream
		while (ob_get_level()) {
			ob_end_clean();
		}

		ob_start();
		header('Content-Type: text/event-stream');
		header('Cache-Control: no-cache');
		$cmd = $amp_conf['AMPBIN'] . '/backup.php --id='
				. escapeshellcmd($var['id']) . ' 2>&1';

		//start running backup
		$run = popen($cmd, 'r');
		while (($msg = fgets($run)) !== false) {
			//dbug('backup', $msg);
			//send results back to the user
			backup_log($msg);
		}

		pclose($run);

		//send messgae to browser that were done
		backup_log('END');

		exit();
		break;
}

//view action
switch ($var['action']) {
	case 'edit':
	case 'save':
		$var['servers'] = backup_get_server('all');
		$var['templates'] = backup_get_template('all_detailed');
		$var = array_merge($var, backup_get_backup($var['id']));
		$content = load_view(dirname(__FILE__) . '/views/backup/backup.php', $var);
		break;
	default:
		$content = load_view(dirname(__FILE__) . '/views/backup/backups.php', $var);
		break;
}
$heading = _("Backup and Restore");
?>

<div class="container-fluid">
	<h1><?php echo $heading?></h1>
	<div class = "display full-border">
		<div class="row">
			<div class="col-sm-12">
				<div class="fpbx-container">
					<div class="display <?php echo !empty($_REQUEST['action']) ? 'full' : 'no'?>-border">
						<?php echo $content ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
