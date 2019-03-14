<?php
namespace FreePBX\modules\Backup\Migration;
use PDO;
class Servers extends Common {
	public $servers;

	public function process(){
		$this->servers = [];
		$this->getLegacyServers()
			->migrate();
		return $this;
	}

	public function getLegacyServers()
	{
		$this->servers = [];

		$sql = 'SELECT * FROM backup_servers';
		try {
			$servers = $this->Database->query($sql)->fetchAll(PDO::FETCH_ASSOC);
		} catch (\Exception $e) {
			// This is a new install, so there was no historical 'backup_servers' table
			return $this;
		}
		$sql = 'SELECT * FROM backup_server_details';
		$serverDetails = $this->Database->query($sql)->fetchAll(PDO::FETCH_ASSOC);
		$final = [];
		$migrated = $this->Backup->getAll('migratedservers');
		$migrated = is_array($migrated) ? $migrated : [];
		foreach ($servers as $server) {
			if (isset($migrated['server_' . $server['id']])) {
				continue;
			}
			if (!is_null($server['readonly'])) {
				$server['readonly'] = unserialize($server['readonly']);
			}
			if (!is_null($server['data'])) {
				$server['data'] = unserialize($server['data']);
			}
			$final['server_' . $server['id']]['server'] = $server;
			$final['server_' . $server['id']]['uuid'] = $this->Backup->generateId();

		}
		foreach ($serverDetails as $data) {
			$value = $this->processValue($data['value']);
			$final['server_' . $data['server_id']]['server'][$data['key']] = $value;
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
		$conf = $this->freepbx->Config->get($tmp[1]);
		if(!$conf){
			return $value;
		}
		return str_replace($value, $conf, $value);
	}

	public function migrate(){
		foreach ($this->servers as $item) {
			$server = $item['server'];
			$server['id'] = $item['uuid'];
			if($server['type'] === 'ftp'){
				$this->handleFTP($server);
			}
			if($server['type'] === 'mysql'){
				$this->handleMySql($server);
			}
			if($server['type'] === 'email'){
				$this->handleEmail($server);
			}
			if($server['type'] === 'local'){
				$this->handleLocal($server);
			}
			if($server['type'] === 'ssh'){
				$this->handleSSH($server);
			}
			if($server['type'] === 'awss3'){
				$this->handleS3($server);
			}
		}
		return $this;
	}

	public function handleFTP($data){
		$this->freepbx->Filestore->addItem('FTP',$data);
	}
	public function handleSSH($data){
		$this->freepbx->Filestore->addItem('SSH',$data);
	}
	public function handleMySQL($data){

	}
	public function handleEmail($data){
		$this->freepbx->Filestore->addItem('Email',$data);
	}

	public function handleLocal($data){
		$this->freepbx->Filestore->addItem('Local',$data);
	}
	public function handleS3($data){
		$this->freepbx->Filestore->addItem('S3',$data);
	}
}

// vim: set ai ts=4 sw=4 ft=php:
