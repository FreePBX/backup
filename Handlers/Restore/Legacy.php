<?php
/**
* Copyright Sangoma Technologies, Inc 2018
* Handle legacy backup files
*/
namespace FreePBX\modules\Backup\Handlers\Restore;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use PDO;
use FreePBX\modules\Backup\Handlers\FreePBXModule;
class Legacy extends Common {
	private $data;

	public function process(){
		$this->extractFile();
		$this->buildData($restore);
		$this->parseSQL();
	}

	protected function extractFile(){
		//remove backup tmp dir for extraction
		$this->fs->remove($this->tmp);

		//add tmp dir back
		$this->fs->mkdir($this->tmp);
		//We have to go the exec route because legacy backups root is ./ which breaks things
		$this->Backup->log(sprintf(_("Extracting: %s... This may take a moment depending on the backup size"), $this->file));
		$process = new Process(['tar', $this->file, '-C '.$this->tmp]);
		$process->mustRun();
		$this->Backup->log(sprintf(_("File extracted to %s. These files will remain until a new restore is run or until cleaned manually."),$this->tmp));
	}

	/**
	 * Build out Manifest
	 *
	 * @return void
	 */
	private function buildData(){
		$this->data['manifest'] = [];
		$this->data['astdb'] = [];
		if(file_exists(BACKUPTMPDIR . '/manifest')){
			$this->Backup->log(_("Loading manifest to memory"));
			$this->data['manifest'] = unserialize(file_get_contents($this->tmp.'/manifest'));
		}
		if(file_exists(BACKUPTMPDIR . '/astdb')){
			$this->Backup->log(_("Loading astdb to memory"));
			$this->data['astdb'] = unserialize(file_get_contents($this->tmp.'/astdb'));
		}
	}

	private function parseSQL(){
		$this->Backup->log(_("Parsing out SQL tables. This may take a moment depending on backup size."));
		$tables = $this->getModuleTables();
		$files = [];
		$final = ['unknown' => []];
		foreach (glob($this->tmp."/*.sql.gz") as $filename) {
			$files[] = $filename;
		}
		$amodules = $this->freepbx->Modules->getActiveModules();
		foreach ($amodules as $key => $value) {
			$final[$key] = [];
		}
		$this->Backup->log(sprintf(_("Found %s database files in the backup."),count($files)));
		foreach($files as $file){
			$this->Backup->log(sprintf(_("File named: %s"),$file));
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

	private function getModuleTables(){
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
