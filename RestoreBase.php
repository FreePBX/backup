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

  public function getUnderscoreClass($freepbx,$module){
    $module = ucfirst($module);
    $namespace = get_class($freepbx->$module);
    return str_replace('\\','_',$namespace);
  }

  public function transformLegacyKV($pdo, $module, $freepbx){
    $module = ucfirst($module);
    $kvsql = "SELECT * FROM kvstore WHERE module = :module";
    try {
      $oldkv = $pdo->prepare($kvsql)
        ->execute(['module' => $module])
        ->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
      if ($e->getCode != '42S02') {
        throw $e;
      }
    }
    (!isset($oldkv) || !is_array($oldkv)) ? : $oldkv = [];
    $this->insertKV($freepbx, $module, $oldkv);
    return $this;
  }

  public function transformNameSpacedKV($pdo, $module, $freepbx){
    $module = ucfirst($module);
    $newkvsql = "SELECT * FROM :table";
    try {
      $newkv = $pdo->prepare($newkvsql)
        ->execute([':table' => $this->getUnderscoreClass($freepbx, $module)])
        ->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
      if ($e->getCode != '42S02') {
        throw $e;
      }
    }
    (!isset($newkv) || !is_array($newkv)) ? : $newkv = [];
    $this->insertKV($freepbx, $module, $newkv);
    return $this;
  }

  public function insertKV($freepbx, $module, $data){
    $module = ucfirst($module);
    if ($freepbx->Modules->checkStatus(strtolower($module))) {
      return $this;
    }
    foreach ($data as $entry) {
      if ($entry['type'] === 'json-arr') {
        $entry['val'] = json_decode($entry['val'], true);
      }
      $freepbx->$module->setConfig($entry['key'], $entry['val'], $entry['id']);
    }
    return $this;
  }
}
