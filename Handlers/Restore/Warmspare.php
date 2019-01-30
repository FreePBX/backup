<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules\Backup\Handlers;

use FreePBX\modules\Filestore\Modules\Remote as FilestoreRemote;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
use Exception;
class Warmspare extends Multiple {
	private $backupdata = [];

	public function process(){
		$backupData = $this->getBackupString($this->id);
		$ssh = new FilestoreRemote();
		$host = $this->backupdata['warmspare_remoteip'];
		if(!$host){
			return;
		}
		$ssh->createSSH($host);
		$user = isset($this->backupdata['warmspare_user'])?$this->backupdata['warmspare_user']:'root';
		$homepath = '/home/'.$user;
		if($user == 'root'){
			$homepath = '/root';
		}
		$keypath = $homepath. '/.ssh/id_rsa';
		$ssh->authenticateSSH($user,$keypath);
		$transaction = 'remote-backup-'.time();
		$command = sprintf('/usr/sbin/fwconsole backup --externbackup=%s --transaction=%s',$backupData, $transaction);
		$ssh->sendCommand($command, DEBUG);
		$ssh->grabFile($homepath.'/'.$transaction.'.tar.gz', $homepath . '/' . $transaction . '.tar.gz');
		exec('/usr/sbin/fwconsole backup --warmspare --restore='. $homepath . '/' . $transaction . '.tar.gz',$out,$ret);
		return $ret;
	}

	public function getBackupString(){
		$this->backupdata = $this->freepbx->Backup->getBackup($this->id);
		$this->backupdata['backup_items'] = $this->freepbx->Backup->getAll('modules_' . $this->id);
		return base64_encode(json_encode($this->backupdata));
	}
}
