<?php
namespace FreePBX\modules\Backup;
use PDO;
use Exception;
/**
 * This is a base class used when creating your modules "Restore.php" class
 */
class RestoreBase extends \FreePBX\modules\Backup\Models\Restore{

	/**
	 * Import Asterisk Database from a multidimensional array
	 *
	 * @param array $families
	 * @return void
	 */
	public function importAstDB($families) {
		foreach($families as $family => $children) {
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
		foreach($tables as $table => $rows) {
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
		$module = ucfirst(strtolower($this->data['module']));
		if(!is_subclass_of($this->FreePBX->$module,'FreePBX\DB_Helper')) {
			$this->log(sprintf(_("%s does not implement KVStore"), $module),'WARNING');
			return;
		}
		foreach($store as $id => $kv) {
			$this->FreePBX->$module->setMultiConfig($kv, $id);
		}
	}

	/**
	 * Restores databases and kvstore based on present XML tables and backup KVStore
	 *
	 * @param \PDO $pdo remote PDO object
	 * @return void
	 */
	public function restoreLegacyDatabaseKvstore(\PDO $pdo) {
		$this->restoreLegacyDatabase($pdo);
		$this->restoreLegacyKvstore($pdo);
	}

	/**
	 * Restores database based on present XML tables and backup database
	 *
	 * @param \PDO $pdo
	 * @return void
	 */
	public function restoreLegacyDatabase(\PDO $pdo) {
		$module = strtolower($this->data['module']);
		$dir = $this->FreePBX->Config->get('AMPWEBROOT').'/admin/modules/'.$module;
		if(!file_exists($dir.'/module.xml')) {
			$this->log(sprintf(_('Unable to run restoreBaseLegacy on %s because module.xml was not found'),$module),'WARNING');
			return;
		}
		$xml = simplexml_load_file($dir.'/module.xml');
		if(empty($xml->database)) {
			$this->log(sprintf(_('Unable to run restoreBaseLegacy on %s because there are no database definitions in module.xml. Perhaps you want to use restoreLegacyKvstore instead'),$module),'WARNING');
			return;
		}

		$this->log(sprintf(_("Importing Databases from %s"), $module));
		$tables = [];
		foreach($xml->database->table as $table) {
			$tname = (string)$table->attributes()->name;
			$sth = $pdo->query("SELECT * FROM $tname",\PDO::FETCH_ASSOC);
			$res = $sth->fetchAll();
			$this->log(sprintf(_("Importing table '%s' from legacy %s"),$tname, $module));
			$this->addDataToTableFromArray($tname, $res);
		}
	}

	/**
	 * Restores kvstore based on backup KVStore
	 *
	 * @param \PDO $pdo remote PDO object
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
			$this->log(sprintf(_("Importing KVStore from %s"), strtolower($this->data['module'])));
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
	 * @param \PDO $pdo The remote PDO connection (Not our local one)
	 * @return array
	 */
	public function getLegacyKVStore(\PDO $pdo) {
		if(version_compare_freepbx($this->data['pbx_version'],"12","lt")) {
			return [];
		}
		if(version_compare_freepbx($this->data['pbx_version'],"14","lt")) {
			$res = $pdo->query('SELECT `id`, `key`, `val`, `type` FROM kvstore WHERE `module` = '.$pdo->quote($this->getNamespace()))->fetchAll(\PDO::FETCH_ASSOC);
		} else {
			$res = $pdo->query('SELECT `id`, `key`, `val`, `type` FROM kvstore_'.str_replace('\\','_',$this->getNamespace()))->fetchAll(\PDO::FETCH_ASSOC);
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
