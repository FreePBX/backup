<?php
namespace FreePBX\modules\Backup;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Filesystem\Filesystem;
use FreePBX\modules\Backup\Models as Model;
/**
 * This is a base class used when creating your modules "Backup.php" class
 */
class BackupBase extends Model\Backup{

	/**
	 * Run Backup method. This is implemented by other modules
	 *
	 * If it's not implemented then it will export defaults by guessing
	 *
	 * @param [type] $id
	 * @param [type] $transaction
	 * @return void
	 */
	public function runBackup($id,$transaction) {
		if($this->defaultFallback) {
			$this->log(sprintf(_("RunBackup method is not implemented in %s, using default fallback"), $module),'WARNING');
			$this->addConfigs(array_merge($this->dumpAll(),["defaultFallback" => true]));
		} else {
			$this->log(sprintf(_("RunBackup method is not implemented in %s"), $module),'WARNING');
		}
	}

	/**
	 * Dump all relevant settings into an array
	 *
	 * Advanced Settings
	 * Feature Codes
	 * Module Tables
	 * Key Value Store
	 *
	 * @return array
	 */
	public function dumpAll($under_score=false) {
		return [
			"settings" => $this->dumpAdvancedSettings(),
			"features" => $this->dumpFeatureCodes(),
			"tables" => $this->dumpTables($under_score),
			"kvstore" => $this->dumpKVStore()
		];
	}

	/**
	 * Dump all known advanced settings
	 *
	 * @return array
	 */
	public function dumpAdvancedSettings() {
		$module = strtolower($this->data['module']);
		$this->log(sprintf(_("Exporting Advanced settings from %s"), $module));
		$sql = "SELECT `keyword`, `value` FROM freepbx_settings WHERE module= :name";
		$sth = $this->FreePBX->Database->prepare($sql);
		$sth->execute([":name" => $module]);
		return $sth->fetchAll(\PDO::FETCH_KEY_PAIR);
	}

