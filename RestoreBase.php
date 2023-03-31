<?php
namespace FreePBX\modules\Backup;
use PDO;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Exception;
/**
 * This is a base class used when creating your modules "Restore.php" class
 */
class RestoreBase extends \FreePBX\modules\Backup\Models\Restore{

	/**
	 * Run Restore Method used by other modules
	 *
	 * @return void
	 */
	public function runRestore() {
		$configs = $this->getConfigs();
		if(!empty($configs['defaultFallback']) && $this->defaultFallback) {
			$this->log(sprintf(_('RunRestore method is not implemented in %s, but was backed up using default backup fallback, using default fallback'),$this->data['module']),'WARNING');
			$this->importAll($configs);
		} elseif(!empty($configs['defaultFallback']) && !$this->defaultFallback) {
			$this->log(sprintf(_('RunRestore method is not implemented in %s, however there is default falback data, nothing to do'),$this->data['module']),'WARNING');
		} else {
			$this->log(sprintf(_('RunRestore method is not implemented in %s, nothing to do'),$this->data['module']),'WARNING');
		}
	}

	/**
	 * Process Legacy Method used by other modules
	 *
	 * @param PDO $pdo The pdo connection for the temporary database
	 * @param array $data An array with 'manifest', 'astdb', 'settings', 'features' as arrays
	 * @param array $tables is a list of tables we determined belong to the module
	 * @param array $unknownTables is an array of tables we don't have an owner for
	 * @return void
	 */
	public function processLegacy($pdo, $data, $tables, $unknownTables) {
		if($this->defaultFallback) {
			$this->log(sprintf(_('Legacy Restore in %s is not implemented, using default fallback'),$this->data['module']),'WARNING');
			$this->restoreLegacyAll($pdo);
		} else {
			$this->log(sprintf(_('Legacy Restore in %s is not implemented'),$this->data['module']),'WARNING');
		}
	}

	/**
	 * Import all from a multidimensional array
	 *
	 * @param array $data
	 * @return void
	 */
	public function importAll($data) {
		if(!empty($data['settings']) && is_array($data['settings'])) {
			$this->importAdvancedSettings($data['settings']);
		}

		if(!empty($data['codes']) && is_array($data['codes'])) {
			$this->importFeatureCodes($data['codes']);
		}

		if(!empty($data['astdb']) && is_array($data['astdb'])) {
			$this->importAstDB($data['astdb']);
		}

		if(!empty($data['tables']) && is_array($data['tables'])) {
			$this->importTables($data['tables']);
		}

		if(!empty($data['kvstore']) && is_array($data['kvstore'])) {
			$this->importKVStore($data['kvstore']);
		}
	}

	/**
	 * Import advanced settings from a multidimensional array
	 *
	 * @param array $settings
	 * @return void
	 */
	public function importAdvancedSettings($settings) {
		if(empty($settings)) {
			return;
		}
		$this->log(sprintf(_('Importing Advanced Settings from %s'),$this->data['module']));
		$module = ucfirst(strtolower($this->data['module']));
		$sql = "UPDATE IGNORE freepbx_settings SET `value` = :value WHERE `keyword` = :keyword AND `module` = :module";
		$sth = $this->FreePBX->Database->prepare($sql);
		foreach($settings as $keyword => $value) {
			$sth->execute([
				":keyword" => $keyword,
				":value" => $value,
				":module" => strtolower($this->data['module'])
			]);
		}
	}

