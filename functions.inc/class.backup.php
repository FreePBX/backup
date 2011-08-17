<?php

class Backup {
	
	/**
	 * Holds a list of paths to applications that we might need
	 * @param var
	 */
	public $apps;
	
	
	/**
	 * Holds settings for this backup
	 * @param var
	 */
	public $b;
	
	/**
	 * Holds a list of all servers
	 * @param var
	 */
	public $s;
	
	/**
	 * Holds a list of all templates
	 * @param var
	 */
	public $t;
	
	function __construct($b, $s, $t = '') {
		global $amp_conf, $db, $cdrdb;
		$this->b						= $b;
		$this->s						= $s;
		//$this->t						= $t;
		$this->amp_conf					= $amp_conf;
		
		$this->b['_ctime']				= time();
		$this->b['_file']				= date("Ymd-His-") . $this->b['_ctime'] . '-' . rand();
		$this->b['_dirname']			= trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $this->b['name']), '_');
		
		$this->db						= $db;
		$this->cdrdb					= $cdrdb;
		$this->amp_conf['CDRDBTYPE']	= $this->cdrdb->dsn['phptype'];
		$this->amp_conf['CDRDBHOST']	= $this->cdrdb->dsn['hostspec'];
		$this->amp_conf['CDRDBUSER']	= $this->cdrdb->dsn['username'];
		$this->amp_conf['CDRDBPASS']	= $this->cdrdb->dsn['password'];
		$this->amp_conf['CDRDBPORT']	= $this->cdrdb->dsn['port'];
		$this->amp_conf['CDRDBNAME']	= $this->cdrdb->dsn['database'];

		ksort($this->b);
	}
	
	function __destruct() {
		//remove temp files and directories
		if (is_file($this->b['_tmpfile'])) {
			unlink($this->b['_tmpfile']);
		}
		
		//remove file lock and release file handler
		if (isset($this->lock) && $this->lock) {
			flock($this->lock, LOCK_UN);
			fclose($this->lock);
			unlink($this->lock_file);
		}
		
		if (is_dir($this->b['_tmpdir'])) {
			$cmd = 'rm -rf ' . $this->b['_tmpdir'];
			exec($cmd);
		}
		
		/* 
		 * cleanup stale backup files (older than one day)
		 * these files are those that were downloaded from a remote server
		 * usually, backups will be deleted after a restore
		 * but the user aborted the restore/decided not to go through with it
		 */
		$files = scandir($this->amp_conf['ASTSPOOLDIR'] . '/tmp/');
		foreach ($files as $file) {
			$f = explode('-', $file);
			if ($f[0] == 'backuptmp' && $f[2] < strtotime('yesterday')) {
				unlink($this->amp_conf['ASTSPOOLDIR'] . '/tmp/' . $file);
			}
		}
		
	}

	function init() {
		$this->b['_dirpath']	= $this->amp_conf['ASTSPOOLDIR'] . '/backup/' . $this->b['_dirname'];
		$this->b['_tmpdir']		= $this->amp_conf['ASTSPOOLDIR'] . '/tmp/backup-' . $this->b['id'];
		$this->b['_tmpfile']	= $this->amp_conf['ASTSPOOLDIR'] . '/tmp/' . $this->b['_file'] . '.tgz';
		$this->lock_file		= $this->b['_tmpdir'] . '/.lock';
		
		//create backup directory
		if (!(is_dir($this->b['_tmpdir']))) {
			mkdir($this->b['_tmpdir'], 0755, true);
		}
	}
	
	
	function acquire_lock() {
		//acquire file handler on lock file

		//TODO: use 'c+' once the project require php > 5.2.8
		$this->lock	= fopen($this->lock_file, 'x+');
		if (flock($this->lock, LOCK_EX | LOCK_NB)) {
			return true;
		} else {
			fclose($this->lock);
			unlink($this->lock_file);
			return false;
		}
	}
	
	function add_items() {
		foreach ($this->b['items'] as $i) {
			switch ($i['type']) {
				case 'file':
					//substitute variable if nesesary
					$i['path'] = backup__($i['path']);
					
					//make sure file exists
					if (!is_file($i['path']) && !is_link($i['path'])) {
						break;
					}
					
					//ensure directory structure
					$dest = $this->b['_tmpdir'] . realpath($i['path']);
					if (!is_dir(dirname($dest))) {
						mkdir(dirname($dest), 0755, true);
					}
					
					//copy file
					copy($i['path'], $dest);
					break;
				case 'dir':
				
					//subsitute variable if nesesary
					$i['path'] = backup__($i['path']);
					
					//ensure directory exists
					if (!is_dir($i['path'])) {
						break;
					}
					
					//ensure directory structure
					$dest = $this->b['_tmpdir'] . realpath($i['path']);
					if (!is_dir($dest)) {
						mkdir($dest, 0755, true);
					}
					
					//build command. Were using tar to copy files for two reasons:
					//a. it's recursive
					//b. it offers excludes
					$cmd[] = fpbx_which('tar') . ' cf - ' . $i['path'];
					if ($i['exclude']) {
						$excludes = explode("\n", $i['exclude']);
						foreach ($excludes as $x) {
							$cmd[] = " --exclude='$x'";
						}
					}
					$cmd[] = ' | ' . fpbx_which('tar') . ' xf - -C ' . $this->b['_tmpdir'];
					exec(implode(' ', $cmd));
					unset($cmd);
					break;
				case 'mysql':
					//build command
					$s = str_replace('server-', '', $i['path']);
					$cmd[] = fpbx_which('mysqldump');
					$cmd[] = '--host='		. backup__($this->s[$s]['host']);
					$cmd[] = '--port='		. backup__($this->s[$s]['port']);
					$cmd[] = '--user='		. backup__($this->s[$s]['user']);
					$cmd[] = '--password='	. backup__($this->s[$s]['password']);
					$cmd[] = backup__($this->s[$s]['dbname']);

					if ($i['exclude']) {
						foreach ($i['exclude'] as $x) {
							$cmd[] = '--ignore-table=' . backup__($this->s[$s]['dbname']) 
									. '.' . backup__($x);
						}
					}
					$cmd[] = ' --opt --skip-comments';
					//$cmd[] = ' > ' . $this->b['_tmpdir'] . '/' . 'mysql-' . $s . '.sql';
					exec(implode(' ', $cmd), $file, $status);

					//dont keep the file if the command failed somehow
					if ($status !== 0) {
						unlink($this->b['_tmpdir'] . '/' . 'mysql-' . $s);
					} else {
						//remove comments from the file, otherwise we have difficulty restoring
						foreach ($file as $f => $line) {
							if (substr($line, 0, 2) == '/*' || substr($line, 0, 3) == 'SET') {
								unset($file[$f]);
							}
						}
						file_put_contents($this->b['_tmpdir'] . '/' . 'mysql-' . $s . '.sql', 
											implode("\n", $file));
					}
;
					unset($cmd, $file);
					break;
				case 'astdb':
					$exclude = array('RG', 'BLKVM', 'FM', 'dundi');
					$exclude = array_merge(explode("\n", $i['exclude']), $exclude);
					$astdb = astdb_get($exclude);
					file_put_contents($this->b['_tmpdir'] . '/astdb', serialize($astdb));
					break;
			}
		}
		
		
	}
	
	function run_hooks($hook) {
		switch ($hook) {
			case 'pre-backup':
				if ($b['prebu_hook']) {
					exec($b['prebu_hook']);
				}
				mod_func_iterator('backup_pre_backup_hook', $this);
				break;
			case 'post-backup':
				if ($b['postbu_hook']) {
					exec($b['postbu_hook']);
				}
				mod_func_iterator('backup_post_backup_hook', $this);
				break;
		}
	}
	
	function create_backup_file($to_stdout = false) {
		$this->build_manifest();
		$cmd[] = fpbx_which('tar');
		$cmd[] = 'zcf';
		$cmd[] = $to_stdout ? '-' : $this->b['_tmpfile'];
		$cmd[] = '-C ' . $this->b['_tmpdir'];
		$cmd[] = '.';
		exec(implode(' ', $cmd));
	}
	
	function store_backup() {
		foreach ($this->b['storage_servers'] as $s) {
			$s = $this->s[$s];
			switch ($s['type']) {
				case 'local':
					//ensure directory structure
					if (!is_dir($this->b['_dirpath'])) {
						mkdir($this->b['_dirpath'], 0755, true);
					}
					
					copy($this->b['_tmpfile'], $this->b['_dirpath'] . '/' . $this->b['_file'] . '.tgz');
					
					//run maintenance on the directory
					$this->maintenance($s['type'], $s);
					break;
				case 'email':
					//dont run if the file is too big
					if (filesize($this->b['_tmpfile']) > $s['maxsize']) {
						continue;
					}
					
					//TODO: set agent to something informative, including fpbx & backup versions
					$email_options = array('useragent' => 'freepbx', 'protocol' => 'mail');
					$email = new CI_Email();
					$from = $this->amp_conf['AMPBACKUPEMAILFROM'] 
							? $this->amp_conf['AMPBACKUPEMAILFROM'] 
							: 'freepbx@freepbx.org';
	
					$msg[] = _('Name')			. ': ' . $this->b['name'];
					$msg[] = _('Created')		. ': ' . date('r', $this->b['_ctime']);
					$msg[] = _('Files')			. ': ' . $this->manifest['file_count'];
					$msg[] = _('Mysql Db\'s')	. ': ' . $this->manifest['mysql_count'];
					$msg[] = _('astDb\'s')		. ': ' . $this->manifest['astdb_count'];
					
					$email->from($from);
					$email->to(backup__($s['addr']));
					$email->subject(_('Backup') . ' ' . $this->b['name']);
					$email->message(implode("\n", $msg));
					$email->attach($this->b['_tmpfile']);
					$email->send();
					
					unset($msg);
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
						
						//switch to directory. If we fail, build directory structure and try again
						if (!ftp_chdir($ftp, $s['path'] . '/' . $this->b['_dirname'])) {
							//ensure directory structure
							ftp_mkdir($ftp, $s['path']);
							ftp_mkdir($ftp, $s['path'] . '/' . $this->b['_dirname']);
							ftp_chdir($ftp, $s['path'] . '/' . $this->b['_dirname']);
						}

						//copy file
						ftp_put($ftp, $this->b['_file'] . '.tgz', $this->b['_tmpfile'], FTP_BINARY);
						
						//run maintenance on the directory
						$this->maintenance($s['type'], $s, $ftp);
						
						//release handel
						ftp_close($ftp);
					} else {
						dbug('ftp connect error');
					}
					break;
				case 'ssh':
					//subsitute variables if nesesary
					$s['path'] = backup__($s['path']);
					$s['user'] = backup__($s['user']);
					$s['host'] = backup__($s['host']);
					
					//ensure directory structure
					$cmd[] = fpbx_which('ssh');
					$cmd[] = '-o StrictHostKeyChecking=no -i';
					$cmd[] = $s['key'];
					$cmd[] = $s['user'] . '\@' . $s['host'];
					$cmd[] = 'mkdir -p ' . $s['path'] 
							. '/' . $this->b['_dirname'];

					exec(implode(' ', $cmd));
					unset($cmd);
					
					//put file
					$cmd[] = fpbx_which('scp');
					$cmd[] = '-o StrictHostKeyChecking=no -i';
					$cmd[] = $s['key'];
					$cmd[] = $this->b['_tmpfile'];
					$cmd[] = $s['user'] . '\@' . $s['host'] 
							. ':' . $s['path'] . '/' . $this->b['_dirname'];

					exec(implode(' ', $cmd));
					unset($cmd);
					
					//run maintenance on the directory
					$this->maintenance($s['type'], $s);
					break;
			}
		}
	}
	
	function build_manifest() {
		$ret['fpbx_db']		= '';
		$ret['fpbx_cdrdb']	= '';
		$ret['name']		= $this->b['name'];
		$ret['ctime']		= $this->b['_ctime'];
		$ret['hooks']		= array(
							'pre_backup'	=> $this->b['prebu_hook'],
							'post_backup'	=> $this->b['postbu_hook'],
							'pre_restore'	=> $this->b['prere_hook'],
							'post_restore'	=> $this->b['postre_hook']
		);
		//TODO: add fpnx/asterisk/backup verions
		//TODO: add format version, same as backup module version
		//get all files in the directory
		$ret['file_list']	= scandirr($this->b['_tmpdir']);

		//remove the mysql/astdb files, add them seperatly
		foreach($ret['file_list'] as $key => $file) {
			if (!is_array($file)) {
				if ($file == 'astdb') {
					unset($ret['file_list'][$key]);
					$ret['astdb'] = 'astdb';
				} elseif (strpos($file, 'mysql-') === 0) {
					//get server id
					$s = substr($file, 6);
					$s = substr($s, 0, -4);
					
					//get exclude
					foreach($this->b['items'] as $i) {
						if($i['type'] == 'mysql' && $i['path'] == 'server-' . $s) {
							$exclude = $i['exclude'];
							break;
						}
					}
					
					//build array on this server
					$ret['mysql'][$s] = array(
								'file'		=> $file,
								'host'		=> backup__($this->s[$s]['host']),
								'port'		=> backup__($this->s[$s]['port']),
								'name'		=> backup__($this->s[$s]['name']),
								'dbname'	=> backup__($this->s[$s]['dbname']),
								'exclude'	=> $exclude
					);
					
					//if this server is freepbx's primary server datastore, record that
					if ($ret['mysql'][$s]['dbname'] == $this->amp_conf['AMPDBNAME']) {
						
						//localhost and 127.0.0.1 are intergangeable, so test both scenarios
						if (in_array(strtolower($ret['mysql'][$s]['host']), array('localhost', '127.0.0.1'))
							&& in_array(strtolower($this->amp_conf['AMPDBHOST']), array('localhost', '127.0.0.1'))
							|| $ret['mysql'][$s]['host'] == $this->amp_conf['AMPDBHOST']
						) {
							$ret['fpbx_db'] = 'mysql-' . $s;
						}
						
						//if this server is freepbx's primary cdr server datastore, record that
					} elseif($ret['mysql'][$s]['dbname'] == $this->amp_conf['CDRDBNAME']) {
						//localhost and 127.0.0.1 are intergangeable, so test both scenarios
						if (in_array(strtolower($ret['mysql'][$s]['host']), array('localhost', '127.0.0.1'))
							&& in_array(strtolower($this->amp_conf['CDRDBHOST']), array('localhost', '127.0.0.1'))
							|| $ret['mysql'][$s]['host'] == $this->amp_conf['CDRDBHOST']
						) {
							$ret['fpbx_cdrdb'] = 'mysql-' . $s;
						}
					}
					unset($ret['file_list'][$key]);
				} elseif ($file == '.lock') {
					unset($ret['file_list'][$key]);
				}
			}
		}
		
		$ret['hostname']	= php_uname("n");
		$ret['file_count']	= count($ret['file_list'], COUNT_RECURSIVE);
		$ret['mysql_count']	= $ret['mysql'] ? count($ret['mysql']) : 0;
		$ret['astdb_count']	= $ret['astdb'] ? count($ret['astdb']) : 0;
		$ret['ftime']		= time();//finish time
		
		$this->b['manifest'] = $ret;
	}
	
	function save_manifest($location) {
		switch ($location) {
			case 'local':
				file_put_contents($this->b['_tmpdir'] . '/manifest', serialize($this->b['manifest']));
				break;
			case 'db':
				$manifest = $this->b['manifest'];
				unset($manifest['file_list']);
				//save manifest in db
				//dontsave the file list in the db - its way to big
				
				$sql = 'INSERT INTO backup_cache (id, manifest) VALUES (?, ?)';
				$q = $this->db->query($sql, array($this->b['_file'], serialize($manifest)));
				if ($this->db->IsError($q)){
					die_freepbx($q->getDebugInfo());
				}
				break;
		}
	}
	
	private function maintenance($type, $data, $handle = '') {
		if (!isset($this->b['delete_time'])  && !isset($this->b['delete_amount'])) {
			return true;
		}
		$delete = $dir = array();
		
		//get file list
		switch ($type) {
			case 'local':
				$dir = scandir($this->b['_dirpath']);
				break;
			case 'ftp':
				$dir = ftp_nlist($handle, '.');
				break;
			case 'ssh':
				$cmd[] = fpbx_which('ssh');
				$cmd[] = '-o StrictHostKeyChecking=no -i';
				$cmd[] = $data['key'];
				$cmd[] = $data['user'] . '\@' . $data['host'];
				$cmd[] = 'ls -1 ' . $data['path'] . '/' . $this->b['_dirname'];
				exec(implode(' ', $cmd), $dir);
				unset($cmd);
				break;
		}
		
		//sanitize file list
		foreach ($dir as $file) {
			//dont include the current backup or special items
			if (in_array($file, array('.', '..', $this->b['_file']))) {
				continue;
			}
			$f = explode('-', $file);
			
			//remove file sufix
			$files[$f[2]] = $file;
			
		}
		
		
		//sort file list based on backup creation time
		ksort($files, SORT_NUMERIC);

		//create delete list based on creation time
		if (isset($this->b['delete_time']) && $this->b['delete_time']) {
			$cut_line = strtotime($this->b['delete_time'] . ' ' . $this->b['delete_time_type'] . ' ago');
			foreach ($files as $epoch => $file) {
				if ($epoch < $cut_line) {
					$delete[$epoch] = $file;
				}
			}
		}
		
		//create delete list based on quantity of files
		if (isset($this->b['delete_amount']) && $this->b['delete_amount']) {
			for ($i = 0; $i < $this->b['delete_amount']; $i++) {
				array_pop($files);
			}
			$delete = array_merge($files, $delete);
		}
		
		//now delete the actual files
		foreach($delete as $key => $file) {
			switch($type) {
				case 'local':
					unlink($this->b['_dirpath'] . '/' . $file);
					unset($delete[$key]);
					break;
				case 'ftp':
					ftp_delete($handle, $file);
					unset($delete[$key]);
					break;
				case 'ssh':
					$cmd[] = fpbx_which('ssh');
					$cmd[] = '-o StrictHostKeyChecking=no -i';
					$cmd[] = $data['key'];
					$cmd[] = $data['user'] . '\@' . $data['host'];
					$cmd[] = 'rm ' . $data['path'] . '/' . '/' . $this->b['_dirname'] . '/' . $file;
					exec(implode(' ', $cmd));
					unset($delete[$key]);
					unset($cmd);
					break;
			}
		}
		
	}
	
}
?>