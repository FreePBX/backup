<?php
namespace FreePBX\modules\Backup\Modules\Migration;
use PDO;
use FreePBX\modules\Backup\Handlers\FreePBXModule;
class Backupjobs extends Migration{
	public $backupJobs = [];
	public $moduleData = [];
	public function process(){
		$this->moduleManager = new FreePBXModule($this->FreePBX);
		return $this->getLegacyBackups()
			 ->migrate();

	}
	public function getLegacyBackups(){
		$sql = 'SELECT * FROM backup ORDER BY name'; 
		$backups = $this->Database->query($sql)->fetchAll(PDO::FETCH_ASSOC);
		$sql = 'SELECT * FROM backup_details';
		$backupDetails = $this->Database->query($sql)->fetchAll(PDO::FETCH_ASSOC);
		$sql = 'SELECT * FROM backup_items';
		$backupItems = $this->Database->query($sql)->fetchAll(PDO::FETCH_ASSOC); 
		$final = [];
		foreach($backups as $job){
			$final['bu_'. $job['id']]['uuid'] = $this->Backup->generateId();
			foreach(unserialize($job['data']) as $key => $val){
				$final['bu_' . $job['id']]['data'][$key] = $val;				
			}
		}
		foreach($backupDetails as $setting){
			$final['bu_' . $setting['backup_id']]['data'][$setting['key']] = $setting['value'];				
		}
		foreach($backupItems as $item){
			$item['excude'] = unserialize($item['exclude']);
			$final['bu_' . $item['backup_id']]['items'][] = $item; 
		}
		$this->Backup->setMultiConfig($final, 'migratedbackups');
		$this->backupJobs = $final;
		return $this;
	}
	public function migrate(){
		$this->buildModuleData();
		foreach($this->Backupjobs as $backup){
			$backupModules = $this->moduleData;
			foreach($backup['items']['exclude'] as $exclude){
				if(isset($this->moduleData['tables'][$exclude])){
					unset($backupModules[$this->moduleData['tables'][$exclude]]);
				}
			}
			$this->Backup->setModulesById($backup['uuid'],$backupModules);
		}
		foreach($backup['data'] as $key => $val){
			if($key === 'desciption'){
				$key = 'backup_description';
			}
			if($key === 'name'){
				$key = 'backup_name';
			}
			if($key === 'email'){
				$key = 'backup_email';
			}
			$this->Backup->updateBackupSetting($backup['uuid'], $key, $val);
		}
	}
	public function buildModuleData(){
		if(!empty($this->moduleData)){
			return $this;
		}
		$amodules = array_keys($this->FreePBX->Modules->getActiveModules());
		$this->moduleData['modules'] = $amodules;
		foreach($amodules as $mod){
			$modTables = $this->moduleManager->getTables($mod);
			foreach($modTables as $table){
				$this->moduleData['tables'][$table] = $mod;
			}
		}
		return $this;
	}
}
