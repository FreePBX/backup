<?php
namespace FreePBX\modules;

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
    			switch ($_REQUEST['action']){
    				case 'wizard':
    					$current_servers = backup_get_server('all_detailed');
    					$server = array();
    					$backup = array();
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
							$server['description'] = $wizdesc?$wizdesc:_('Wizard:&nbsp;').$wizname;
							$server['type'] = $wizremtype;
							$server['host'] = $wizserveraddr;
							$server['user'] = $wizserveruser;
							$server['path'] = $wizremotepath?$wizremotepath:'';
							switch($value['type']){
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
						$backup['cron_schedule'] = $wizfrez;
						switch ($wizfreq) {
							case 'daily':
								$backup['cron_hour'] = $wizat;
							break;
							case 'weekly':
								$backup['cron_dow'] = $wizat['day'];
								$backup['cron_hour'] = $wizat['hour'];
							break;
							case 'monthly':
								$backup['cron_dom'] = $wizat['monthday'];
								$backup['cron_hour'] = $wizat['hour'];
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
    			}
    		break;
    	}
    }
	public function getActionBar($request) {
		$buttons = array();
		switch($request['display']) {
			case 'backup':
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
				if (empty($request['extdisplay'])) {
					unset($buttons['delete']);
				}
				if(empty($request['type'])){
					$button = array();
				}
			break;
		}
		return $buttons;
	}
}
