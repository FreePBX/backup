<?php
namespace FreePBX\modules\Backup\Migration;
use PDO;
class Servers extends Common {
	public $servers;

	public function process(){
		$this->servers = [];
		$this->getLegacyServers();
		return $this->migrate();
	}

	public function getLegacyServers() {
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
		//$migrated = $this->Backup->getAll('migratedservers');
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
		}
		foreach ($serverDetails as $data) {
			$value = $this->processValue($data['value']);
			$final['server_' . $data['server_id']]['server'][$data['key']] = $value;
		}
		foreach($final as $key => $ser) {
			$this->Backup->setConfig($key, $ser, 'migratedservers');
		}
		$this->servers = $final;
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
		$mapping = [];
		foreach ($this->servers as $item) {
			$server = $item['server'];
			$uuid = null;
			switch($server['type']) {
				case 'ftp':
					$uuid = $this->handleFTP($server);
				break;
				case 'email':
					$uuid = $this->handleEmail($server);
				break;
				case 'local':
					$uuid = $this->handleLocal($server);
				break;
				case 'ssh':
					$uuid = $this->handleSSH($server);
				break;
				case 'awss3':
					$uuid = $this->handleS3($server);
				break;
				default:
					out(sprintf(_("Unable to map '%s' of type '%s"),$server['name'],$server['type']));
					//unknown type!
				break;
			}
			if(!empty($uuid)) {
				$item = $this->freepbx->Filestore->getItemById($uuid);
				$mapping[$server['id']] = $uuid;
			}
		}
		return $mapping;
	}

	public function handleFTP($data){
		return $this->freepbx->Filestore->addItem('FTP',$data);
	}

	public function handleSSH($data){
		return $this->freepbx->Filestore->addItem('SSH',$data);
	}

	public function handleEmail($data){
		return $this->freepbx->Filestore->addItem('Email',$data);
	}

	public function handleLocal($data){
		return $this->freepbx->Filestore->addItem('Local',$data);
	}

	public function handleS3($data){
		return $this->freepbx->Filestore->addItem('S3',$data);
	}
}

// vim: set ai ts=4 sw=4 ft=php:
