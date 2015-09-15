#!/usr/bin/env php
<?php
$restrict_mods						= array('backup' => true, 'core' => true, 'sysadmin' => true);
$bootstrap_settings['cdrdb']		= true;
$bootstrap_settings['freepbx_auth']	= false;
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
	include_once('/etc/asterisk/freepbx.conf');
}

if (!function_exists('backup_log')) {
	// Our current backup module isn't installed, or is disabled
	// for some reason.  This isn't going to help us at all. So,
	// we're just going to force load it, and hang the consequences.
	include (__DIR__."/../functions.inc.php");
}

/**
 * OPTIONS
 * opts - if we have opts, run the backup from it, passing the file back when finisehed
 * id - if we have an id. If we do, just run a "regular" backup, using the id for options
 *    and pulling all other data from the database
 * astdb - tools for handeling the astdb
 */

$getopt = (function_exists('_getopt') ? '_' : '') . 'getopt';
$vars = $getopt($short = '', $long = array('restore::', 'items::', 'manifest::', 'skipnat::'));

// Let items be descriptive - it may NOT be an encoded array.
if (isset($vars['items'])) {
	// Is it an encoded array though?
	$items = unserialize(base64_decode($vars['items']));
	if (!is_array($items)) {
		// Ok, it's not. It may just be a comma delimited set of things
		// to restore.
		$items = array();
		$itemsarr = explode(',', $vars['items']);
		foreach ($itemsarr as $i) {
			switch ($i) {
			case 'astdb':
				$items['astdb'] = true;
				break;
			case 'mysql':
				$items['mysql'] = true;
				break;
			case 'cdr':
				$items['cdr'] = 'true'; // Yes, this is mean to be a string, not bool. See the restore below
				break;
			case 'files':
				$items['files'] = true;
				break;
			case 'all':
				$items['astdb'] = true;
				$items['mysql'] = true;
				$items['files'] = true;
				$items['cdr'] = 'true'; // String, not bool
				break;
			default:
				die("Unknown item option $i\n");
			}
		}
	}
}

// Are we going to want to skip nat-ish settings?
if (isset($vars['skipnat'])) {
	$skipnat = true;
	backup_log(_('Explicitly skipping host-specific NAT settings'));
} else {
	$skipnat = false;
}


// Have we been asked to show a manifest?
if(isset($vars['manifest'])) {
	print_r(backup_get_manifest_tarball($vars['manifest']));
	exit;
}