	/**
	 * Dump all known feature codes
	 *
	 * @return array
	 */
	public function dumpFeatureCodes() {
		$module = strtolower($this->data['module']);
		$this->log(sprintf(_("Exporting Feature Codes from %s"), $module));
		$sql = "SELECT `featurename` , `description` , `helptext` , `defaultcode` , `customcode` , `enabled` , `providedest` FROM featurecodes WHERE modulename = :name";
		$sth = $this->FreePBX->Database->prepare($sql);
		$sth->execute([":name" => $module]);
		return $sth->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_ASSOC|\PDO::FETCH_UNIQUE);
	}
	/**
	*  $modulename
	*   $under_score = true or false search with like modulename_
	*/
	public function dumpDBTables($modename,$under_score=true,$ignoretables=[]) {
		if(!$under_score) {
			$query = "SELECT table_name FROM information_schema.tables WHERE table_name LIKE '".$modename."%'";
		} else {
			$query = "SELECT table_name FROM information_schema.tables WHERE table_name LIKE '".$modename."\_%'";
		}
		$tables = $this->FreePBX->Database->query($query)->fetchAll(\PDO::FETCH_ASSOC);
		$ret = [];
		foreach($tables as $table) {
			$tname = (string)$table['table_name'];
			if(in_array($tname,$ignoretables)){
				continue;
			}
			$ret[$tname] = $this->FreePBX->Database->query("SELECT * FROM $tname")->fetchAll(\PDO::FETCH_ASSOC);
		}
		return $ret;
	}

	/**
	 * dumpAstDB
	 *
	 * @param  string $family
	 *
	 * @return array()
	 */
	public function dumpAstDB($family = ""){
		if(!is_string($family)){
			return array();
		}

		$astdb 	= $this->FreePBX->astman->command("database show $family");
		$astdb 	= explode("\n",$astdb["data"]);
		$result = array();
		foreach($astdb as $line){
			list($root, $value) = explode(":",$line);
			if(strpos($root, $family) !== False){
				$children 	= substr(trim($root),1);
				$children	= trim(substr(str_replace($family, "", $children),1));
				$result[] 	= array($family => array($children => trim($value)));
			}
		}
		return $result;
	}
  
	/**
	 * Dump all known databases from said module
	 *
	 * @return array
	 */
	public function dumpTables($under_score=false,$ignoretables = []) {
		$module = strtolower($this->data['module']);
		$this->log(sprintf(_("Exporting Databases from %s"), $module));
		$dir = $this->FreePBX->Config->get('AMPWEBROOT').'/admin/modules/'.$module;
		if(!file_exists($dir.'/module.xml')) {
			return [];
		}
		$xml = simplexml_load_file($dir.'/module.xml');
		$tables = [];
		$tables = $this->dumpDBTables($module, $under_score,$ignoretables);

		if(is_object($xml->database->table)) {
			foreach($xml->database->table as $table) {
				$tname = (string)$table->attributes()->name;
				//ignore tables
				if(in_array($tname,$ignoretables)) {
					continue;
				}

				if(array_key_exists($tname,$tables)) {
					continue;
				} else {
					$tables[$tname] = $this->FreePBX->Database->query("SELECT * FROM $tname")->fetchAll(\PDO::FETCH_ASSOC);
				}
			}
		}
		return $tables;
	}

	/**
	 * Dump KVStore to a multidimensional array
	 *
	 * @return array
	 */
	public function dumpKVStore($ids=false) {
		$module = ucfirst(strtolower($this->data['module']));
		$this->log(sprintf(_("Exporting KVStore from %s"), $module));
		if (is_array($ids)) {
			$this->log(sprintf(_("Exporting KVStore based on ids  %s"), implode(',',$ids)));
		}
		if(!is_subclass_of($this->FreePBX->$module,'FreePBX\DB_Helper')) {
			return [];
		}
		if(!is_array($ids)) {
			$ids = $this->FreePBX->$module->getAllids();
			$ids[] = 'noid';
		}
		$final = [];
		foreach($ids as $id) {
			$final[$id] = $this->FreePBX->$module->getAll($id);
		}
		return $final;
	}

	/**
	 * Dump specific tables
	 *
	 * @param String $moduleName
	 * @param String $tableName
	 * @param Boolean $dumpOtherOptions
	 * @param Boolean $zipDump
	 * @return Object
	 */
	function dumpTableIntoFile($moduleName, $tableName, $dumpOtherOptions = false, $zipDump = false)
	{
		global $amp_conf;

		$dbhost = $amp_conf['AMPDBHOST'];
		$dbuser = $amp_conf['AMPDBUSER'];
		$dbport = $amp_conf['AMPDBPORT'];
		$dbpass = $amp_conf['AMPDBPASS'];
		$dbname = $amp_conf['AMPDBNAME'];

		$cdrDbTables = ['cdr', 'cel', 'queuelog'];
		if (in_array($tableName, $cdrDbTables)) {
			$dbhost = $this->FreePBX->Config->get('CDRDBHOST') ? $this->FreePBX->Config->get('CDRDBHOST') : $amp_conf['AMPDBHOST'];
			$dbuser = $this->FreePBX->Config->get('CDRDBUSER') ? $this->FreePBX->Config->get('CDRDBUSER') : $amp_conf['AMPDBUSER'];
			$dbport = $this->FreePBX->Config->get('CDRDBPORT') ? $this->FreePBX->Config->get('CDRDBPORT') : $amp_conf['AMPDBPORT'];
			$dbpass = $this->FreePBX->Config->get('CDRDBPASS') ? $this->FreePBX->Config->get('CDRDBPASS') : $amp_conf['AMPDBPASS'];
			$dbname = $this->FreePBX->Config->get('CDRDBNAME') ? $this->FreePBX->Config->get('CDRDBNAME') : 'asteriskcdrdb';
		}

		$fs = new Filesystem();
		$tmpdir = sys_get_temp_dir() . "/{$moduleName}_dump";
		if (is_dir($tmpdir)) {
			$fs->remove($tmpdir);
		}
		$fs->mkdir($tmpdir);

		$mysqldump = fpbx_which('mysqldump');
		
		$fileExt = $zipDump ? 'sql.gz' : 'sql';
		$tmpfile = "{$tmpdir}/{$moduleName}.{$fileExt}";

		$dbhost = ($dbhost === 'localhost' || $dbhost === '127.0.0.1') ? '' : '-h ' . $dbhost;
		$dbport = empty(trim($dbport)) ? '' : '-P ' . $dbport;

		$command = "{$mysqldump} {$dbport} {$dbhost} -u{$dbuser} -p{$dbpass} {$dbname} ";

		if ($dumpOtherOptions) {
			$command .= "{$dumpOtherOptions} ";
		}

		$command .= "{$tableName} ";
		
		if ($zipDump) {
			$command .= "| gzip -9 > {$tmpfile}";
		} else {
			$command .= "--result-file={$tmpfile}";
		}

		$this->log(sprintf(_("Starting mysql dumps of : %s"), $tableName));

		try {
			$process = new Process($command);
			$process->setTimeout(3600);
			$process->disableOutput();
			$process->mustRun();
		} catch (ProcessFailedException $e) {
			$this->log(sprintf(_("%s table Backup Error %s "), $tableName, $e->getMessage()), 'ERROR');
			return false;
		}
		$fileObj = new \SplFileInfo($tmpfile);
		$this->addSplFile($fileObj);
		// deletes files, directories and symlinks
		$this->addGarbage($tmpdir);
		$this->log(sprintf(_("Completed mysql dumps of : %s"), $tableName));
		return $fileObj;
	}
}
