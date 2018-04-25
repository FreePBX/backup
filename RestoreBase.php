<?php
namespace FreePBX\modules\Backup;
/**
 * This is a base class used when creating your modules "Restore.php" class
 */
class RestoreBase{

  public function __construct($backupobj=null,$freepbx,$tmpdir){
    $this->backupObj = $backupobj;
    $this->FreePBX = $freepbx;
    $this->tmpdir = $tmpdir;
  }
  public function getDependencies(){
    return $this->backupObj->getDependencies();
  }
  public function getConfigs(){
    return $this->backupObj->getConfigs();
  }
  public function addDirectories($directories = []){
    $this->backupObj->addDirs($directories);
  }
  public function getFiles(){
    return $this->backupObj->getFiles();
  }
}