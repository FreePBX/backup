<?php
namespace FreePBX\modules\Backup;
use FreePBX\modules\Backup\Models as Model;
/**
 * This is a base class used when creating your modules "Backup.php" class
 */
class BackupBase extends Model\Backup{
  public function addFile($filename,$path,$base,$type = "file"){
    parent::addFiles([['type' => $type, 'filename' => $filename, 'pathto' => $path,'base' => $base]]);
  }
}