	/**
	 * Import Feature codes from a multidimensional array
	 *
	 * @param array $codes
	 * @return void
	 */
	public function importFeatureCodes($codes) {
		if(empty($codes)) {
			return;
		}
		$this->log(sprintf(_('Importing Feature Codes from %s'),$this->data['module']));
		$module = strtolower($this->data['module']);

		$sql = "DELETE FROM `featurecodes` WHERE `modulename` = :modulename";
		$sth = $this->FreePBX->Database->prepare($sql);
		$sth->execute(array(":modulename" => $module));

		$sql = "INSERT INTO featurecodes (`modulename`, `featurename`, `description`, `helptext`, `defaultcode`, `customcode`, `enabled`, `providedest`) VALUES (:modulename, :featurename, :description, :helptext, :defaultcode, :customcode, :enabled, :providedest)";
		$sth = $this->FreePBX->Database->prepare($sql);
		foreach($codes as $key => $data) {
			$sth->execute([
				":description" 	=> $data['description'],
				":helptext" 	=> $data['helptext'],
				":defaultcode" 	=> $data['defaultcode'],
				":providedest" 	=> $data['providedest'],
				":customcode" 	=> $data['customcode'],
				":enabled" 		=> $data['enabled'],
				":featurename" 	=> $key,
				":modulename" 	=> $module
			]);
		}
	}

	/**
	 * Import Asterisk Database from a multidimensional array
	 *
	 * @param array $families
	 * @return void
	 * 
	 * expected data:
	 *  "astdb": [
     *      {
     *          "DAYNIGHT": {
     *             "C0": "DAY"
     *          }
     *      }, ....
     *  ],
	 */
	public function importAstDB($data) {
		if(empty($data) && !is_array($data)) {
			return;
		}

		foreach($data as $key => $content) {			
			foreach($content as $family => $extra) {
				if(!is_array($extra)){// content is not array eg:/DAYNIGHT/C      : NIGHT
					$this->log(sprintf(_('Importing AstDB family %s/%s  : %s'),$key,$family,$extra));
					$this->FreePBX->astman->database_put($key,$family,$extra);
					continue;
				}
				foreach($extra as $children => $val ){
					$this->log(sprintf(_('Importing AstDB family %s from %s'),$family,$this->data['module']));
					$this->FreePBX->astman->database_put($family,$children,$val);
				}
			}
		}
	}

	/**
	 * Import Tables from a multidimensional array
	 *
	 * @param array $tables
	 * @return void
	 */
	public function importTables($tables) {
		if(empty($tables)) {
			return;
		}
		foreach($tables as $table => $rows) {
			$this->log(sprintf(_('Importing Table %s from %s'),$table,$this->data['module']));
			$this->addDataToTableFromArray($table, $rows);
		}
	}
	/**
	 * Import KVStore from a multidimensional array
	 *
	 * @param array $store
	 * @return void
	 */
	public function importKVStore($store) {
		if(empty($store)) {
			return;
		}
		$module = ucfirst(strtolower($this->data['module']));
		if(!is_subclass_of($this->FreePBX->$module,'FreePBX\DB_Helper')) {
			$this->log(sprintf(_("%s does not implement KVStore"), $module),'WARNING');
			return;
		}
		$this->log(sprintf(_('Importing KVStore from %s'),$this->data['module']));
		foreach($store as $id => $kv) {
			$this->FreePBX->$module->setMultiConfig($kv, $id);
		}
	}

	/**
	 * Restore Legacy from All storage locations
	 *
	 * @param \PDO $pdo The pdo connection for the temporary database
	 * @return void
	 */
	public function restoreLegacyAll(\PDO $pdo) {
		$this->restoreLegacyDatabase($pdo);
		$this->restoreLegacyKvstore($pdo);
		$this->restoreLegacyFeatureCodes($pdo);
		$this->restoreLegacySettings($pdo);
	}

	/**
	 * Restores databases and kvstore based on present XML tables and backup KVStore
	 *
	 * @param \PDO $pdo The pdo connection for the temporary database
	 * @return void
	 */
	public function restoreLegacyDatabaseKvstore(\PDO $pdo) {
		$this->restoreLegacyDatabase($pdo);
		$this->restoreLegacyKvstore($pdo);
	}
	/**
	*  $modulename
	*   $under_score = true or false search with like modulename_
	*/
	public function getModuleTable_names($modename,$under_score=true) {
		if(!$under_score) {
			$query = "SELECT table_name FROM information_schema.tables WHERE table_name LIKE '".$modename."%'";
		} else {
			$query = "SELECT table_name FROM information_schema.tables WHERE table_name LIKE '".$modename."_%'";
		}
		$tables = $this->FreePBX->Database->query($query)->fetchAll(\PDO::FETCH_ASSOC);
		$ret = [];
		foreach($tables as $table) {
			$tname = (string)$table['table_name'];
			$ret[] = $tname;
		}
		return $ret;
	}

