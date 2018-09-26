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
    $oldkv = NULL;
    $module = ucfirst($module);
    $kvsql = "SELECT * FROM kvstore WHERE `module` = :module";
    try {
      $stmt = $pdo->prepare($kvsql);
      $stmt->execute([':module' => $module]);
      $oldkv = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
      if ($e->getCode() != '42S02') {
        throw $e;
      }
    }
    (!isset($oldkv) || !is_array($oldkv)) ? : $oldkv = [];
    $this->insertKV($freepbx, $module, $oldkv);
    return $this;
  }

  public function transformNamespacedKV($pdo, $module, $freepbx){
    $module = ucfirst($module);
    $newkvsql = "SELECT * FROM " . $this->getUnderscoreClass($freepbx, $module);
    try {
      $stmt = $pdo->prepare($newkvsql);
      $stmt->execute();
      $newkv = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
      if ($e->getCode() != '42S02') {
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
    if (!is_null($data) ) {
      foreach ($data as $entry) {
        if ($entry['type'] === 'json-arr') {
          $entry['val'] = json_decode($entry['val'], true);
        }
        $freepbx->$module->setConfig($entry['key'], $entry['val'], $entry['id']);
      }
    }
    return $this;
  }

	public function addDataDB($data){
		foreach( $data['tables'] as $table){
			$loadedTables = $data['pdo']->query("SELECT * FROM $table");
			$results = $loadedTables->fetchAll(\PDO::FETCH_ASSOC);
			foreach ($results as $key => $value) {
			  $truncate = "TRUNCATE TABLE $table";
			  $this->FreePBX->Database->query($truncate);
			  $first = $results[0];
			  $params = $columns = array_keys($first);
			  array_walk($params, function(&$v, $k) {
				$v = ':'.preg_replace("/[^a-z0-9]/i", "", $v);
			  });
			  $sql = "INSERT INTO `$table` (`".implode('`,`',$columns)."`) VALUES (".implode(',',$params).")";
			  $sth = $this->FreePBX->Database->prepare($sql);
			  foreach($results as $row) {
				$insertable = [];
				foreach($row as $k => $v) {
				  $k = preg_replace("/[^a-z0-9]/i", "", $k);
				  $insertable[':'.$k] = $v;
				}
				$sth->execute($insertable);
			  }
			}
		  }
	}
}
