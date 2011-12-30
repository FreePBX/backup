<?php
/*
 * returns a json object of a directory in a jstree compatiable way
 * 
 */
function backup_jstree_list_dir($id, $path = '') {

	//sanitize path
	$path = trim($path, '/');
	$path = str_replace(array('..', ':'), '', trim($path, '/'));
	$path = escapeshellcmd($path);
	
	$ret = array();

	$s = backup_get_server($id);
	if(!$s) {
		$ret[] = array('data' => _('error/not found!')); 
	}

	switch ($s['type']) {
		case 'local':
			$s['path'] = backup__($s['path']);
			$dir = scandir($s['path'] . '/' . $path);
			foreach ($dir as $file) {
				
				//keep out the dots!
				if (in_array($file, array('.', '..'))) {
					continue;
				}
				
				//if this file is a directory, set it to be expandable
				if (is_dir($s['path'] . '/' . $path . '/' . $file)) {
					$ret[] = array(
								'attr'	=> array('data-path' => $path . '/' . $file),
								'data'	=> $file,
								'state'	=> 'closed'
								);
				} else {
					if (substr($file, -7) == '.tar.gz' || substr($file, -4) == '.tgz') {
						$ret[] = array(
									'attr' => array(
												'data-manifest'	=> 
													json_encode(backup_get_manifest_db($file)),
												'data-path'		=> $path . '/' . $file,
												
												),
									'data' => array(
												'title' => $file,
												'icon'	=> 'noicon'
									)
									); 
					}
				}
			}
			break;
		case 'ftp':
			//subsitute variables if nesesary
			$s['host'] = backup__($s['host']);
			$s['port'] = backup__($s['port']);
			$s['user'] = backup__($s['user']);
			$s['password'] = backup__($s['password']);
			$s['path'] = backup__($s['path']);
			$path = trim($path, '/') . '/';
			$ftp = ftp_connect($s['host'], $s['port']);
			if (ftp_login($ftp, $s['user'], $s['password'])) {
				//use pasive mode
				//ftp_pasv($ftp, true);
				ftp_chdir($ftp, $s['path'] . '/' . $path);
				$ls = ftp_nlist($ftp,  '');
				$dir = ftp_rawlist($ftp, '-d1 */');
				foreach ($ls as $file) {
					if (in_array($file . '/', $dir)) {
						$ret[] = array(
									'attr'	=> array('data-path' => $path . '/' . $file),
									'data'	=> $file,
									'state'	=> 'closed'
									);
					} else {
						if (substr($file, -7) == '.tar.gz' || substr($file, -4) == '.tgz') {
							$ret[] = array(
										'attr' => array(
													'data-manifest' => json_encode(backup_get_manifest_db($file)),
													'data-path' => $path . '/' . $file
													),
										'data' => $file
										);
						}
					}
				}
				//dbug('ftp ls', $ls);
				//dbug('ftp dir ' . $s['path'] . '/' . $path, $dir);
				//release handel
				ftp_close($ftp);
			} else {
				$ret[] = array('data' => _('FTP Connection error!')); 
				dbug('ftp connect error');
			}
			break;
		case 'ssh':
			$s['path'] = backup__($s['path']);
			$s['user'] = backup__($s['user']);
			$s['host'] = backup__($s['host']);
			$cmd[] = 'ssh';//TODO: path shouldnt be hardocded
			$cmd[] = '-o StrictHostKeyChecking=no -i';
			$cmd[] = $s['key'];
			$cmd[] = $s['user'] . '\@' . $s['host'];
			$cmd[] = '"cd ' . $s['path'] . '/' . $path . ';';
			$cmd[] = 'find * -maxdepth 0 -type f -exec echo f:"{}" \;;';
			$cmd[] = 'find * -maxdepth 0 -type d -exec echo d:"{}" \;"';
			exec(implode(' ', $cmd), $ls);
			//dbug(implode(' ', $cmd), $ls);
			unset($cmd);
			foreach ($ls as $file) {
				$f = explode(':', $file);
				if ($f[0] == 'd') {
					$ret[] = array(
								'attr'	=> array('data-path' => $path . '/' . $f[1]),
								'data'	=> $f[1],
								'state'	=> 'closed'
								);
				} elseif ($f[0] == 'f') {
					if (substr($f[1], -7) == '.tar.gz' || substr($f[1], -4) == '.tgz') {
						$ret[] = array(
									'attr' => array(
												'data-manifest' => json_encode(backup_get_manifest_db($f[1])),
												'data-path' => $path . '/' . $f[1]
												),
									'data' => $f[1]
									); 
					}
				}
			}
			break;
	}

	return $ret;
}

