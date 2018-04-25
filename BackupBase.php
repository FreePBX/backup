<?php
namespace FreePBX\modules\Backup;
/**
 * This is a base class used when creating your modules "Backup.php" class
 */
class BackupBase{
   
  public function __construct($backupobj=null,$freepbx = null){
    if(empty($freepbx) || empty($freepbx)){
        throw new \InvalidArgumentException("The module expects to recieve a backup object and a FreePBX object");
    }
    $this->backupObj = $backupobj;
    $this->FreePBX = $freepbx;
  }
  public function addDependency($dependency){
    $this->backupObj->addDependency($dependency);
  }
  public function addConfigs($configs){
    $this->backupObj->addConfigs($configs);
  }
  public function addDirectories($directories = []){
    $this->backupObj->addDirs($directories);
  }
  public function addFile($filename,$path,$base,$type = "file"){
    $this->backupObj->addFiles([['type' => $type, 'filename' => $filename, 'pathto' => $path,'base' => $base]]);
  }
}