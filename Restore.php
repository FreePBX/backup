<?php
namespace FreePBX\modules\Backup;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	public function runRestore(){
		$settings = $this->getConfigs();
		$this->importKVStore($settings['kvstore']);
	}
	public function processLegacy($pdo, $data, $tables, $unknownTables){
		$this->log('Restoring only Legacy Backup FTP Servers to Filestore');
		$this->RestoreLegacyFtpFilestore($pdo);
	}
	
	private function RestoreLegacyFtpFilestore($pdo){
		//backup_server,backup_server_details
		$bkserver = "SELECT id,name,desc FROM backup_servers WHERE type='ftp'";
		$sth = $pdo->query($bkserver,\PDO::FETCH_ASSOC);
		$serversar = $sth->fetchAll();
		if(!empty($serversar)) {
			foreach($serversar as $ser){
				$server = ['id'=>'','action'=>'add','timeout'=>30,'name'=>$ser['name'],'desc'=>$ser['desc'],'driver'=>'FTP'];
				$bkserverd = "SELECT key,value FROM backup_server_details WHERE server_id='".$ser['id']."'";
				$sth = $pdo->query($bkserverd,\PDO::FETCH_ASSOC);
				$res = $sth->fetchAll();
				foreach($res as $row) {
					$server[$row['key']] = $row['value'];
				}
				$this->FreePBX->Filestore->addItem('FTP',$server);
			}
		}
	}
}