/*
 * gets a manifest from the db
 * Please note: cahced manifest in the db have limitied information
 * for a complete and fresh manifest, please extract it from the tarball
 */
function backup_get_manifest_db($bu) {
	global $db;
	if (substr($bu, -4) == '.tgz') {
		$bu = substr($bu, 0, -4);
	}
	
	$sql = 'SELECT manifest from backup_cache WHERE id = ?';
	$ret = $db->getOne($sql, $bu);
	if ($db->IsError($ret)){
		die_freepbx($ret->getDebugInfo());
	}
	
	if ($ret) {
		return unserialize($ret);
	} else {
		return _('manifest not found');
	}
	
}

/*
 * extracts a manifest from a tarball
 */
function backup_get_manifest_tarball($bu) {
	$cmd[] = fpbx_which('tar');
	$cmd[] = 'zxOf ' . $bu;
	$cmd[] = './manifest';
	$cmd[] = '2> /dev/null';
	exec(implode(' ', $cmd), $manifest, $ret);
	if ($ret === 0) {
		return unserialize($manifest[0]);
	} else {
		return false;
	}
}

/*
 * returns a html unorder list of files in a jstree format
 */
function backup_jstree_ul_backup_files($files, $path = '') {
	$ret = '';
	$ret .= '<ul>';
	foreach ($files as $dir => $f) {
		if (is_array($f)) {
			$ret .= '<li' 
					. ' data-path="' . $path . '/' . $dir . '"'
					//. ' data-name="' . $dir . '"'
					//. ' data-node-type="branch"'
					.' >';
			$ret .= '<a href="#">' . $dir . '</a>';
			$ret .= $dir;
			$ret .= backup_jstree_list_backup_files($f, $path . '/' . $dir);
		} else {
			$ret .= '<li' 
					. ' data-path="' . $path . '/' . $f . '"'
					//. ' data-name="' . $f . '"'
					//. ' data-node-type="leaf"'
					.' >';
			$ret .= '<a href="#">' . $f . '</a>';
		}
		$ret .= '</li>'."\n";
	}
	$ret .= '</ul>';
	//dbug('ret', $ret);
	return $ret;
}

/*
 * returns a json object of files in a jstree format
 */
function backup_jstree_json_backup_files($files, $path = '') {
	$ret = array();
	foreach ($files as $dir => $f) {
		if (is_array($f)) {
			$ret[] = array(
						'attr'	=> array('data-path' => $path . '/' . $dir),
						'data'	=> $dir,
						'state'	=> 'closed',
						'children' => array(backup_jstree_json_backup_files($f, $path . '/' . $dir))
						);
		} else {
			$ret[] = array(
						'attr'	=> array('data-path' => $path . '/' . $f),
						'data'	=> array('title' => $f, 'icon' => 'noicon'),
						//'state'	=> 'closed'
						);
 		}

	}

	return $ret;
}

/**
 * make sure backup file is local, download it and make it local if necessary
 */
