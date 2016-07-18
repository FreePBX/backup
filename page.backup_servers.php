<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
$get_vars = array(
				'action'		=> '',
				'addr'			=> '',
				'dbname'		=> '',
				'desc'			=> '',
				'dir'			=> '',
				'display'		=> '',
				'host'			=> '',
				'id'			=> '',
				'key'			=> '',
				'maxsize'		=> '',
				'maxtype'		=> '',
				'menu'			=> '',
				'name'			=> '',
				'password'		=> '',
				'path'			=> '',
				'port'			=> '',
				'user'			=> '',
				'server_type'	=> '',
				'submit'		=> '',
				'transfer'		=> '',
				'bucket'		=> '',
				'awsaccesskey'		=> '',
				'awssecret'		=> '',
				'type'			=> '',
				'fstype' => ''
				);
isset($_REQUEST['bucket'])?$_REQUEST['name'] = $_REQUEST['bucket']:'';
foreach ($get_vars as $k => $v) {
	$var[$k] = isset($_REQUEST[$k]) ? $_REQUEST[$k] : $v;
}

//set action to delete if delete was pressed instead of submit
if ($var['submit'] == _('Delete') && $var['action'] == 'save') {
	$var['action'] = 'delete';
}

$var['servers'] = backup_get_server('all');

//server type
if ($var['id'] && !$var['server_type']) {
	$var['server_type'] = $var['servers'][$var['id']]['type'];
}

//action actions
switch ($var['action']) {
	case 'save':
		// Make sure people can't set it to be more than 25MB
		$maxsize = string2bytes($var['maxsize'], $var['maxtype']);
		if ($maxsize > 26214400) {
			$maxsize = 26214400;
		}
		$var['maxsize'] = $maxsize;
		unset($var['maxtype']);
		$var['id'] = backup_put_server($var);
		break;
	case 'delete':
		$var['id'] = backup_del_server($var['id']);
		break;
}

//view action
switch ($var['action']) {
	case 'edit':
	case 'save':
		if (!$var['id']) {
			$var['id'] = $var['server_type'];
		}
		$var = array_merge($var, backup_get_server($var['id']));
		$content = load_view(dirname(__FILE__) . '/views/servers/' . $var['type'] . '.php', $var);
		break;
	default:
		$content = load_view(dirname(__FILE__) . '/views/servers/servers.php', $var);
		break;
}


$heading = _("Servers");
?>

<div class="container-fluid">
	<h1><?php  echo $heading?></h1>
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
</br>
</br>