	/**
	 * Restores database based on present XML tables and backup database
	 *
	 * @param \PDO $pdo The pdo connection for the temporary database
	 * @return void
	 */
	public function restoreLegacyDatabase(\PDO $pdo,$tables = [],$ignoretables = []) {
		$module = strtolower($this->data['module']);
		$dir = $this->FreePBX->Config->get('AMPWEBROOT').'/admin/modules/'.$module;
		if(!file_exists($dir.'/module.xml')) {
			$this->log(sprintf(_('Unable to run restoreLegacyDatabase on %s because module.xml was not found'),$module),'WARNING');
			return;
		}
		$xml = simplexml_load_file($dir.'/module.xml');
		if(empty($xml->database)) {
			$this->log(sprintf(_(' %s found no database definitions in module.xml'),$module),'WARNING');
		}
		// add the missed the tables from FreePBX 15 database schema
		if(is_array($tables) && count($tables) == 0) {// tables are passed from module/Restore.php
			$this->log(sprintf(_("Reading Databases Table infromation using module name %s"), $module));
			$tables = $this->getModuleTable_names($module);
		}
		//compaire  with xml and add which are missed
		if(is_object($xml->database->table)) {
			foreach($xml->database->table as $table) {
				$tname = (string)$table->attributes()->name;
				if(array_key_exists($tname,$tables)) {
					continue;
				} else {
					$tables[] = $tname;
				}
			}
		}
		if(is_array($tables) && count($tables) == 0) {
			$this->log(sprintf(_('Unable to run restoreLegacyDatabase on %s because NO Database information provided'),$module),'WARNING');
			return;
		}

		$this->log(sprintf(_("Importing Databases from %s"), $module));
		foreach($tables as $table) {
			if(in_array($table, $ignoretables)) {
				continue;
			}
			$tname = $table;
			try {
				$sth = $pdo->query("SELECT * FROM $tname",\PDO::FETCH_ASSOC);
				$res = $sth->fetchAll();
				if(!empty($res)) {
					$this->log(sprintf(_("Importing table '%s' from legacy %s"),$tname, $module));
					$this->addDataToTableFromArray($tname, $res);
				} else {
					$this->log(sprintf(_("Table '%s' is empty from legacy %s, skipping"),$tname, $module), 'WARNING');
				}
			} catch(\Exception $e) {
				$this->log(sprintf(_("Table '%s' does not exist in legacy %s, skipping"),$tname, $module), 'WARNING');
			}
		}
	}

	/**
	 * Restore Legacy Feature Codes
	 *
	 * @param \PDO $pdo The pdo connection for the temporary database
	 * @return void
	 */
	public function restoreLegacyFeatureCodes(\PDO $pdo) {
		$helptextskip = false;
		if(version_compare_freepbx($this->getVersion(),"11","lt")) {
			$helptextskip = true;
		}
		$module = strtolower($this->data['module']);
		if($helptextskip) {
			$sql = "SELECT `featurename`,`description`, `defaultcode`, `customcode`, `enabled`, `providedest` FROM featurecodes WHERE modulename = :name";
		}else {
			$sql = "SELECT `featurename`,`description`, `defaultcode`, `customcode`, `enabled`,`helptext`,`providedest` FROM featurecodes WHERE modulename = :name";
		}
		$sth = $pdo->prepare($sql);
		$sth->execute([":name" => $module]);
		$res = $sth->fetchAll(\PDO::FETCH_ASSOC);

		$sql = "DELETE FROM `featurecodes` WHERE `modulename` = :modulename";
		$sth = $this->FreePBX->Database->prepare($sql);
		$sth->execute(array(":modulename" => $module));

		$sql = "INSERT INTO featurecodes (`modulename`, `featurename`, `description`, `helptext`, `defaultcode`, `customcode`, `enabled`, `providedest`) VALUES (:modulename, :featurename, :description, :helptext, :defaultcode, :customcode, :enabled, :providedest)";
		$sth = $this->FreePBX->Database->prepare($sql);
		foreach($res as $data) {
			if($helptextskip) {
				$data['helptext'] = '';
			}
			$sth->execute([
				":description" 	=> $data['description'],
				":helptext" 	=> $data['helptext'],
				":defaultcode" 	=> $data['defaultcode'],
				":customcode" 	=> $data['customcode'],
				":enabled" 	=> $data['enabled'],
				":featurename" 	=> $data['featurename'],
				":modulename" 	=> $module,
				":providedest"	=> $data['providedest']
			]);
		}
	}