function backup_restore_locate_file($id, $path) {
	global $amp_conf;
	$path = trim($path, '/');
	$path = str_replace(array('..', ':'), '', trim($path, '/'));
	$path = escapeshellcmd($path);
	
	$s = backup_get_server($id);
	if (!$s) {
		return array('error_msg' => _('Backup Server not found!'));
	}
	
	//dest is where we gona put backup files pulled infrom other servers
	$dest = $amp_conf['ASTSPOOLDIR'] 
			. '/tmp/' 
			. 'backuptmp-s' . $id .'-'
			. time() . '-'
			. basename($path);

	switch ($s['type']) {
		case 'local':
			$s['path'] = backup__($s['path']);
			$path = $s['path'] . '/' . $path;
			break;
		case 'ftp':
			//subsitute variables if nesesary
			$s['host'] = backup__($s['host']);
			$s['port'] = backup__($s['port']);
			$s['user'] = backup__($s['user']);
			$s['password'] = backup__($s['password']);
			$s['path'] = backup__($s['path']);
			$ftp = ftp_connect($s['host'], $s['port']);
			if (ftp_login($ftp, $s['user'], $s['password'])) {
				//use pasive mode
				ftp_pasv($ftp, true);
				if (ftp_get($ftp, $dest, $s['path'] . '/' . $path, FTP_BINARY)) {
					$path = $dest;
				} else {
					return array('error_msg' => _('Failed to retrieve file from server!'));
				}
				ftp_close($ftp);
			} else {
				dbug('ftp connect error');
			}
			break;
		case 'ssh':
			$s['path'] = backup__($s['path']);
			$s['user'] = backup__($s['user']);
			$s['host'] = backup__($s['host']);
			$cmd[] = fpbx_which('scp');
			$cmd[] = '-o StrictHostKeyChecking=no -i';
			$cmd[] = $s['key'];
			$cmd[] = $s['user'] . '\@' . $s['host'] 
					. ':' . $s['path'] . '/' . $path;
			$cmd[] = $dest;

			exec(implode(' ', $cmd), $foo, $ret);
			unset($cmd);
			if ($ret === 0) {
				$path = $dest;
			} 	else {
					return array('error_msg' => _('Failed to retrieve file from server!'));
				}
			break;
	}

	if (is_file($path)) {
		return $path;
	} else {
		return array('error_msg' => _('File not found! ' . $path));
	}
}

/*
 * update legacy backups so that there compatable with the new way of doing things
 */
