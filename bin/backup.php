#!/usr/bin/env php
<?php
$restrict_mods						= array('backup' => true);
$bootstrap_settings['cdrdb']		= true;
$bootstrap_settings['freepbx_auth']	= false;
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
	include_once('/etc/asterisk/freepbx.conf');
}

/**
 * OPTIONS
 * opts - if we have opts, run the backup from it, passing the file back when finisehed
 * id - if we have an id. If we do, just run a "regular" backup, using the id for options
 *    and pulling all other data from the database
 * astdb - tools for handeling the astdb
 */

$getopt = (function_exists('_getopt') ? '_' : '') . 'getopt';
$vars = $getopt($short = '', $long = array('opts::', 'id::', 'astdb::', 'data::'));

//if the id option was passed
if ($vars['id']) {
	//bu = backup settings
	//s= servers
	//b= backup object
	if ($bu = backup_get_backup($vars['id'])) {
		$s = backup_get_server('all_detailed');
		$b = new Backup($bu, $s);		
		backup_log(_('Intializing Backup') . ' ' .$vars['id']);
		$b->init();
				
		if ($b->b['bu_server'] == "0") {
			//get lock to prevent backups from being run cuncurently
			while (!$b->acquire_lock()) {
				backup_log(_('waiting for lock...'));
				sleep(10);
			}
			backup_log(_('Backup Lock acquired!'));
			
			backup_log(_('Running pre-backup hooks...'));
			$b->run_hooks('pre-backup');
			
			backup_log(_('Adding items...'));
			$b->add_items();
			
			backup_log(_('Bulding manifest...'));
			$b->build_manifest();
			$b->save_manifest('local');
			$b->save_manifest('db');
			
			backup_log(_('Creating backup...'));
			$b->create_backup_file();
		} else {
			$opts = array(
					'bu'	=> $bu,
					's'		=> $s,
					'b'		=> $b
			);
			$cmd[] = fpbx_which('ssh');
			$cmd[] = '-o StrictHostKeyChecking=no -i';
			$cmd[] = backup__($s[$b->b['bu_server']]['key']);
			$cmd[] = backup__($s[$b->b['bu_server']]['user']) 
					. '\@' 
					. backup__($s[$b->b['bu_server']]['host']);
			$cmd[] = '`php -r \'
				$bootstrap_settings["freepbx_auth"] = false;
				$bootstrap_settings["skip_astman"] = true;
				$restrict_mods = true;
				if (!@include_once(getenv("FREEPBX_CONF") ? getenv("FREEPBX_CONF") : "/etc/freepbx.conf")) {
					include_once("/etc/asterisk/freepbx.conf");
				}
				foreach($amp_conf as $key => $val) {
					if (is_bool($val)) {
						echo "export " . trim($key) . "=" . ($val?"TRUE":"FALSE") ."\n";
					} else {
						echo "export " . trim($key) . "=" . escapeshellcmd(trim($val)) ."\n";
					}
				}
				\'
				`';
			$cmd[] = '$AMPSBIN/backup.php --opts=' . base64_encode(serialize($opts)) . '\'';
			$cmd[] = '> ' . $b->b['_tmpfile'];
			exec(implode(' ', $cmd), $ret, $status);
			unset($cmd);
			$b->b['manifest'] = backup_get_manifest_tarball($b->b['_tmpfile']);
			$b->save_manifest('db');
		}	
		
			backup_log(_('Storing backup...'));
			$b->store_backup();
			
			backup_log(_('Running post-backup hooks...'));
			$b->run_hooks('post-backup');
			
			backup_log(_('Backup successfully completed!'));
			//TODO: restore to this server if requested
		} else {
		backup_log('backup id ' . $vars['id'] . ' not found!');
	}
	
//if the opts option was passed, used for remote backup (warm spare)
} elseif($vars['opts']) {
	//r = remote options
	if(!$r = base64_decode(unserialize($vars['opts']))) {
		echo 'invalid opts';
		exit(1);
	}
	$b = new Backup($r->bu, $r->s);
	$b->b['_ctime']		= $r->b->b['_ctime'];
	$b->b['_file']		= $r->b->b['_file'];
	$b->b['_dirname']	= $r->b->b['_dirname'];
	$b->init();
	$b->run_hooks('pre-backup');
	$b->add_items();
	$b->build_manifest();
	$b->save_manifest('local');
	$b->create_backup_file(true);
	exit();
} elseif($var['astdb']) {
	switch ($var['astdb']) {
		case 'dump':
			echo astdb_get(array('RG', 'BLKVM', 'FM', 'dundi'));
			break;
		case 'restore':
			if (is_file($data)) {
				$data = file_get_contents($data);
			}
			astdb_put(unserialize($data), array('RINGGROUP', 'BLKVM', 'FM', 'dundi'));
			break;
	}
} else {
	show_opts();
}

exit();

function show_opts() {
	$e[] = 'backup.php';
	$e[] = '';
	$e[] = 'options:';
	$e[] = "\t" . '--id=<id number> - a valid backup id';
	$e[] = "\t" . '--astdb=<restore|dump> - dump or restore the astdb';
	$e[] = "\t" . '--data=<data> a serilialized string of the astdb dumb to restore.';
	$e[] = "\t\t" . ' Can also point to a file contianing the serializes string';
	$e[] = '';
	$e[] = '';
	echo implode("\n", $e);
}
?>