	public function restoreLegacyAdvancedSettings(\PDO $pdo) {
		return $this->restoreLegacySettings($pdo);
	}

	/**
	 * Restore Legacy Advanced Settings
	 *
	 * @param \PDO $pdo The pdo connection for the temporary database
	 * @return void
	 */
	public function restoreLegacySettings(\PDO $pdo) {
		$module = strtolower($this->data['module']);
		$sql = "SELECT `keyword`, `value` FROM freepbx_settings WHERE module= :name";
		$sth = $pdo->prepare($sql);
		$sth->execute([":name" => $module]);
		$res = $sth->fetchAll(\PDO::FETCH_ASSOC);

		if(!empty($res)) {
			$this->log(sprintf(_("Importing Advanced Settings from %s"), $module));
			$sql = "UPDATE IGNORE freepbx_settings SET `value` = :value WHERE `keyword` = :keyword AND `module` = :module";
			$usth = $this->FreePBX->Database->prepare($sql);

			foreach($res as $data) {
				$usth->execute([
					":keyword" => $data['keyword'],
					":value" => $data['value'],
					":module" => $data['module']
				]);
			}
		}


	}

	/**
	 * Restores kvstore based on backup KVStore
	 *
	 * @param \PDO $pdo The pdo connection for the temporary database
	 * @return void
	 */
	public function restoreLegacyKvstore(\PDO $pdo) {
		$module = ucfirst(strtolower($this->data['module']));
		if(!is_subclass_of($this->FreePBX->$module,'FreePBX\DB_Helper')) {
			$this->log(sprintf(_("%s does not implement KVStore"), $module),'WARNING');
			return;
		}

		$data = $this->getLegacyKVStore($pdo);
		if(!empty($data)) {
			$this->importKVStore($data);
		}
	}

	/**
	 * Get Underscored FreePBX Module
	 *
	 * @param string $module The module rawname
	 * @return srring
	 */
	public function getNamespace() {
		$module = ucfirst(strtolower($this->data['module']));
		$namespace = get_class($this->FreePBX->$module);
		return $namespace;
	}

	/**
	 * Legacy import KVStore into memory
	 *
	 * @param \PDO $pdo The pdo connection for the temporary database
	 * @return array
	 */
	public function getLegacyKVStore(\PDO $pdo) {
		if(version_compare_freepbx($this->data['pbx_version'],"12","lt")) {
			return [];
		}

		try {
			if(version_compare_freepbx($this->data['pbx_version'],"14","lt")) {
				$module = ucfirst(strtolower($this->data['module']));
				$res = $pdo->query('SELECT `id`, `key`, `val`, `type` FROM kvstore WHERE `module` like '.$pdo->quote('%'.$module))->fetchAll(\PDO::FETCH_ASSOC);
			} else {
				$res = $pdo->query('SELECT `id`, `key`, `val`, `type` FROM kvstore_'.str_replace('\\','_',$this->getNamespace()))->fetchAll(\PDO::FETCH_ASSOC);
			}
		} catch(\Exception $e) {
			return [];
		}

		if(empty($res)) {
			return [];
		}

		$final = [];
		foreach($res as $r) {
			if($r['type'] === 'blob') {
				$b = $pdo->query('SELECT `type`, `content` FROM `kvblobstore` WHERE `uuid`='.$pdo->quote($r['val']))->fetch(\PDO::FETCH_ASSOC);
				if(empty($b)) {
					continue;
				}
				$r['type'] = $b['type'];
				$r['val'] = $b['content'];
			}
			switch($r['type']) {
				case 'json-obj':
					$val = json_decode(stripcslashes($r['val']));
				break;
				case 'json-arr':
					$val = json_decode(stripcslashes($r['val']), true);
				break;
				default:
					$val = $r['val'];
				break;
			}
			$final[$r['id']][$r['key']] = $val;
		}
		return $final;
	}

	/**
	 * Legacy import KVStore into memory using kvstore ids
	 *
	 * @param \PDO $pdo The pdo connection for the temporary database
	 * @return array
	 */
	public function getLegacyKVStoreByIds(\PDO $pdo,$ids = []) {
		if(version_compare_freepbx($this->data['pbx_version'],"12","lt")) {
			return [];
		}
		if(!is_array($ids) || empty($ids)){
			return [];
		}
		$idsearch =  implode("','",$ids);
		$idsearch =  "'".$idsearch."'";
		try {
			if(version_compare_freepbx($this->data['pbx_version'],"14","lt")) {
				$res = $pdo->query("SELECT `id`, `key`, `val`, `type` FROM kvstore WHERE `module` = ".$pdo->quote($this->getNamespace())." WHERE `id` IN ($idsearch)")->fetchAll(\PDO::FETCH_ASSOC);
			} else {
				$res = $pdo->query("SELECT `id`, `key`, `val`, `type` FROM kvstore_".str_replace('\\','_',$this->getNamespace())." WHERE `id` IN ($idsearch)")->fetchAll(\PDO::FETCH_ASSOC);
			}
		} catch(\Exception $e) {
			return [];
		}

		if(empty($res)) {
			return [];
		}

		$final = [];

		foreach($res as $r) {
			if($r['type'] === 'blob') {
				$b = $pdo->query('SELECT `type`, `content` FROM `kvblobstore` WHERE `uuid`='.$pdo->quote($r['val']))->fetch(\PDO::FETCH_ASSOC);
				if(empty($b)) {
					continue;
				}
				$r['type'] = $b['type'];
				$r['val'] = $b['content'];
			}
			switch($r['type']) {
				case 'json-obj':
					$val = json_decode(stripcslashes($r['val']));
				break;
				case 'json-arr':
					$val = json_decode(stripcslashes($r['val']), true);
				break;
				default:
					$val = $r['val'];
				break;
			}
			$final[$r['id']][$r['key']] = $val;
		}
		return $final;
	}

	/**
	 * Dynamically add data from an array to a table
	 *
	 * This uses doctrine to see the column names and types to match them up
	 * and quote them correctly, if any columns are missing then a warning is displayed
	 *
	 * @param string $table The table name
	 * @param array $data The data to import
	 * @param boolean $delete If set to true then delete everything from the table before inserting
	 * @return void
	 */
	public function addDataToTableFromArray($table, $data, $delete = true) {
		$dc = $this->FreePBX->Database->getDoctrineConnection();
		$sm = $dc->getSchemaManager();

		$columns = [];
		//list columns and set the binding type (used for quoting)
		foreach($sm->listTableColumns($table) as $c) {
			$columns[$c->getName()] = $c->getType()->getBindingType();
		}

		if($delete) {
			$this->log("Cleaning table: $table");
			$this->FreePBX->Database->query("SET FOREIGN_KEY_CHECKS=0");
			$this->FreePBX->Database->query("TRUNCATE TABLE $table");
			$this->FreePBX->Database->query("SET FOREIGN_KEY_CHECKS=1");
		}

		foreach($data as $row) {
			//Filter out missing columns
			$row = array_filter($row, function($key) use($columns){
				if(!isset($columns[$key])) {
					$this->log(sprintf(_("Column '%s' does not exist in %s, skipping"), $key, $table),'WARNING');
					return false;
				}
				return true;
			}, ARRAY_FILTER_USE_KEY);

			//Correctly quote before inserting
			$final = [];
			foreach($row as $col => $data) {
				$find_chr		= array('\n', '\"', "\'" );
				$replace_chr	= array("\n", '"' , "'" );
				$final['`'.$col.'`'] = is_null($data) ? $data : str_replace($find_chr, $replace_chr, $data);
			}

			try {
				$dc->insert($table, $final);
			} catch(\Exception $e) {
				$this->log($e->getMessage(),'ERROR');
				continue;
			}

		}
	}

	/**
	 * Restore specific tables dump from file
	 *
	 * @param String $tableName
	 * @param String $tmppath
	 * @param Object $files
	 * @return Boolean
	 */
	public function restoreDataFromDump($tableName, $tmppath, $files)
	{
		if (empty($files[0])) {
			return false;
		}

		$dump = $files[0];
		$dumpfile = $tmppath . '/files/' . ltrim($dump->getPathTo(), '/') . '/' . $dump->getFilename();
		if (!file_exists($dumpfile)) {
			return;
		}

		global $amp_conf;

		$dbhost = $amp_conf['AMPDBHOST'];
		$dbuser = $amp_conf['AMPDBUSER'];
		$dbport = $amp_conf['AMPDBPORT'];
		$dbpass = $amp_conf['AMPDBPASS'];
		$dbname = $amp_conf['AMPDBNAME'];

		$cdrDbTables = ['cdr', 'cel', 'queuelog'];
		if (in_array(strtolower($tableName), $cdrDbTables)) {
			$dbhost = $this->FreePBX->Config->get('CDRDBHOST') ? $this->FreePBX->Config->get('CDRDBHOST') : $amp_conf['AMPDBHOST'];
			$dbuser = $this->FreePBX->Config->get('CDRDBUSER') ? $this->FreePBX->Config->get('CDRDBUSER') : $amp_conf['AMPDBUSER'];
			$dbport = $this->FreePBX->Config->get('CDRDBPORT') ? $this->FreePBX->Config->get('CDRDBPORT') : $amp_conf['AMPDBPORT'];
			$dbpass = $this->FreePBX->Config->get('CDRDBPASS') ? $this->FreePBX->Config->get('CDRDBPASS') : $amp_conf['AMPDBPASS'];
			$dbname = $this->FreePBX->Config->get('CDRDBNAME') ? $this->FreePBX->Config->get('CDRDBNAME') : 'asteriskcdrdb';
		}

		$mysql = fpbx_which('mysql');

		$dbhost = ($dbhost === 'localhost' || $dbhost === '127.0.0.1') ? '' : '-h ' . $dbhost;
		$dbport = empty(trim($dbport)) ? '' : '-P ' . trim($dbport);

		if (strpos($dumpfile, '.gz') !== false) {
			$restore = "gunzip < " . $dumpfile . " | " . "{$mysql} {$dbport} {$dbhost} -u{$dbuser} -p{$dbpass} {$dbname}";
		} else {
			$restore = "{$mysql} {$dbport} {$dbhost} -u{$dbuser} -p{$dbpass} {$dbname} < {$dumpfile}";
		}
		
		$this->log(sprintf(_("Started restoring mysql dumps of : %s"), $tableName));

		try {
			$process = new Process($restore);
			$process->setTimeout(3600);
			$process->disableOutput();
			$process->mustRun();
		} catch (ProcessFailedException $e) {
			$this->log(sprintf(_("%s table Restore Error %s "), $tableName, $e->getMessage()), 'ERROR');
			return false;
		}

		$this->log(sprintf(_("Completed restoring mysql dumps of : %s"), $tableName));
		return true;
	}
}
