<?php
namespace FreePBX\modules\Backup;
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
	 * @return array
	 */
	public function dumpAll() {
		return [
			"settings" => $this->dumpAdvancedSettings(),
			"features" => $this->dumpFeatureCodes(),
			"tables" => $this->dumpTables(),
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
		$sql = "SELECT `featurename`, `customcode`, `enabled` FROM featurecodes WHERE modulename = :name";
		$sth = $this->FreePBX->Database->prepare($sql);
		$sth->execute([":name" => $module]);
		return $sth->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_ASSOC|\PDO::FETCH_UNIQUE);
	}

	/**
	 * Dump all known databases from said module
	 *
	 * @return array
	 */
	public function dumpTables() {
		$module = strtolower($this->data['module']);
		$this->log(sprintf(_("Exporting Databases from %s"), $module));
		$dir = $this->FreePBX->Config->get('AMPWEBROOT').'/admin/modules/'.$module;
		if(!file_exists($dir.'/module.xml')) {
			return [];
		}
		$xml = simplexml_load_file($dir.'/module.xml');
		if(empty($xml->database)) {
			return [];
		}

		$tables = [];
		foreach($xml->database->table as $table) {
			$tname = (string)$table->attributes()->name;
			$tables[$tname] = $this->FreePBX->Database->query("SELECT * FROM $tname")->fetchAll(\PDO::FETCH_ASSOC);
		}
		return $tables;
	}
	/**
	 * Dump KVStore to a multidimensional array
	 *
	 * @return array
	 */
	public function dumpKVStore() {
		$module = ucfirst(strtolower($this->data['module']));
		$this->log(sprintf(_("Exporting KVStore from %s"), $module));
		if(!is_subclass_of($this->FreePBX->$module,'FreePBX\DB_Helper')) {
			return [];
		}

		$ids = $this->FreePBX->$module->getAllids();
		$ids[] = 'noid';
		$final = [];
		foreach($ids as $id) {
			$final[$id] = $this->FreePBX->$module->getAll($id);
		}
		return $final;
	}

	/**
	 * Add Single Sile to Files List
	 *
	 * @param string $filename The file name
	 * @param string $path The Path to the file
	 * @param string $base Base Directory to extract to
	 * @param string $type The file Type
	 * @return void
	 */
	public function addFile($filename,$path,$base,$type = "file"){
		parent::addFiles([['type' => $type, 'filename' => $filename, 'pathto' => $path,'base' => $base]]);
	}

	/**
	 * Utilizes SplFileInfo to add a file
	 *
	 * @param \SplFileInfo $file
	 * @return void
	 */
	public function addSplFile(\SplFileInfo $file){
		parent::addFiles([['type' => $file->getExtension(), 'filename' => $file->getBasename(), 'pathto' => $file->getPath(),'base' => '']]);
	}
}