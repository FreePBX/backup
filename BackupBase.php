<?php
namespace FreePBX\modules\Backup;
use FreePBX\modules\Backup\Models as Model;
/**
 * This is a base class used when creating your modules "Backup.php" class
 */
class BackupBase extends Model\Backup{

	/**
	 * Dump all known databases from said module
	 *
	 * @return array
	 */
	public function dumpTables() {
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

		$this->log(sprintf(_("Exporting Databases from %s"), $module));
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
		if(!is_subclass_of($this->FreePBX->$module,'FreePBX\DB_Helper')) {
			$this->log(sprintf(_("%s does not implement KVStore"), $module),'WARNING');
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