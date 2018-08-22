<?php
namespace FreePBX\modules\Backup\Modules;
use FreePBX\modules\Filestore\drivers\FTP\FTP;
use FreePBX\modules\Filestore\drivers\Local\Local;
use FreePBX\modules\Filestore\drivers\S3\S3;
use FreePBX\modules\Filestore\drivers\SSH\SSH;
use FreePBX\modules\Filestore\drivers\Email\Email;
use PDO;
class Servers extends Migration{
	public function process(){
		$this->servers = [];
		$this->getLegacyServers()
			->migrate();
		return $this;
	}

	public function getLegacyServers(){
		$sql = 'SELECT * FROM backup_servers';
		$servers = $this->Database->query($sql)->fetchAll(PDO::FETCH_ASSOC);
		$sql = 'SELECT * FROM backup_server_details';
		$serverDetails = $this->Database->query($sql)->fetchAll(PDO::FETCH_ASSOC);
		$final = [];
		$migrated = $this->Backup->getAll('migratedservers');
		foreach ($servers as $server) {
			if(is_array($migrated && isset($migrated['server_'.$server['id']]))){
				continue;
			}
			if (!is_null($server['readonly'])) {
				$server['readonly'] = unserialize($server['readonly']);
			}
			if (!is_null($server['data'])) {
				$server['data'] = unserialize($server['data']);
			}
			$final['server_' . $server['id']]['uuid'] = $this->Backup->generateId();
			$final['server_' . $server['id']]['server'] = $server;
		}
		foreach ($serverDetails as $data) {
			$value = $this->processValue($data['value']);
			$final['server_' . $data['server_id']]['data'][$data['key']] = $value;
		}
		$this->Backup->setMultiConfig($final, 'migratedservers');
		$this->servers = $final;
		return $this;
	}
	public function processValue($value){
		if(strpos($value, '__') === false){
			return $value;
		}
		preg_match("/__(.*)__/", $value, $tmp);
		if(!isset($tmp[1])){
			return $value;
		}
		$conf = $this->FreePBX->Config->get($tmp[1]);
		if(!$conf){
			return $value;
		}
		return str_replace($value, $conf, $value);
	}

	public function migrate(){
		foreach ($this->servers as $server) {
			$uuid = $server['uuid'];
			$server = $server['data'] + $server['server'];
			$server['id'] = $uuid;
			if($server['type'] === 'ftp'){
				return $this->handleFTP($server);
			}
			if($server['type'] === 'mysql'){
				return $this->handleMySql($server);
			}
			if($server['type'] === 'email'){
				return $this->handleEmail($server);
			}
			if($server['type'] === 'local'){
				return $this->handleLocal($server);
			}
			if($server['type'] === 'ssh'){
				return $this->handleSSH($server);
			}
			if($server['type'] === 'awss3'){
				return $this->handleS3($server);
			}
			continue;
		}
		return $this;
	}

	public function handleFTP($data){
		$ftp = new FTP($this->FreePBX);
		$ftp->addItem($data);
		return $this;
	}
	public function handleSSH($data){
		$ssh = new SSH($this->FreePBX);
		$email->addItem($data);    
	}
	public function handleMySQL($data){

	}
	public function handleEmail($data){
		$email = new Email($this->FreePBX);
		$email->addItem($data);        
	}

	public function handleLocal($data){
		$local = new Local($this->FreePBX);
		$local->addItem($data);        
	}
	public function handleS3($data){
		$S3 = new S3($this->FreePBX);
		$S3->addItem($data);    
	}        
}

// vim: set ai ts=4 sw=4 ft=php:
