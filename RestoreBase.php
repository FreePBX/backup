<?php
namespace FreePBX\modules\Backup;
use PDO;
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
		$module = ucfirst(strtolower($this->data['module']));
		$sql = "UPDATE IGNORE featurecodes SET `customcode` = :customcode, `enabled` = :enabled WHERE `featurename` = :featurename AND `modulename` = :modulename";
		$sth = $this->FreePBX->Database->prepare($sql);
		foreach($codes as $key => $data) {
			$sth->execute([
				":customcode" => $data['customcode'],
				":enabled" => $data['enabled'],
				":featurename" => $key,
				":modulename" => strtolower($this->data['module'])
			]);
		}
	}

	/**
	 * Import Asterisk Database from a multidimensional array
	 *
	 * @param array $families
	 * @return void
	 */
	public function importAstDB($families) {
		if(empty($families)) {
			return;
		}
		foreach($families as $family => $children) {
			$this->log(sprintf(_('Importing AstDB family %s from %s'),$family,$this->data['module']));
			foreach($children as $key => $val) {
				$this->FreePBX->astman->database_put($family,$key,$val);
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
	 * Restores database based on present XML tables and backup database
	 *
	 * @param \PDO $pdo The pdo connection for the temporary database
	 * @return void
	 */
	public function restoreLegacyDatabase(\PDO $pdo) {
		$module = strtolower($this->data['module']);
		$dir = $this->FreePBX->Config->get('AMPWEBROOT').'/admin/modules/'.$module;
		if(!file_exists($dir.'/module.xml')) {
			$this->log(sprintf(_('Unable to run restoreLegacyDatabase on %s because module.xml was not found'),$module),'WARNING');
			return;
		}
		$xml = simplexml_load_file($dir.'/module.xml');
		if(empty($xml->database)) {
			$this->log(sprintf(_('Unable to run restoreLegacyDatabase on %s because there are no database definitions in module.xml'),$module),'WARNING');
			return;
		}

		$this->log(sprintf(_("Importing Databases from %s"), $module));
		$tables = [];
		foreach($xml->database->table as $table) {
			$tname = (string)$table->attributes()->name;
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
		$module = strtolower($this->data['module']);
		$sql = "SELECT `featurename`, `customcode`, `enabled` FROM featurecodes WHERE modulename = :name";
		$sth = $pdo->prepare($sql);
		$sth->execute([":name" => $module]);
		$res = $sth->fetchAll(\PDO::FETCH_ASSOC);

		$sql = "UPDATE IGNORE featurecodes SET `customcode` = :customcode, `enabled` = :enabled WHERE `featurename` = :featurename AND `modulename` = :modulename";
		$usth = $this->FreePBX->Database->prepare($sql);

		foreach($res as $data) {
			$usth->execute([
				":customcode" => $data['customcode'],
				":enabled" => $data['enabled'],
				":featurename" => $data['featurename'],
				":modulename" => $module
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
				$res = $pdo->query('SELECT `id`, `key`, `val`, `type` FROM kvstore WHERE `module` = '.$pdo->quote($this->getNamespace()))->fetchAll(\PDO::FETCH_ASSOC);
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
				$r['val'] = $b['val'];
			}
			switch($r['type']) {
				case 'json-obj':
					$val = json_decode($val);
				break;
				case 'json-arr':
					$val = json_decode($val, true);
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
			$this->FreePBX->Database->query("DELETE FROM $table");
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
				$final['`'.$col.'`'] = $data;
			}

			try {
				$dc->insert($table, $final);
			} catch(\Exception $e) {
				$this->log($e->getMessage(),'ERROR');
				continue;
			}

		}
	}
}