function backup_migrate_legacy($bu) {
	global $amp_conf;

	$name = pathinfo($bu, PATHINFO_BASENAME);
	if (!substr($name, -7) == '.tar.gz' ) {
		return false;
	}
	
	$legacy_name = substr($name, 0, -7);
	
	$dir = $amp_conf['ASTSPOOLDIR'] . '/tmp/' . $legacy_name;
	mkdir($dir, 0755, true);

	$cmd[] = fpbx_which('tar');
	$cmd[] = '-zxf';
	$cmd[] = $bu;
	$cmd[] = ' -C ' . $dir;
	exec(implode(' ', $cmd));
	unset($cmd);

	$dir2 = $dir . '/tmp/ampbackups.' . $legacy_name;

	foreach (scandir($dir2) as $file) {
		if (substr($file, -7) == '.tar.gz') {
			$cmd[] = fpbx_which('tar');
			$cmd[] = '-zxf';
			$cmd[] = $dir2 . '/' . $file;
			$cmd[] = ' -C ' . $dir2;
			exec(implode(' ', $cmd));
			unset($cmd);
			
			unlink($dir2 . '/' . $file);
		}
		
	}

	//add files to manifest
	$ret['file_list']	= scandirr($dir2);
	$ret['file_count']	= count($ret['file_list'], COUNT_RECURSIVE);
	$ret['fpbx_db']		= '';
	$ret['fpbx_cdrdb']	= '';
	
	//format db's + add to manifest
	if (is_file($dir2 . '/astdb.dump')) {
		
		//rename file
		rename($dir2 . '/astdb.dump', $dir2 . '/astdb');
		
		//remove it from the file_list
		unset($ret['file_list'][array_search('astdb.dump', $ret['file_list'])]);

		//set the manifest
		$ret['astdb'] = 'astdb';
	} elseif(is_file($dir2 . '/tmp/ampbackups.' . $legacy_name . '/astdb.dump')) {
		rename($dir2 . '/tmp/ampbackups.' . $legacy_name . '/astdb.dump', $dir2 . '/astdb');
		$ret['astdb'] = 'astdb';
	}
	
	//serialize the astdb
	if (!empty($ret['astdb'])) {
		$astdb = array();
		foreach(file($dir2 . '/astdb') as $line) {
			$line = explode('] [', trim($line, '[]/'));

			//chuck the bad values
			if ($line[1] == '<bad value>') {
				continue;
			}
			
			//expldoe the key
			list($family, $key) = explode('/', $line[0], 2);
			
			//add to astdb array
			$astdb[$family][$key] = trim(trim($line[1]), ']');
		}
		
		
		file_put_contents($dir2 . '/astdb', serialize($astdb));
	}
	
	//migrate mysql files to a format that we can restore from
	if (is_file($dir2 . '/asterisk.sql')) {
		rename($dir2 . '/asterisk.sql', $dir2 . '/mysql-db.sql');
		unset($ret['file_list'][array_search('asterisk.sql', $ret['file_list'])]);//remove from manifest
		$ret['fpbx_db'] = 'mysql-db';
		$ret['mysql']['db'] = array('file' => 'mysql-db.sql');
		foreach($file = file($dir2 . '/mysql-db.sql') as $num => $line) {
			if (in_array(substr($line, 0, 2), array('--', '/*'))) {
				unset($file[$num]);
			}
		}

		file_put_contents($dir2 . '/mysql-db.sql', $file);
	}
	
	if (is_file($dir2 . '/asteriskcdr.sql')) {
		rename($dir2 . '/asteriskcdr.sql', $dir2 . '/mysql-cdr.sql');
		unset($ret['file_list'][array_search('asteriskcdr.sql', $ret['file_list'])]);//remove from manifest
		$ret['fpbx_cdrdb'] = 'mysql-cdr';
		$ret['mysql']['cdr'] = array('file' => 'mysql-cdr.sql');
		foreach($file = file($dir2 . '/mysql-cdr.sql') as $num => $line) {
			if (in_array(substr($line, 0, 2), array('--', '/*'))) {
				unset($file[$num]);
			}
		}
		file_put_contents($dir2 . '/mysql-cdr.sql', $file);
	}
	
	$ret['mysql_count']	= $ret['mysql'] ? count($ret['mysql']) : 0;
	$ret['astdb_count']	= $ret['astdb'] ? count($ret['astdb']) : 0;
	
	//delete legacy's tmp dir
	system('rm -rf ' . $dir2 . '/tmp');
	unset($ret['file_list']['tmp']);
	
	//store manifest
	file_put_contents($dir2 . '/manifest', serialize($ret));
	
	
	//build new tarball
	$dest = $amp_conf['ASTSPOOLDIR'] 
			. '/tmp/' 
			. 'backuptmp-slegacy-'
			. time() . '-'
			. $legacy_name
			. '.tgz';
	$cmd[] = fpbx_which('tar');
	$cmd[] = '-zcf';
	$cmd[] = $dest;
	$cmd[] = '-C ' . $dir2;
	$cmd[] = '.';
	exec(implode(' ', $cmd));
	unset($cmd);

	//remove tmp working dir
	system('rm -rf '.$dir);

	return $dest;
}


/*
 * do the actualy restore
 */
