<?php
namespace FreePBX\modules;
$setting = array('authenticate' => true, 'allowremote' => false);
class Backup implements \BMO {
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new Exception("Not given a FreePBX Object");
		}
		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
		//include __DIR__.'/functions.inc/class.backup.php';
		//$this->Backup = new \FreePBX\modules\Backup();
	}
    public function install() {}
    public function uninstall() {}
    public function backup() {}
    public function restore($backup) {}
    public function doConfigPageInit($page) {
    	switch ($page) {
    		case 'backup':
    		if ($_REQUEST['submit'] == _('Delete') && $_REQUEST['action'] == 'save') {
    			$_REQUEST['action'] = 'delete';
    		}
    			switch ($_REQUEST['action']){
    				case 'wizard':
    					$current_servers = backup_get_server('all_detailed');
    					$server = array();
    					$backup = array();
    					dbug($_REQUEST);
    					extract($_REQUEST);
    					foreach($current_servers as $key => $value){
							if ($value['name'] == 'Local Storage' && $value['type'] == 'local' && $value['immortal'] == 'true') {
								$localserver = $value['id'];
							}
							if ($value['name'] == 'Config server' && $value['type'] == 'mysql' && $value['immortal'] == 'true') {
								$mysqlserver = $value['id'];
							}
							if ($value['name'] == 'CDR server' && $value['type'] == 'local' && $value['immortal'] == 'true') {
								$cdrserver = $value['id'];
							}
    						if($value['type'] == $wizremtype){
    							//If a server has the same host AND user we assume it already exists
    							$server_exists = ($wizserveraddr == $value['host'] && $wizserveruser == $value['user']);
    						}
    					}
						if(!$server_exists && $wizremote == 'yes'){
							$server['name'] = $wizname;
							$server['desc'] = $wizdesc?$wizdesc:_('Wizard:&nbsp;').$wizname;
							$server['type'] = $wizremtype;
							$server['host'] = $wizserveraddr;
							$server['user'] = $wizserveruser;
							$server['path'] = $wizremotepath?$wizremotepath:'';
							switch($wizremtype){
								case 'ftp':
									$server['port'] = $wizserverport?$wizserverport:'21';
									$server['password'] = $wizftppass?$wizftppass:'';
									$server['transfer'] = $wiztrans;
								break;
								case 'ssh':
									$server['port'] = $wizserverport?$wizserverport:'22';
									$server['key'] = $wizsshkeypath;
								break;
							}
							$serverid = backup_put_server($server);
						}else{
							$serverid = $value['id'];
						}
    				
						//Create Backup Job
						$backup['name'] = $wizname;
						$backup['description'] = $wizdesc;
						if($wiznotif == 'yes' && !empty($wizemail)){
							$backup['email'] = $wizemail;
						}else{
							$backup['email'] = '';
						}
						//cron
						$backup['cron_minute'] = array('*');
						$backup['cron_dom'] = array('*');
						$backup['cron_dow'] = array('*');
						$backup['cron_hour'] = array('*');
						$backup['cron_month'] = array('*');
						$backup['cron_schedule'] = $wizfreq;
						switch ($wizfreq) {
							case 'daily':
								$backup['cron_hour'] = array($wizat);
							break;
							case 'weekly':
								$backup['cron_dow'] = array($wizat[0]);
								$backup['cron_hour'] = array($wizat[1]);
							break;
							case 'monthly':
								$backup['cron_dom'] = array($wizat[0]);
								$backup['cron_hour'] = array('23');
								$backup['cron_minute'] = array('59');
							break;
						}
						$backup['storage_servers'] = array($localserver,$serverid);
						$backup['delete_amount'] = '3'; //3 runs
						$backup['delete_time']= '30'; //clear backups older than 30...
						$backup['delete_time_type']= 'days'; //...days
    					//Backup Configs
						$backup['type'][] = 'astdb';
						$backup['path'][] = '';
						$backup['exclude'][] = '';
						$backup['type'][] = 'dir';
						$backup['path'][] = '__AMPWEBROOT__/admin';
						$backup['exclude'][] = '';
						$backup['type'][] = 'dir';
						$backup['path'][] = '__ASTETCDIR__';
						$backup['exclude'][] = '';
						$backup['type'][] = 'dir';
						$backup['path'][] = '__AMPBIN__';
						$backup['exclude'][] = '';
						$backup['type'][] = 'dir';
						$backup['path'][] = '/etc/dahdi';
						$backup['exclude'][] = '';
						$backup['type'][] = 'file';
						$backup['path'][] = '/etc/freepbx.conf';
						$backup['exclude'][] = '';

						//Backup CDR
						if($wizcdr == 'yes'){
							$backup['type'][] = 'mysql';
							$backup['path'][] = 'server-'.$cdrserver;
							$backup['exclude'][] ='';
						}
						//Backup Voicemail
						if($wizvm == 'yes'){
							$backup['type'][] = 'dir';
							$backup['path'][] = '__ASTSPOOLDIR__/voicemail';
							$backup['exclude'][] = '';
						}
						//Backup Voicemail
						if($wizrecording == 'yes'){
							$backup['type'][] = 'dir';
							$backup['path'][] = '__ASTSPOOLDIR__/monitor';
							$backup['exclude'][] = '';
						}
						backup_put_backup($backup);	
    				break;
    				case 'delete':
    					backup_del_backup($_REQUEST['id']);
    					unset($_REQUEST['action']);
    					unset($_REQUEST['id']);
    				break;
    			}
    		break;
    	}
    }
	public function getActionBar($request) {
		dbug($request);
		$buttons = array();
		switch($request['display']) {
			case 'backup':
			case 'backup_servers':
			case 'backup_templates':
				$buttons = array(
					'delete' => array(
						'name' => 'delete',
						'id' => 'delete',
						'value' => _('Delete')
					),
					'reset' => array(
						'name' => 'reset',
						'id' => 'reset',
						'value' => _('Reset')
					),
					'submit' => array(
						'name' => 'submit',
						'id' => 'submit',
						'value' => _('Submit')
					)
				);
				if (empty($request['id'])) {
					unset($buttons['delete']);
				}
				if((!$request['action']) || (strtolower($request['action']) == 'delete')){
					$buttons = array();
				}
			break;
		}
		return $buttons;
	}
	//AJAX
	public function ajaxRequest($req, &$setting) {       
		switch ($req) {           
			case 'getJSON':               
				return true;           
			break;                   
			default:               
				return false;           
			break;       
		}   
	}   
	public function ajaxHandler(){       
		switch ($_REQUEST['command']) {           
			case 'getJSON':               
				switch ($_REQUEST['jdata']) {                   
					case 'backupGrid':
						return array_values($this->listBackups());       
					return $ret;
					break;
					case 'backupGrid':
						$ret = array();       
					return $ret;
					break;
					case 'serverGrid':
						$ret = array();       
					return $ret;
					break;
					case 'templateGrid':
						$ret = array();       
					return $ret;
					break;
					default:
						return false;
					break; 
				}
			break;
			default:
				return false;
			break;
		}
	}
	public function listBackups(){
		$sql = 'SELECT * FROM backup ORDER BY name';
		$ret = $this->db->query($sql, \PDO::FETCH_ASSOC);
		$backups = array();
		//set index to server id for easy retrieval
		foreach ($ret as $s) {
			//set index to  id for easy retrieval
			$backups[$s['id']] = $s;

			//default name in one is missing
			if (!$backups[$s['id']]['name']) {
				$backups[$s['id']]['name'] = _('Backup') . ' ' . $s['id'];
			}

			//add details if requested
			if ($id == 'all_detailed') {
				$backups[$s['id']] = backup_get_backup($s['id']);
			}
		}
		return $backups;
	}
}