// If we're NOT being asked to restore, show help.
if (!isset($vars['restore'])) {
	show_opts();
	exit;
} else {
	// Actually do a restore
	if (!$items) {
		backup_log(_('Nothing to restore!'));
		exit();
	}

	backup_log(_('Initializing Restore...'));

	if (!file_exists($vars['restore'])) {
		backup_log(_('Backup file not found! Aborting.'));
		return false;
	}
	//TODO: should we use the manifest to ensure that all
	//files exists before trying to restore them?
	$manifest = backup_get_manifest_tarball($vars['restore']);

	//run hooks
	if (isset($manifest['hooks']['pre_restore']) && $manifest['hooks']['pre_restore']) {
		backup_log(_('Running pre-restore scripts...'));
		exec($manifest['hooks']['pre_restore']);
	}
	backup_log(_('Running pre-restore hooks, if any...'));
	mod_func_iterator('backup_pre_restore_hook', $manifest);

	// Are we restoring the web root? We need to detect that,
	// so that (via #8059) if we aren't, don't restore the mysql
	// database, as the existing one will still be correct.
	$restoringwebroot = false;

	if (isset($items['files']) && $items['files']) {
		if ($items['files'] === true) {
			backup_log(_('Restoring all files (this may take some time)...'));
			$filelist = recurse_dirs(".", $manifest['file_list']);
			$restoringwebroot = true;
		} else {
			backup_log(_('Restoring files (this may take some time)...'));
			$filelist = "";
			foreach ($items['files'] as $f) {
				// Make sure we extract as './filename' as this is
				// what they've historically been saved as. Sigh.
				if ($f[0] != '/') {
					$filelist .= "./$f\n";
				} else {
					$filelist .= ".$f\n";
				}
			}
		}

		$tmpfile = tempnam("/tmp", "restore");
		file_put_contents($tmpfile, $filelist);

		$cmd[] = fpbx_which('tar');
		$cmd[] = 'zxvf'; // We use 'v' to check for module root later
		$cmd[] = $vars['restore'];
		//switch to root so that files get put back where they belong
		//aslo, dont preseve access/modified times, as we may not always have the perms to do this
		//across the entire heirachy of a file we are restoring
		$cmd[] = '--atime-preserve -m -C /';
		$cmd[] = "--files-from=$tmpfile";
		// Never restore asterisk.conf. No matter what.
		$cmd[] = "--exclude='asterisk.conf'";
		// Same for cdr_mysql.conf
		$cmd[] = "--exclude='cdr_mysql.conf'";
		// And, just to be on the safe side, we never want to restore
		// freepbx.conf, either.
		$cmd[] = "--exclude='freepbx.conf'";
		exec(implode(' ', $cmd), $taroutput);
		backup_log(_('File restore complete!'));
		unlink($tmpfile);
		unset($cmd);

		$webroot = ".".$amp_conf['AMPWEBROOT']."/admin";

		// Does our filelist contain any file in the webroot?
		foreach ($taroutput as $file) {
			if (strpos($file, $webroot) === 0) {
				$restoringwebroot = true;
				break;
			}
		}
	}
	unset($manifest['file_list']);
	//dbug('$manifest', $manifest);

	//restore cdr's if requested
	if (isset($items['cdr']) && $items['cdr'] == 'true') {
		backup_log(_('Restoring CDRs...'));
		$s = explode('-', $manifest['fpbx_cdrdb']);
		$file = $manifest['mysql'][$s[1]]['file'];
		$cdr_stat_time = time();//last time we sent status update
		$notifed_for = array();//precentages we sent status updates for

		 //create cdrdb handler
		$dsn = array(
				'phptype'  => $amp_conf['CDRDBTYPE']
							? $amp_conf['CDRDBTYPE']
							: $amp_conf['AMPDBENGINE'],
				'hostspec' => $amp_conf['CDRDBHOST']
							? $amp_conf['CDRDBHOST']
							: $amp_conf['AMPDBHOST'],
				'username' => $amp_conf['CDRDBUSER']
							? $amp_conf['CDRDBUSER']
							: $amp_conf['AMPDBUSER'],
				'password' => $amp_conf['CDRDBPASS']
							? $amp_conf['CDRDBPASS']
							: $amp_conf['AMPDBPASS'],
				'port'     => $amp_conf['CDRDBPORT']
							? $amp_conf['CDRDBPORT']
							: '3306',
				'database' => $amp_conf['CDRDBNAME']
							? $amp_conf['CDRDBNAME']
							: 'asteriskcdrdb',
		);
		$cdrdb = DB::connect($dsn);
		$path = $amp_conf['ASTSPOOLDIR'] . '/tmp/' . time() . '.sql';

		//get db
		$cmd[] = fpbx_which('tar');
		$cmd[] = 'zxOf';
		$cmd[] = $vars['restore'];
		$cmd[] = './' . $file;
		$cmd[] = '>';
		$cmd[] = $path;

		exec(implode(' ', $cmd), $file);
		unset($cmd);

		backup_log(_('Getting CDR size...'));
		$cmd[] = fpbx_which('wc');
		$cmd[] = ' -l';
		$cmd[] = $path;

		exec(implode(' ', $cmd), $lines);
		unset($cmd);

		$lines = explode(' ', $lines[0]);
		$lines = $lines[0];

		$pretty_lines = number_format($lines);

		$file = fopen($path, 'r');
		$linecount = 0;
		$sql = "";
		$nextnotify = time() + 30;
		while(($line = fgets($file)) !== false) {
			$line = trim($line);
			$linecount++;

			$sql .= $line;

			if (substr($sql, -1) == ';') {
				$q = $cdrdb->query($sql);
				$sql = "";
			}
			// This just resets the timelimit of the script.
			set_time_limit(30);

			// Update the user every 30 seconds.
			if (time() > $nextnotify) {
				$percent = floor((1 - ($lines - $linecount) / $lines) * 100);
				$nextnotify = time() + 30;
				$log = sprintf(_("Processed %s%% of CDRs (%s of %s lines)"), $percent, number_format($linecount), $pretty_lines);
				backup_log($log);
			}
		}

		fclose($file);
		unlink($path);
		backup_log(_('Restoring CDRs complete'));
	}

	//restore Database
	if (isset($items['mysql']) || isset($items['settings']) && $items['settings'] == 'true') {
		if (!$restoringwebroot) {
			backup_log(_('WARNING!'));
			backup_log(_('Web Root restore not detected, not restoring module table'));
			backup_log(sprintf(_('You should run "%s" to ensure system integrity'),"fwconsole moduleadmin upgradeall"));
		}
		if ($manifest['fpbx_db'] != '') {
			$s = explode('-', $manifest['fpbx_db']);
			$file = $manifest['mysql'][$s[1]]['file'];
			$settings_stat_time = time();//last time we sent status update
			$notifed_for = array();//precentages we sent status updates for
			$path = $amp_conf['ASTSPOOLDIR'] . '/tmp/' . time() . '.sql';

			//get db
			$cmd[] = fpbx_which('tar');
			$cmd[] = 'zxOf';
			$cmd[] = $vars['restore'];
			$cmd[] = './' . $file;
			$cmd[] = '>';
			$cmd[] = $path;

			exec(implode(' ', $cmd), $file);
			unset($cmd);

			$cmd[] = fpbx_which('wc');
			$cmd[] = ' -l';
			$cmd[] = $path;

			exec(implode(' ', $cmd), $lines);
			unset($cmd);

			$lines = explode(' ', $lines[0]);
			$lines = $lines[0];

			$pretty_lines = number_format($lines);
			$file = fopen($path, 'r');

			if ($skipnat) {
				backup_log(_('Preserving local NAT settings'));
				// Back up the NAT settings, to restore later.
				$ss = FreePBX::create()->Sipsettings;
				$backup['localnets'] = $ss->getConfig('localnets');
				$backup['externip'] = $ss->getConfig('externip');
			}

			backup_log(_('Restoring Database...'));

			$linecount = 0;
			$sql = "";
			$nextnotify = time() + 30;
			while(($line = fgets($file)) !== false) {
				$line = trim($line);
				$linecount++;

				$sql .= $line;
				if (substr($line, -1) == ';') {
					// 1: Never restore module_xml
					if (preg_match('/`module_xml`/m', $sql)) {
						$sql = "";
					}
					// 2: If we're not restoring webroot, don't restore
					// the `module` table.
					if (!$restoringwebroot && preg_match('/`modules`/m', $sql)) {
						$sql = "";
					}
					// 3: Never restore the freepbxha table. You may think there's
					// a reason for it, but there's not, honest.
					if (preg_match('/`freepbxha`/m', $sql)) {
						$sql = "";
					}

					if ($sql) {
						$q = $db->query($sql);
						$sql = "";
					}
					// This just resets the timelimit of the script.
					set_time_limit(30);
				}

				// Update the user once every 30 seconds
				if (time() > $nextnotify) {
					$percent = floor((1 - ($lines - $linecount) / $lines) * 100);
					$nextnotify = time() + 30;
					$log = sprintf(_("Processed %s%% of Settings (%s of %s lines)"), $percent, number_format($linecount), $pretty_lines);
					backup_log($log);
				}
			}
			backup_log(_('Restored Database'));

			fclose($file);
			unlink($path);

			// Now, if we're selected skipnat, restore everything we backed up
			// before the import.
			if ($skipnat) {
				backup_log(_('Restoring NAT settings'));
				$ss = FreePBX::create()->Sipsettings;
				$ss->setConfig('localnets', $backup['localnets']);
				$ss->setConfig('externip',  $backup['externip']);
			}
		}
	}

	//  How about ASTDB?
	if (isset($items['astdb']) || isset($items['settings']) && $items['settings'] == 'true') {
		if ($manifest['astdb'] != '') {
			backup_log(_('Restoring astDB...'));
			$cmd[] = fpbx_which('tar');
			$cmd[] = 'zxOf';
			$cmd[] = $vars['restore'];
			$cmd[] = './' . $manifest['astdb'];
			exec(implode(' ', $cmd), $file);
			astdb_put(unserialize($file[0]), array('RINGGROUP', 'BLKVM', 'FM', 'dundi'));
			unset($cmd);
		}

		backup_log(_('Restoring Settings complete'));
	}
	//dbug($file);

	//run hooks
	if (isset($manifest['hooks']['post_restore']) && $manifest['hooks']['post_restore']) {
		backup_log(_('Running post restore script...'));
		exec($manifest['hooks']['post_restore']);
	}

	backup_log(_('Running post-restore hooks, if any...'));
	mod_func_iterator('backup_post_restore_hook', $manifest);

	//ensure that manager username and password are whatever we think they should be
	//the DB is authoritative, fetch whatever we have set there
	backup_log(_('Cleaning up...'));
	$freepbx_conf =& freepbx_conf::create();
	fpbx_ami_update($freepbx_conf->get_conf_setting('AMPMGRUSER', true),
					$freepbx_conf->get_conf_setting('AMPMGRPASS', true));

	// Update AstDB
	core_users2astdb();
	core_devices2astdb();

	needreload();

	//delete backup file if it was a temp file
	if (dirname($vars['restore']) == $amp_conf['ASTSPOOLDIR'] . '/tmp/') {
		unlink($vars['restore']);
	}

	/*
	 * cleanup stale backup files (older than one day)
	 * usually, backups will be deleted after a restore
	 * However, files that were downloaded from a remote server and
	 * the user aborted the restore should be cleaned up here
	 */
	$files = scandir($amp_conf['ASTSPOOLDIR'] . '/tmp/');
	foreach ($files as $file) {
		$f = explode('-', $file);
		if ($f[0] == 'backuptmp' && $f[2] < strtotime('yesterday')) {
			unlink($amp_conf['ASTSPOOLDIR'] . '/tmp/' . $file);
		}
	}

	backup_log(_('Restore complete!'));
	backup_log(_('Reloading...'));
	do_reload();
	// Trigger sysadmin to reload/regen any settings if available
	if (is_dir("/var/spool/asterisk/incron")) {
		$triggers = array('update-dns', 'config-postfix', 'update-ftp', 'fail2ban-generate', 'update-mdadm', 'update-ports', 'update-timezone', 'update-ups');
		foreach ($triggers as $f) {
			$filename = "/var/spool/asterisk/incron/sysadmin.$f";
			if (file_exists($filename)) {
				// Odd...
				@unlink($filename);
			}
			@fclose(@fopen($filename, "w"));
		}
	}

	backup_log(_('Done!'));
	exit();
}

