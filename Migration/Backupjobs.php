<?php
namespace FreePBX\modules\Backup\Migration;
use PDO;
class Backupjobs extends Common{
	public $backupJobs = [];
	public $moduleData = [];
	private $storageMapping = [];

	public function __construct($freepbx = ''){
		$this->freepbx = $freepbx;
		$this->Database = $freepbx->Database;
		$this->Backup = $freepbx->Backup;
	}

	public function process($mapping=[]){
		$this->storageMapping = $mapping;
		return $this->getLegacyBackups()->migrate();

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
			$final['bu_' . $job['id']] = $job;
			$final['bu_'. $job['id']]['uuid'] = $this->Backup->generateId();
			$final['bu_' . $job['id']]['data'] = unserialize($job['data']);
		}
		$final['bu_' . $job['backup_id']]['data']['storage_servers'] = [];
		foreach($backupDetails as $setting){
			if($setting['key'] == 'storage_servers'){
				$final['bu_' . $setting['backup_id']]['data']['storage_servers'][] = $setting['value'];
				continue;
			}
			$final['bu_' . $setting['backup_id']]['data'][$setting['key']] = $setting['value'];
		}
		foreach($backupItems as $item){
			$item['exclude'] = unserialize($item['exclude']);
			$final['bu_' . $item['backup_id']]['items'][] = $item;
		}
		foreach($final  as $key=>$backup){
			$this->Backup->setConfig($key,$backup, 'migratedbackups');
		}
		$this->backupJobs = $final;
		return $this;
	}
	public function migrate($mapping=[]){
		$this->buildModuleData();
		//$migrated = $this->Backup->getAll('migrationcompleted');
		$migrated = !$migrated?[]:array_column($migrated,'id');
		foreach($this->backupJobs as $key => $backup){
			if(!isset($backup['id']) && in_array($backup['id'], $migrated)){
				continue;
			}
			$backupModules = $this->moduleData;
			$backup['items']['exclude'] = is_array($backup['items']['exclude']) ? $backup['items']['exclude'] : [];
			$storage = [];
			foreach ($backup['items']['exclude'] as $exclude) {
				if (isset($this->moduleData['tables'][$exclude])) {
					unset($backupModules['modules'][$this->moduleData['tables'][$exclude]]);
				}
			}
			$items = [];
			foreach ($backupModules['modules'] as $key => $value) {
				$items[] = ['modulename' => ucfirst($key), 'selected' => true];
			}
			$this->Backup->setModulesById($backup['uuid'],$items);

			$backup['data']['storage_servers']  = is_array($backup['data']['storage_servers'])?$backup['data']['storage_servers']:[$backup['data']['storage_servers']];

			foreach($backup['data']['storage_servers'] as $server){
				$lookup = $this->getStorageId($server);
				if(empty($lookup)){
					//$this->freepbx->Logger->getDriver('default')->debug("couldn't find a migration path for server $server");
					continue;
				}
				$storage[] = $lookup;
			}

			if($backup['uuid']){
				$this->Backup->setConfig($backup['uuid'], array('id' => $backup['uuid'], 'name' => $backup['name'], 'description' => $backup['description']), 'backupList');
				$this->Backup->setConfig('backup_name', $backup['name'],$backup['uuid']);
				$this->Backup->setConfig('backup_description', $backup['description'],$backup['uuid']);
				$this->Backup->setConfig('backup_email', $backup['email'],$backup['uuid']);
				$this->Backup->setConfig('backup_emailinline', 'no');
				$this->Backup->setConfig('backup_addbjname', 'no');
				foreach($backup['data'] as $key => $value){
					if($key === 'name'){
						$key = 'backup_name';
					}
					if($key === 'email'){
						$key = 'backup_email';
					}
					if($key === 'storage_servers'){
						continue;
					}
					if($key === 'delete_time_type'){
						continue;
					}
					if(substr($key,0,4) === 'cron'){
						continue;
					}

					if($key === 'emailfailonly'){
						$key = 'backup_emailtype';
						if($value){
							$value = 'failure';
						}
						if(!$value){
							$value = 'both';
						}
					}
					if($key === 'delete_time'){
						$timeType = $backup['data']['delete_time_type'];
						$value = (int)$value;
						switch($timeType) {
							case 'minutes':
								$value = $value * 0.000694444;
							break;
							case 'hours':
								$value = $value * 0.0416667;
							break;
							case 'weeks':
								$value = $value * 7;
							break;
							case 'months':
								$value = $value * 30.4167;
							break;
							case 'years':
								$value = $value * 365;
							break;
						}
						$key = 'maintage';
						if ($value === 0) {
							continue;
						}
						if ($value < 8){
							$newvalue = 7;
						}
						if($value > 7 && $value < 15){
							$newvalue = 14;
						}
						if($value > 16 && $value < 22){
							$newvalue = 21;
						}
						if($value > 21 && $value < 31){
							$newvalue = 30;
						}
						if($value > 30 && $value < 91){
							$newvalue = 90;
						}
						if($value > 90 && $value < 121){
							$newvalue = 120;
						}
						if($value > 120 && $value < 241){
							$newvalue = 240;
						}
						if($value > 240){
							$newvalue = 365;
						}
						$value = $newvalue;
					}
					if($key === 'delete_amount'){
						$key = 'maintruns';
					}
					if($key === 'bu_server'){
						$storageId = $this->getStorageId($value);
						$storageItem = $this->freepbx->Filestore->getItemById($storageId);
						if(!empty($storageItem)){
							$this->Backup->updateBackupSetting($backup['uuid'], 'warmspare_remoteip', $storageItem['host']);
							$this->Backup->updateBackupSetting($backup['uuid'], 'warmspare_user', $storageItem['user']);
						}
						continue;
					}
					if($key === 'applyconfigs'){
						$key = 'warmspare_remoteapply';
						$value = !empty($value)?'yes':'no';
					}
					if($key === 'skipdns'){
						$key = 'warmspare_remotedns';
						$value = !empty($value)?'yes':'no';
					}
					if($key === 'skipbind'){
						$key = 'warmspare_remotebind';
						$value = !empty($value)?'yes':'no';
					}
					if($key === 'skipnat'){
						$key = 'warmspare_remotenat';
						$value = !empty($value)?'yes':'no';
					}
					if($key === 'disabletrunks'){
						$key = 'warmspare_remotetrunks';
						$value = !empty($value)?'yes':'no';
					}
					if($key === 'restore'){
						$key = 'warmspareenable';
						$value = !empty($value)?'yes':'no';
					}
					$this->Backup->updateBackupSetting($backup['uuid'], $key, $value);
				}
				if(isset($backup['data']['cron_schedule'])){
					switch ($backup['data']['cron_schedule']) {
						case 'hourly':
							$cronjob = sprintf('* %s * * *', rand(0,23));
							break;
						case 'daily':
							$cronjob = sprintf('0 %s * * *', rand(0, 23));
							break;
						case 'monthly':
							$cronjob = sprintf('%s %s %s * *', rand(0, 59),rand(0,23),rand(1,28));
							break;
						case 'weekly':
							$cronjob = sprintf('%s %s * * %s', rand(0, 59),rand(0,23),rand(1,7));
							break;
						case 'annually':
							$cronjob = sprintf('%s %s %s %s *', rand(0, 59), rand(0, 23), rand(1, 28),rand(1,12));
							break;
						case 'reboot':
							$cronjob = sprintf('* * * * *', rand(0, 23));
							break;
						case 'custom':
							$minute = isset($backup['data']['cron_minute']) && strlen($backup['data']['cron_minute'] > 0) ? $backup['data']['cron_minute'] : '*';
							$dom = isset($backup['data']['cron_dom']) && strlen($backup['data']['cron_dom'] > 0) ? $backup['data']['cron_dom'] : '*';
							$dow = isset($backup['data']['cron_dow']) && strlen($backup['data']['cron_dow'] > 0) ? $backup['data']['cron_dow'] : '*';
							$hour = isset($backup['data']['cron_hour']) && strlen($backup['data']['cron_hour'] > 0)? $backup['data']['cron_hour'] : '*';
							$month = isset($backup['data']['cron_month']) && strlen($backup['data']['cron_month'] > 0) ? $backup['data']['cron_month'] : '*';
							$cronjob = sprintf('%s %s %s %s %s', $minute, $hour, $dom, $month, $dow);
							break;
						default:
							$cronjob = false;
							break;
					}
					if($cronjob !== false){
						$this->Backup->updateBackupSetting($backup['uuid'], 'backup_schedule', $cronjob);
						$this->Backup->updateBackupSetting($backup['uuid'], 'schedule_enabled', 'yes');

						$this->Backup->scheduleJobs($backup['uuid']);
					}
				}
				$this->Backup->updateBackupSetting($backup['uuid'],'backup_storage', $storage);
				$this->Backup->setConfig('id', $backup['id'], 'migrationcompleted');
			}
		}

	}

	public function getMigratedServers($refresh = false){
		if(empty($this->servers) || $refresh){
			$this->servers = $this->Backup->getAll('migratedservers');
		}
		return $this->servers;
	}

	public function getStorageId($oldId){
		return isset($this->storageMapping[$oldId]) ? $this->storageMapping[$oldId] : null;
	}

	public function buildModuleData(){
		if(!empty($this->moduleData)){
			return $this;
		}
		$amodules = $this->freepbx->Modules->getActiveModules();
		$this->moduleData['modules'] = $amodules;
		foreach($amodules as $mod){
			$modTables = $this->getTables($mod['rawname']);
			foreach($modTables as $table){
				$this->moduleData['tables'][$table] = $mod['rawname'];
			}
		}
		return $this;
	}
	public function resetMigrationInfo(){
		$this->Backup->delById('migrationcompleted');
		$this->Backup->delById('migratedbackups');
		$this->Backup->delById('migratedservers');
		return $this;
	}

	public function getTables($module){
		$tables = [];
		$this->loadModuleXML($module);
		if (!$this->moduleXML) {
			return [];
		}
		$moduleTables = $this->moduleXML->database->table;
		if(!$moduleTables){
			return [];
		}
		foreach ($moduleTables as $table) {
			$tname = (string)$table->attributes()->name;
			$tables[] = $tname;
		}
		return $tables;
	}

	public function loadModuleXML($module){
		if($this->ModuleXML){
			return $this;
		}
		$dir = $this->freepbx->Config->get('AMPWEBROOT') . '/admin/modules/' . $module;
		if(!file_exists($dir.'/module.xml')){
			$this->moduleXML = false;
			return $this;
		}
		$xml = simplexml_load_file($dir . '/module.xml');
		$this->moduleXML = $xml;
		return $this;
	}
}
