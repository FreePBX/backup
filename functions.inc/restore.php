<?php
require __DIR__ . '/../vendor/autoload.php';
use Touki\FTP\FTP;
use Touki\FTP\FTPWrapper;
use Touki\FTP\Connection\Connection;
use Touki\FTP\PermissionsFactory;
use Touki\FTP\FilesystemFactory;
use Touki\FTP\WindowsFilesystemFactory;
use Touki\FTP\DownloaderVoter;
use Touki\FTP\UploaderVoter;
use Touki\FTP\CreatorVoter;
use Touki\FTP\DeleterVoter;
use Touki\FTP\Manager\FTPFilesystemManager;
use Touki\FTP\Model\File;
use Touki\FTP\Model\Directory;
use Touki\FTP\Exception\DirectoryException;
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
			if (!is_dir($s['path']."/$path")) {
				return array();
			}
			$dir = scandir($s['path']."/$path");
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
			$fstype = isset($s['fstype'])?$s['fstype']:'auto';
			$connection = new Connection($s['host'], $s['user'], $s['password'], $s['port'], 90, ($s['transfer'] == 'passive'));
			try{
				$connection->open();
			}catch (\Exception $e){
				backup_log($e->getMessage());
				echo $e->getMessage();
				return;
			}
			$wrapper = new FTPWrapper($connection);
			$permFactory = new PermissionsFactory;
			switch ($fstype) {
				case 'auto':
					$ftptype = $wrapper->systype();
					if(strtolower($ftptype) == "unix"){
						$fsFactory = new FilesystemFactory($permFactory);
					}else{
						$fsFactory = new WindowsFilesystemFactory;
					}
				break;
				case 'unix':
					$fsFactory = new FilesystemFactory($permFactory);
				break;
				case 'windows':
					$fsFactory = new WindowsFilesystemFactory;
				break;
			}
			$manager = new FTPFilesystemManager($wrapper, $fsFactory);
			$dlVoter = new DownloaderVoter;
			$ulVoter = new UploaderVoter;
			$ulVoter->addDefaultFTPUploaders($wrapper);
			$crVoter = new CreatorVoter;
			$crVoter->addDefaultFTPCreators($wrapper, $manager);
			$deVoter = new DeleterVoter;
			$deVoter->addDefaultFTPDeleters($wrapper, $manager);
			$ftp = new FTP($manager, $dlVoter, $ulVoter, $crVoter, $deVoter);
			if(!$ftp){
				$this->b['error'] = _("Error creating the FTP object");
			}
			$ftpdirs = $ftp->findFilesystems(new Directory($s['path']));
			$ls = array();
			foreach($ftpdirs as $thisdir){
				$files = $ftp->findFilesystems(new Directory($thisdir->getRealPath()));
				foreach ($files as $f) {
					$ls[] = $f->getRealpath();
				}

			}
				foreach ($ls as $file) {
					$filename = basename($file);
					//determine if we are a directory or not, rather than using rawlist
						if (substr($file, -7) == '.tar.gz' || substr($filename, -4) == '.tgz') {
							$ret[] = array(
										'attr' => array(
													'data-manifest' => json_encode(backup_get_manifest_db($filename)),
													'data-path' => $file
													),
										'data' =>  $file,
										);
						}
					}
				//dbug('ftp ls', $ls);
				//dbug('ftp dir ' . $s['path'] . '/' . $path, $dir);
				//release handel
			break;
		case 'ssh':
			$s['path'] = backup__($s['path']);
			$s['user'] = backup__($s['user']);
			$s['host'] = backup__($s['host']);
			$cmd[] = 'ssh';//TODO: path shouldnt be hardocded
			$cmd[] = '-o StrictHostKeyChecking=no -i';
			$cmd[] = $s['key'];
			$cmd[] = $s['user'] . '\@' . $s['host'];
			$cmd[] = '-p ' . $s['port'];
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
		case 'awss3':
			$s['bucket'] = backup__($s['bucket']);
			$s['awsaccesskey'] = backup__($s['awsaccesskey']);
			$s['awssecret'] = backup__($s['awssecret']);
			$awss3 = new S3($s['awsaccesskey'], $s['awssecret']);
			$contents = $awss3->getBucket($s['bucket']);
			foreach ($contents as $f){

				if (substr($f['name'], -7) == '.tar.gz' || substr($f['name'], -4) == '.tgz') {
					$ret[] = array(
							'attr' => array(
									'data-manifest' => json_encode(backup_get_manifest_db($f['name'])),
									'data-path' => $path . '/' . $f['name']
									),
							'data' => $f['name']
									);
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
	$ret = $db->getOne($sql, array($bu));
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
	$cmd[] = "zxOf ".escapeshellarg($bu);
	$cmd[] = "--occurrence=1"; // Stop after finding the first one
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
			$path = ltrim($path,'/');
			$connection = new Connection($s['host'], $s['user'], $s['password'], $s['port'], 90, ($s['transfer'] == 'passive'));
			try{
				$connection->open();
			}catch (\Exception $e){
				$this->b['error'] = $e->getMessage();
				backup_log($this->b['error']);
				return;
			}
			$wrapper = new FTPWrapper($connection);
			$permFactory = new PermissionsFactory;
			$ftptype = $wrapper->systype();
			if(strtolower($ftptype) == "unix"){
				$fsFactory = new FilesystemFactory($permFactory);
			}else{
				$fsFactory = new WindowsFilesystemFactory;
			}
			$manager = new FTPFilesystemManager($wrapper, $fsFactory);
			$dlVoter = new DownloaderVoter;
			$dlVoter->addDefaultFTPDownloaders($wrapper);
			$ulVoter = new UploaderVoter;
			$ulVoter->addDefaultFTPUploaders($wrapper);
			$crVoter = new CreatorVoter;
			$crVoter->addDefaultFTPCreators($wrapper, $manager);
			$deVoter = new DeleterVoter;
			$deVoter->addDefaultFTPDeleters($wrapper, $manager);
			$ftp = new FTP($manager, $dlVoter, $ulVoter, $crVoter, $deVoter);
			if(!$ftp){
				$this->b['error'] = _("Error creating the FTP object");
			}
			$ftpdirs = $ftp->findFilesystems(new \Touki\FTP\Model\Directory($s['path']));
			 $file = null;
			foreach($ftpdirs as $thisdir){
				if($ftp->fileExists(new \Touki\FTP\Model\File($path))){
					$file = $ftp->findFileByName($path);
				}
			}

			try{
				$options = array(
    			FTP::NON_BLOCKING  => false,     // Whether to deal with a callback while downloading
    			FTP::TRANSFER_MODE => FTP_BINARY // Transfer Mode
				);
				$ftp->download($dest,$file,$options);
				$path = $dest;
			}catch(\Exception $e){
					return array('error_msg' => _('Failed to retrieve file from server!'));
			}
			break;
		case 'ssh':
			$s['path'] = backup__($s['path']);
			$s['user'] = backup__($s['user']);
			$s['host'] = backup__($s['host']);
			$cmd[] = fpbx_which('scp');
			$cmd[] = '-o StrictHostKeyChecking=no -i';
			$cmd[] = $s['key'];
			$cmd[] = '-P ' . $s['port'];
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
		case 'awss3':
			$s['bucket'] = backup__($s['bucket']);
			$s['awsaccesskey'] = backup__($s['awsaccesskey']);
			$s['awssecret'] = backup__($s['awssecret']);
			$awss3 = new S3($s['awsaccesskey'], $s['awssecret']);
			dbug('S3 Path: ' . $path);
			dbug('S3 Dest: ' . $dest);
			if ($awss3->getObject($s['bucket'],$path,$dest) !== false) {
				$path = $dest;
			} else {
				return array('error_msg' => _('Failed to retrieve file from server!'));
			}
			break;
	}
	if (file_exists($path)) {
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
	$legacy_name = '';
	$name = pathinfo($bu, PATHINFO_BASENAME);
	if (substr($name, -7) != '.tar.gz' ) {
		return false;
	}

	//get legacy name based on the directory the legacy backup was origionally created in
	//were expcecting to see something like: /tmp/ampbackups.20110310.16.00.00/
	//in the tarball
	$cmd[] = fpbx_which('tar');
	$cmd[] = 'tf';
	$cmd[] = $bu;
	exec(implode(' ', $cmd), $res);
	unset($cmd);

	foreach ($res as $r) {
		if (preg_match('/\/tmp\/ampbackups\.([\d]{8}(\.[\d]{2}){3})\//', $r, $legacy_name)) {
			if (isset($legacy_name[1])) {
				$legacy_name = $legacy_name[1];
				break;
			}
		}
	}
	if (!$legacy_name) {
		return false;
	}

	//create directory where tarball will be exctracted to
	$dir = $amp_conf['ASTSPOOLDIR'] . '/tmp/' . $legacy_name;
	mkdir($dir, 0755, true);

	$cmd[] = fpbx_which('tar');
	$cmd[] = '-zxf';
	$cmd[] = $bu;
	$cmd[] = ' -C ' . $dir;
	exec(implode(' ', $cmd));
	unset($cmd);

	$dir2 = $dir . '/tmp/ampbackups.' . $legacy_name;

	//exctract sub tarballs
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
	$src = $dir2 . '/asterisk.sql';
	if (is_file($src)) {
		$dst = $dir2 . '/mysql-db.sql';

		unset($ret['file_list'][array_search('asterisk.sql', $ret['file_list'])]);//remove from manifest
		$ret['fpbx_db'] = 'mysql-db';
		$ret['mysql']['db'] = array('file' => 'mysql-db.sql');

		// remove SET and comments that later break restores when using pear
		$cmd[] = fpbx_which('grep');
		$cmd[] = "-v '^\/\*\|^SET\|^--'";
		$cmd[] = $src;
		$cmd[] = ' > ' .  $dst;
		exec(implode(' ', $cmd), $file, $status);
		if ($status) {
			// The grep failed, if there is a $dst file remove it and either way rename the $src
			freepbx_log(FPBX_LOG_ERROR,
				_("Failed converting asterisk.sql to proper format, renaming to mysql-db.sql in current state"));
			if (is_file($dst)) {
				unlink($dst);
			}
			rename($src,$dst);
		} else {
			unlink($src);
		}
		unset($cmd, $file);
	}

	$src = $dir2 . '/asteriskcdr.sql';
	if (is_file($src)) {
		$dst = $dir2 . '/mysql-cdr.sql';
		unset($ret['file_list'][array_search('asteriskcdr.sql', $ret['file_list'])]);//remove from manifest
		$ret['fpbx_cdrdb'] = 'mysql-cdr';
		$ret['mysql']['cdr'] = array('file' => 'mysql-cdr.sql');


		// remove SET and comments that later break restores when using pear
		$cmd[] = fpbx_which('grep');
		$cmd[] = "-v '^\/\*\|^SET\|^--'";
		$cmd[] = $src;
		$cmd[] = ' > ' .  $dst;
		exec(implode(' ', $cmd), $file, $status);
		if ($status) {
			// The grep failed, if there is a $dst file remove it and either way rename the $src
			freepbx_log(FPBX_LOG_ERROR, _("Failed converting asteriskcdr.sql to proper format, renaming to mysql-cdr.sql in current state"));
			if (is_file($dst)) {
				unlink($dst);
			}
			rename($src,$dst);
		} else {
			unlink($src);
		}
		unset($cmd, $file);
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