exit();

function show_opts() {
	$e[] = 'restore.php';
	$e[] = '';
	$e[] = 'options:';
	$e[] = "\t--restore=/path/to/backup/file.tgz";
	$e[] = "\t\tSpecify the path to the backup file you wish to restore.";
	$e[] = "\t--items=...";
	$e[] = "\t\tThis is either a base64 encoded, serialized array, which is provided";
	$e[] = "\t\tby the web interface, or, a comma separated list of any of the following:";
	$e[] = "\t\t\tall\tRestore everything in the backup";
	$e[] = "\t\t\t\tThis is the same as enabling all the following options";
	$e[] = "\t\t\tmysql\tRestore the MySQL Settings Database";
	$e[] = "\t\t\tastdb\tRestore the AstDB";
	$e[] = "\t\t\tcdr  \tRestore the CDR Database";
	$e[] = "\t\t\tfiles\tRestore all files in the backup";
	$e[] = "\t--manifest=/path/to/file.tgz";
	$e[] = "\t\tDisplay the manifest file embedded in the backup .tgz.";
	$e[] = "\t--skipnat";
	$e[] = "\t\tThis explicitly skips any per-machine NAT settings (eg, externip)";
	$e[] = '';
	$e[] = '';
	echo implode("\n", $e);
}

function recurse_dirs($key, $var) {
	$dirwalk = "";
	foreach ($var as $k => $v) {
		if (is_numeric($k)) {
			// If it's a number, it's a list of files
			$dirwalk .= "$key/$v\n";
		} else {
			$dirwalk .= recurse_dirs("$key/$k", $v);
		}
	}
	return $dirwalk;
}
