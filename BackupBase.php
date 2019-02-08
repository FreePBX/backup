<?php
namespace FreePBX\modules\Backup;
use FreePBX\modules\Backup\Models as Model;
/**
 * This is a base class used when creating your modules "Backup.php" class
 */
class BackupBase extends Model\Backup{
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