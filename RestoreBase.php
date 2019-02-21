<?php
namespace FreePBX\modules\Backup;
use PDO;
use Exception;
/**
 * This is a base class used when creating your modules "Restore.php" class
 */
class RestoreBase extends \FreePBX\modules\Backup\Models\Restore{

	/**
	 * Restores databases and kvstore based on present XML tables and backup KVStore
	 *
	 * @param \PDO $pdo remote PDO object
	 * @return void
	 */
	public function restoreLegacyDatabaseKvstore(\PDO $pdo) {
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
			$this->log(sprintf(_("Importing %s from %s"),$tname, $module));
			$this->addDataToTableFromArray($tname, $res);
		}

		$this->restoreLegacyKvstore($pdo);
	}

	/**
	 * Restores kvstore based on backup KVStore
	 *
	 * @param \PDO $pdo remote PDO object
	 * @return void
	 */
	public function restoreLegacyKvstore(\PDO $pdo) {
		$data = $this->getLegacyKVStore($pdo);
		if(!empty($data)) {
			$this->log(sprintf(_("Importing KVStore from %s"), strtolower($this->data['module'])));
			$module = ucfirst(strtolower($this->data['module']));
			foreach($data as $id => $kv) {
				$this->FreePBX->$module->setMultiConfig($kv, $id);
			}
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
		foreach($sm->listTableColumns($table) as $c) {
			$columns[$c->getName()] = $c->getType()->getBindingType();
		}

		if($delete) {
			$this->FreePBX->Database->query("DELETE FROM $table");
		}

		foreach($data as $row) {
			$row = array_filter($row, function($key) use($columns){
				if(!isset($columns[$key])) {
					$this->log(sprintf(_('Column %s does not exist in %s, skipping'), $key, $table),'WARNING');
					return false;
				}
				return true;
			}, ARRAY_FILTER_USE_KEY);

			foreach($row as $col => &$data) {
				$data = $dc->quote($data, $columns[$col]);
			}

			try {
				$dc->insert($table, $row);
			} catch(\Exception $e) {
				$this->log($e->getMessage(),'ERROR');
				continue;
			}

		}
	}
}
