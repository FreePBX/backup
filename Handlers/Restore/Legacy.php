<?php
/**
* Copyright Sangoma Technologies, Inc 2018
* Handle legacy backup files
*/
namespace FreePBX\modules\Backup\Handlers\Restore;
use PDO;
class Legacy extends Common {
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \InvalidArgumentException('Not given a BMO Object');
		}
		$this->freepbx = $freepbx;
		$this->Backup = $freepbx->Backup;
		$webrootpath = $this->freepbx->Config->get('AMPWEBROOT');
		$webrootpath = (isset($webrootpath) && !empty($webrootpath)) ? $webrootpath : '/var/www/html';
		$this->data = [];

		define('WEBROOT', $webrootpath);
		define('BACKUPTMPDIR', '/var/spool/asterisk/tmp');
	}
	public function process($restore, $job, $warmspare){
		$this->extractFile($restore);
		$this->buildData($restore);
		$this->parseSQL();
	}
	public function getModuleTables(){
		$moduleManager = new FreePBXModule($this->freepbx);
		$amodules = $this->freepbx->Modules->getActiveModules();
		foreach ($amodules as $mod => $data) {
			$modTables = $moduleManager->getTables($mod);
			foreach ($modTables as $table) {
				$this->moduleData['tables'][$table] = $mod;
			}
		}
		return $this->moduleData['tables'];
	}
	public function buildData(){
		$this->data['manifest'] = [];
		$this->data['astdb'] = [];
		if(file_exists(BACKUPTMPDIR . '/manifest')){
			$this->Backup->log('',_("Loading manifest to memory").PHP_EOL);
			$this->data['manifest'] = unserialize(file_get_contents(BACKUPTMPDIR.'/manifest'));
		}
		if(file_exists(BACKUPTMPDIR . '/astdb')){
			$this->Backup->log('',_("Loading astdb to memory").PHP_EOL);
			$this->data['astdb'] = unserialize(file_get_contents(BACKUPTMPDIR.'/astdb'));
		}
	}

	public function extractFile($filepath){
		$this->Backup->log('',_("Cleaning up old data from the temp directory".PHP_EOL));
		$this->Backup->fs->remove(BACKUPTMPDIR);
		$this->Backup->fs->mkdir(BACKUPTMPDIR);
		//We have to go the exec route because legacy backups root is ./ which breaks things
		$this->Backup->log('',sprintf(_("Extracting: %s... This may take a moment depending on the backup size").PHP_EOL, $filepath));
		exec('tar -xzvf '.$filepath.' -C '.BACKUPTMPDIR, $out, $ret);
		if($ret == 0){
			$this->Backup->log('',sprintf(_("File extracted to %s. These files will remain until a new restore is run or until cleaned manually.").PHP_EOL,BACKUPTMPDIR));
		}
		return $ret;
	}

	public function parseSQL(){
		$this->Backup->log('',_("Parsing out SQL tables. This may take a moment depending on backup size.").PHP_EOL);
		$tables = $this->getModuleTables();
		$files = [];
		$final = ['unknown' => []];
		foreach (glob(BACKUPTMPDIR."/*.sql.gz") as $filename) {
			$files[] = $filename;
		}
		$amodules = $this->freepbx->Modules->getActiveModules();
		foreach ($amodules as $key => $value) {
			$final[$key] = [];
		}
		sprintf(_("Found %s database files in the backup.").PHP_EOL,count($files));
		foreach($files as $file){
			$this->Backup->log('',_("File named: ".$file.PHP_EOL));
			$pdo = $this->setupTempDb($file);
			$loadedTables = $pdo->query("SHOW TABLES");
			while ($current = $loadedTables->fetch(PDO::FETCH_COLUMN)) {
				if(!isset($tables[$current])){
					$final['unknown'][] = $current;
					continue;
				}
				$final[$tables[$current]][] = $current;
			}
			$dt = $this->data['manifest']['fpbx_cdrdb'];
			$scndCndtn = preg_match("/$dt/i",$file);
			$data = [
					'final' => $final,
					'pdo' => $pdo,
			];
			if(!empty($dt) && $scndCndtn){
				$this->processLegacyCdr($data);
			}else{
				$this->processLegacyNormal($data);
			}
		}
	}
	public function setupTempDb($file){
		sprintf(_("Loading supplied database file %s").PHP_EOL, $file);
		exec('mysqladmin -f DROP asterisktemp', $out, $ret);
		exec('mysqladmin CREATE asterisktemp', $out, $ret);
		$this->Backup->log('',_("Temporary DB asterisktemp CREATED".PHP_EOL));
		$this->Backup->log('',_("Loading content to asterisktemp".PHP_EOL));
		system('pv '.$file.' | gunzip | mysql asterisktemp', $out);
		$this->Backup->log('',_("Temporary DB asteriskcdrdb loaded with ".$file." data.".PHP_EOL));
		$host = '127.0.0.1';
		$db = 'asterisktemp';
		$user = 'root';
		$pass = '';
		$charset = 'utf8mb4';

		$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
		$opt = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
		];
		$this->tempDB = new PDO($dsn, $user, $pass, $opt);
		return $this->tempDB;
	}
	public function processLegacyCdr($info){
		foreach ($info['final'] as $key => $value) {
			if($key === 'cdr' || $key === 'cel' || $key === 'queuelog'){
				$namespace = '\\FreePBX\\modules\\'.ucfirst($key).'\\Restore';
				if(!class_exists($namespace)){
					sprintf(_("Couldn't find %s").PHP_EOL,$namespace);
					continue;
				}
				$class = new $namespace(null,$this->freepbx, BACKUPTMPDIR);
				if(method_exists($class,'processLegacy')){
					$this->Backup->log('',sprintf(_("Calling legacy restore on module %s".PHP_EOL),$key));
					$class->processLegacy($info['pdo'], $this->data, $value, $info['final']['unknown'],BACKUPTMPDIR);
					unset($class);
					continue;
				}
				$this->Backup->log('',sprintf(_("The module %s does not seem to support legacy restores." . PHP_EOL), $key));
			}else{
				continue;
			}
		}
	}
	public function processLegacyNormal($info){
		foreach ($info['final'] as $key => $value) {
			if($key === 'unknown' || $key === 'framework' || $key === 'cdr' || $key === 'cel' || $key === 'queuelog'){
				continue;
			}
			$namespace = '\\FreePBX\\modules\\'.ucfirst($key).'\\Restore';
			if(!class_exists($namespace)){
				sprintf(_("Couldn't find %s").PHP_EOL,$namespace);
				continue;
			}
			$class = new $namespace(null,$this->freepbx, BACKUPTMPDIR);
			if(method_exists($class,'processLegacy')){
				$this->Backup->log('',sprintf(_("Calling legacy restore on module %s".PHP_EOL),$key));
				$class->processLegacy($info['pdo'], $this->data, $value, $info['final']['unknown'],BACKUPTMPDIR);
				unset($class);
				continue;
			}
			$this->Backup->log('',sprintf(_("The module %s does not seem to support legacy restores." . PHP_EOL), $key));
		}
	}
}