function backup_restore($bu, $items) {
	global $amp_conf, $db;
	if (!is_file($bu)) {
		return false;
	}
	//TODO: should we use the manifest to ensure that all 
	//files exists before trying to restore them?
	$manifest = backup_get_manifest_tarball($bu);
	
	//run hooks
	if (isset($manifest['hooks']['pre_restore']) && $manifest['hooks']['pre_restore']) {
		exec($manifest['hooks']['pre_restore']);
	}
	mod_func_iterator('backup_pre_restore_hook', $manifest);
	
	if (isset($items['files']) && $items['files']) {
		$cmd[] = fpbx_which('tar');
		$cmd[] = 'zxf';
		$cmd[] = $bu;
		//switch to root so that files get put back where they belong
		//aslo, dont preseve access/modified times, as we may not always have the perms to do this
		//across the entire heirachy of a file we are restoring
		$cmd[] = '--atime-preserve -m -C /';
		foreach ($items['files'] as $f) {
			$cmd[] = '.' . $f;
		}
		exec(implode(' ', $cmd));
		dbug('restoring backup!', implode(' ', $cmd));
		unset($cmd);
	}
	unset($manifest['file_list']);
	//dbug('$manifest', $manifest);
	if (isset($items['settings']) && $items['settings'] == 'true') {
		$s = explode('-', $manifest['fpbx_db']);
		$file = $manifest['mysql'][$s[1]]['file'];
		
		//get db
		$cmd[] = fpbx_which('tar');
		$cmd[] = 'zxOf';
		$cmd[] = $bu;
		$cmd[] = './' . $file;
		exec(implode(' ', $cmd), $file);
		unset($cmd);
		
		//TODO: should restore-to server should be configurable from the gui at restore time?
		$path = $amp_conf['ASTSPOOLDIR'] . '/tmp/' . time() . '.sql';
		$buffer = array();
		foreach($file as $line) {
			
			switch (true) {
				case ($line == ''):
				//clear the buffer every time we hit a blank line
					$flush = true;
					break;
				case (substr($line, -1) == ';'):
					//clear the buffer if the end of this line is the end of a statement
					$buffer[] = $line;
					$flush = true;
					break;
				default:
					$buffer[] = $line;
					break;
			}

			
			//if $flush is true, we need to flush the buffer
			if ($flush) {
				//dont spill the buffer if its emtpy
				if ($buffer) {
					$q = $db->query(implode("\n", $buffer));
					/*if ($db->IsError($q)){
						dbug($q->getDebugInfo());
					}*/
					db_e($q);
					//dbug($db->last_query);
					$buffer = array();
				}
				$flush = false;
			}
		}
		unset($file);
		
		//restore astdb
		$cmd[] = fpbx_which('tar');
		$cmd[] = 'zxOf';
		$cmd[] = $bu;
		$cmd[] = './' . $manifest['astdb'];
		exec(implode(' ', $cmd), $file);
		astdb_put(unserialize($file[0]), array('RINGGROUP', 'BLKVM', 'FM', 'dundi'));
		unset($cmd);
		//dbug($file);
		
		//run hooks
		if (isset($manifest['hooks']['post_restore']) && $manifest['hooks']['post_restore']) {
			exec($manifest['hooks']['post_restore']);
		}
		mod_func_iterator('backup_post_restore_hook', $manifest);
		needreload();
		
		//delete backup file if it was a temp file
		if (dirname($bu) == $amp_conf['ASTSPOOLDIR'] . '/tmp/') {
			unlink($bu);
		}
		
		/* 
		 * cleanup stale backup files (older than one day)
		 * usually, backups will be deleted after a restore
		 * these files are those that were downloaded from a remote server
		 * but the user aborted the restore
		 */
		$files = scandir($amp_conf['ASTSPOOLDIR'] . '/tmp/');
		foreach ($files as $file) {
			$f = explode('-', $file);
			if ($f[0] == 'backuptmp' && $f[2] < strtotime('yesterday')) {
				unlink($amp_conf['ASTSPOOLDIR'] . '/tmp/' . $file);
			}
		}
	}
	
}
?>
