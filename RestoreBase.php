<?php
namespace FreePBX\modules\Backup;
use PDO;
use Exception;
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
  public function getDirs(){
    return $this->backupObj->getDirs();
  }
  public function addDirectories($directories = []){
    $this->backupObj->addDirs($directories);
  }
  public function getFiles(){
    return $this->backupObj->getFiles();
  }
  
  public function getAMPConf($database){
    $sql = "select keyword, value from freepbx_settings";
    try{
      return $database->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e){
      return [];
    }
  }

  public function getAstDb($path){
    if(!file_exists($path)){
      return [];
    }
    $data = @unserialize(file_get_contents($path));
    if($data === false){
      return [];
    }
    return $data;
  }
}
