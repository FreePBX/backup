<?php
/**
 * Copyright Sangoma Technologies, Inc 2015
 */
namespace FreePBX\modules;

$setting = array('authenticate' => true, 'allowremote' => false);
class Backup implements \BMO {
	public function __construct($freepbx = null) {
			if ($freepbx == null) {
					throw new Exception('Not given a FreePBX Object');
			}
			$this->FreePBX = $freepbx;
			$this->db = $freepbx->Database;
			//include __DIR__.'/functions.inc/class.backup.php';
			//$this->Backup = new \FreePBX\modules\Backup();
	}

	public function install(){
	}

	public function uninstall(){
	}

	public function backup(){
	}

	public function restore($backup){
	}

	public function doConfigPageInit($page) {
		$_REQUEST['submit'] = isset($_REQUEST['submit'])?$_REQUEST['submit']:'';
		$_REQUEST['action'] = isset($_REQUEST['action'])?$_REQUEST['action']:'';
		$_REQUEST['id'] = isset($_REQUEST['id'])?$_REQUEST['id']:'';

		switch ($page) {
			case 'backup':
			if ($_REQUEST['submit'] == _('Delete') && $_REQUEST['action'] == 'save') {
				$_REQUEST['action'] = 'delete';
			}
			switch ($_REQUEST['action']) {
				case 'wizard':
					$current_servers = backup_get_server('all_detailed');
					$server = array();
					$backup = array();
					$create_server = false;
					$backup['bu_server'] = '0';
					extract($_REQUEST, EXTR_SKIP);
					foreach ($current_servers as $key => $value) {
						if ($value['name'] == 'Local Storage' && $value['type'] == 'local' && $value['immortal'] == 'true') {
							$backup['storage_servers'][] = $value['id'];
						}
						if ($value['name'] == 'Config server' && $value['type'] == 'mysql' && $value['immortal'] == 'true') {
							$mysqlserver = $value['id'];
						}
						if ($value['name'] == 'CDR server' && $value['type'] == 'local' && $value['immortal'] == 'true') {
							$cdrserver = $value['id'];
						}
						if ($value['type'] == $wizremtype) {
							//If a server has the same host AND user we assume it already exists
							$create_server = !($wizserveraddr == $value['host'] && $wizserveruser == $value['user']);
						}
					}

					if ($create_server && $wizremote == 'yes') {
						$server['name'] = $wizname;
						$server['desc'] = $wizdesc ? $wizdesc : _('Wizard').":&nbsp;".$wizname;
						$server['type'] = $wizremtype;
						$server['host'] = $wizserveraddr;
						$server['user'] = $wizserveruser;
						$server['path'] = $wizremotepath ? $wizremotepath : '';
						switch ($wizremtype) {
							case 'ftp':
							$server['port'] = $wizserverport ? $wizserverport : '21';
							$server['password'] = $wizftppass ? $wizftppass : '';
							$server['transfer'] = $wiztrans;
							break;
							case 'ssh':
							$server['port'] = $wizserverport ? $wizserverport : '22';
							$server['key'] = $wizsshkeypath;
							break;
						}
						$backup['storage_servers'][] = backup_put_server($server);
					}

					//Create Backup Job
					$backup['name'] = $wizname;
					$backup['desc'] = $wizdesc;
					if ($wiznotif == 'yes' && !empty($wizemail)) {
						$backup['email'] = $wizemail;
					} else {
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
					$backup['delete_amount'] = '3'; //3 runs
					$backup['delete_time'] = '30'; //clear backups older than 30...
					$backup['delete_time_type'] = 'days'; //...days
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
					if (PHP_OS == "FreeBSD") {
						$backup['type'][] = 'dir';
						$backup['path'][] = '/usr/local/etc/dahdi';
						$backup['exclude'][] = '';
						$backup['type'][] = 'dir';
						$backup['path'][] = '/usr/local/lib/dahdi';
						$backup['exclude'][] = '';
					} else {
						$backup['type'][] = 'dir';
						$backup['path'][] = '/etc/dahdi';
						$backup['exclude'][] = '';
					}
					$backup['type'][] = 'file';
					$backup['path'][] = '/etc/freepbx.conf';
					$backup['exclude'][] = '';

					//Backup CDR
					if ($wizcdr == 'yes' && isset($cdrserver)) {
						$backup['type'][] = 'mysql';
						$backup['path'][] = 'server-'.$cdrserver;
						$backup['exclude'][] = '';
					}
					//Backup Voicemail
					if ($wizvm == 'yes') {
						$backup['type'][] = 'dir';
						$backup['path'][] = '__ASTSPOOLDIR__/voicemail';
						$backup['exclude'][] = '';
					}
					//Backup Voicemail
					if ($wizbu == 'yes') {
						$backup['type'][] = 'dir';
						$backup['path'][] = '__ASTSPOOLDIR__/monitor';
						$backup['exclude'][] = '';
					}
					backup_put_backup($backup);
				break;
				case 'delete':
					backup_del_backup($_REQUEST['id']);
					//TODO: Not allowed in 5.5
					unset($_REQUEST['action']);
					unset($_REQUEST['id']);
				break;
			}
			break;
		}
	}

	/**
	 * Action bar in 13+
	 * @param [type] $request [description]
	 */
	public function getActionBar($request) {
		$buttons = array(
			'reset' => array(
				'name' => 'reset',
				'id' => 'reset',
				'value' => _('Reset'),
			),
			'submit' => array(
				'name' => 'submit',
				'id' => 'submit',
				'value' => _('Save'),
			),
			'run' => array(
				'name' => 'run',
				'id' => 'run_backup',
				'value' => _('Save and Run'),
			),
			'delete' => array(
				'name' => 'delete',
				'id' => 'delete',
				'value' => _('Delete'),
			),
		);
		switch ($request['display']) {
			case 'backup':
			case 'backup_servers':
				if (empty($request['id'])) {
					unset($buttons['delete']);
					unset($buttons['run']);
				}
				if (empty($request['action']) || (strtolower($request['action']) == 'delete')) {
					$buttons = array();
				}
				if($request['display'] != 'backup' && isset($buttons['run'])){
					unset($buttons['run']);
				}
			break;
			case 'backup_templates':
				if (isset($request['action']) && $request['action'] == "edit" || $request['action'] == "save") {
					unset($buttons['run']);
					unset($buttons['reset']);
					if (!$request['id']) {
						unset($buttons['delete']);
					}
				} else {
					$buttons = array();
				}
			break;
			default:
				$buttons = array();

		}
		return $buttons;
	}

	/**
	 * Ajax Request for BMO
	 * @param string $req     [description]
	 * @param [type] $setting [description]
	 */
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

	/**
	 * Ajax Handler for BMO
	 */
	public function ajaxHandler() {
		switch ($_REQUEST['command']) {
			case 'getJSON':
				switch ($_REQUEST['jdata']) {
					case 'backupGrid':
						return array_values($this->listBackups());
					break;
					case 'serverGrid':
						return array_values($this->listServers());
					break;
					case 'templateGrid':
						return array_values($this->listTemplates());
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

	/**
	 * List all Servers
	 */
	public function listServers() {
		$sql = 'SELECT * FROM backup_servers ORDER BY name';
		$stmt = $this->db->prepare($sql);
		$stmt->execute();
		$ret = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		return $ret;
	}

	/**
	 * List all templates
	 */
	public function listTemplates() {
		$sql = 'SELECT * FROM backup_templates ORDER BY name';
		$stmt = $this->db->prepare($sql);
		$stmt->execute();
		$ret = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		return $ret;
	}

	/**
	 * List all backups
	 */
	public function listBackups() {
		$sql = 'SELECT * FROM backup ORDER BY name';
		$ret = $this->db->query($sql, \PDO::FETCH_ASSOC);
		$backups = array();
		//set index to server id for easy retrieval
		foreach ($ret as $s) {
			//set index to  id for easy retrieval
			$backups[$s['id']] = $s;

			//default name in one is missing
			if (!$backups[$s['id']]['name']) {
				$backups[$s['id']]['name'] = _('Backup').' '.$s['id'];
			}

		}
		return $backups;
	}
	public function getRightNav($request) {
		$var = array();
		switch ($request['display']) {
			case 'backup':
				if(isset($request['action']) && $request['action'] == 'edit'){
					$var['backup'] = backup_get_backup('all');
				}
				return load_view(dirname(__FILE__) . '/views/rnav/backup.php', $var);
			break;
			case 'backup_restore':
				$var['servers'] = backup_get_server('all');
				return load_view(dirname(__FILE__) . '/views/rnav/restore.php', $var);
			break;
			case 'backup_servers':
				if(isset($request['action']) && $request['action'] == 'edit'){
					$var['servers'] = backup_get_server('all');
				}
				return load_view(dirname(__FILE__) . '/views/rnav/servers.php', $var);
			break;
			case 'backup_templates':
				if(isset($request['action']) && $request['action'] == 'edit'){
					$var['templates'] = backup_get_template('all');
				}
				return load_view(dirname(__FILE__) . '/views/rnav/templates.php', $var);
			break;
		}
	}
}
