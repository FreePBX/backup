<?php

namespace FreePBX\modules\Backup\Api\Gql;

use GraphQLRelay\Relay;
use GraphQL\Type\Definition\Type;
use FreePBX\modules\Api\Gql\Base;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Backup extends Base {
	protected $module = 'backup';

	public function mutationCallback() {
		if($this->checkAllWriteScope()) {
			return function() {
				return [
					'runWarmsparebackuprestore' => Relay::mutationWithClientMutationId([
						'name' => 'RunWarmSpareRestore',
						'description' => 'Run a Warm Spare Restore',
						'inputFields' => $this->getMutationFields(),
						'outputFields' => [
							'restorestatus' => [
								'type' => Type::nonNull(Type::string()),
								'resolve' => function ($payload) {
									return $payload['restorestatus'];
								}
							]
						],
						'mutateAndGetPayload' => function ($input) {
							//lets run the  restore command 'backupfilename'
							$filename = $input['backupfilename'];
							if(file_exists($filename)) {
								$command = "fwconsole backup --restore $filename --skiprestorehooks";
								$process = new Process($command);
								try {
									$process->setTimeout(null);
									$process->mustRun();
									$out = $process->getOutput();
									return ['restorestatus' =>'Restore Done'];
								} catch (ProcessFailedException $e) {
									return ['restorestatus' =>'Restore Errored'];
								}
							}

							return ['restorestatus' =>'Backup file not found'];
						}
					]),
					'addBackup' => Relay::mutationWithClientMutationId([
						'name' => _('addBackup'),
						'description' => _('Set Backup Configurations'),
						'inputFields' => $this->getAddBackupInputFields(),
						'outputFields' => $this->getAddBackupOutputFields(),
						'mutateAndGetPayload' => function ($input) {
							$input = $this->resolveNames($input);
							if($input['schedule_enabled'] == 'yes' && empty($input['backup_schedule'])){
								return ['message' => _('You have enabled enableBackupSchedule so please add scheduleBackup'), 'status' => false];
							}
							return $this->addBackup($input);
						}
					]),
					'restoreBackup' => Relay::mutationWithClientMutationId([
						'name' => _('restoreBackup'),
						'description' => _('Restore Backup'),
						'inputFields' => $this->getRestoreInputFields(),
						'outputFields' => $this->getRestoreOutputFields(),
						'mutateAndGetPayload' => function ($input) {
							return $this->restoreBackup($input);
						}
					]),
				];
			};
		}
	}

	/**
	 * queryCallback
	 *
	 * @return void
	 */
	public function queryCallback() {
		if($this->checkAllReadScope()) {
			return function() {
				return [
					'fetchBackupFiles' => [
						'type' => $this->typeContainer->get('backup')->getConnectionType(),
						'resolve' => function($root, $args) {
                     $res = $this->freepbx->backup->getAllRemote();
							if(!empty($res)){
								return ['message' => _("List of backup files"), 'status' => true , 'fileDetails' => json_encode($res)];
							}else{
								return ['message' => _('Sorry unable to find the filestore types'), 'status' => false];
							}
						}
					],
            ];
			};
	   }
	}
	
	public function initializeTypes() {
		$user = $this->typeContainer->create('backup');
		$user->setDescription('%description%');

		$user->addInterfaceCallback(function() {
			return [$this->getNodeDefinition()['nodeInterface']];
		});

		$user->addFieldCallback(function() {
			return [
				'id' => Relay::globalIdField('backup', function($row) {
					return $row['id'];
				}),
				'name' => [
					'type' => Type::nonNull(Type::string()),
					'description' => 'Warm spare Backup file name is required',
				],
				'status' =>[
					'type' => Type::boolean(),
					'description' => _('Status of the request'),
				],
				'message' =>[
					'type' => Type::String(),
					'description' => _('Message for the request')
				]
			];
		});

		$user->setConnectionResolveNode(function ($edge) {
			return $edge['node'];
		});

		$user->setConnectionFields(function() {
			return [
				'message' =>[
					'type' => Type::string(),
					'description' => _('Message for the request')
				],
				'status' =>[
					'type' => Type::boolean(),
					'description' => _('Status for the request')
				],
				'fileDetails' => [
					'type' => Type::string(),
					'description' => _('List of backuo files')
				]
			];
		});
	}
	
	private function getMutationFields() {
		return [
			'backupfilename' => [
				'type' => Type::nonNull(Type::string()),
				'description' => 'Provide a Backup filename'
			],

		];
	}

	private function getMutationExecuteArray($input) {
		return [
			":backupfilename" => isset($input['backupfilename']) ? $input['backupfilename'] : '',
		];
	}
	
	/**
	 * getAddBackupInputFields
	 *
	 * @return void
	 */
	private function getAddBackupInputFields(){
		return [
			'name' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('A name used to identify your backups')
			],
			'description' => [
				'type' => Type::string(),
				'description' => _('Add a description for your backup')
			],
			'backupModules' => [
				'type' => Type::nonNull(Type::listOf(Type::String())),
				'description' => _('Modules to backup')
			],
			'notificationEmail' => [
				'type' => Type::string(),
				'description' => _('Email address to send notifications, Multiple email addresses need to be separated by comma')
			],
			'inlineLogs' => [
				'type' => Type::string(),
				'description' => _('When set to Yes logs will be added to the body of the email, when set to No logs will be added as an attachment, default no')
			],
			'emailType' => [
				'type' => Type::string(),
				'description' => _('When to email default both')
			],
			'storageLocation' => [
				'type' => Type::nonNull(Type::listOf(Type::String())),
				'description' => _('Select one or more storage locations. Storage locations can be added/configured with the Filestore module')
			],
			'appendBackupName' => [
				'type' => Type::boolean(),
				'description' => _('When set to Yes , Backp files will store like filestore-path/backup-job-name/backup-file and if set to NO then backup file will store into filestore-path/backup-file,default false')
			],
			'enableBackupSchedule' => [
				'type' => Type::boolean(),
				'description' => _('Enable scheduled backups, default false')
			],
			'scheduleBackup' => [
				'type' => Type::string(),
				'description' => _('When should this backup run')
			],
			'updatesToKeep' => [
				'type' => Type::id(),
				'description' => _('How many updates to keep. If this number is 3, the last 3 will be kept. 0 is unlimited')
			],
			'backupDaysToKeep' => [
				'type' => Type::id(),
				'description' => _('How long to maintain backups. Example 30 will delete anything older than 30 days.')
			],
			'warmSpace' => [
				'type' => Type::boolean(),
				'description' => _('Should the warm spare feature be enabled, default false')
			],
		];
	}
	
	/**
	 * getRestoreInputFields
	 *
	 * @return void
	 */
	private function getRestoreInputFields(){
		return [
			'name' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('Name of the Restore input file')
			]
		];
	}
	
	/**
	 * getAddBackupOutputFields
	 *
	 * @return void
	 */
	private function getAddBackupOutputFields(){
		return [
			'status' => [
				'type' => Type::boolean(),
				'resolve' => function ($payload) {
					return $payload['status'];
				}
			],
			'message' => [
				'type' => Type::string(),
				'resolve' => function ($payload) {
					return $payload['message'];
				}
			],
			'id' => [
				'type' => Type::string(),
				'resolve' => function ($payload) {
					return isset($payload['id']) ? $payload['id'] : null;
				}
			],
		];
	}
			
	/**
	 * getRestoreOutputFields
	 *
	 * @return void
	 */
	private function getRestoreOutputFields(){
		return [
			'status' => [
				'type' => Type::boolean(),
				'resolve' => function ($payload) {
					return $payload['status'];
				}
			],
			'message' => [
				'type' => Type::string(),
				'resolve' => function ($payload) {
					return $payload['message'];
				}
			],
			'transaction_id' => [
				'type' => Type::string(),
				'resolve' => function ($payload) {
					return isset($payload['transaction_id']) ? $payload['transaction_id']: '' ;
				}
			]
		];
	}

	/**
	 * resolveNames
	 *
	 * @param  mixed $input
	 * @return void
	 */
	private function resolveNames($input){
	   $input['backup_name'] = isset($input['name']) ? $input['name'] : '';
		$input['backup_description'] = isset($input['description']) ? $input['description'] : '';
		$input['backup_email'] = isset($input['notificationEmail']) ? $input['notificationEmail'] : '';
		$input['backup_emailinline'] = isset($input['inlineLogs']) &&  $input['inlineLogs'] == true ? 'yes' : 'no';
		$input['backup_emailtype'] = isset($input['emailType']) ? $input['emailType'] : '';
		$input['backup_storage'] = isset($input['storageLocation']) ? $input['storageLocation'] : '';
		$input['backup_addbjname'] = isset($input['appendBackupName']) &&  $input['appendBackupName'] == true ? 'yes' : 'no';
		$input['schedule_enabled'] = isset($input['enableBackupSchedule']) &&  $input['enableBackupSchedule'] == true ? 'yes' : 'no';
		$input['backup_schedule'] = isset($input['scheduleBackup']) ? $input['scheduleBackup'] : '';		
		$input['maintruns'] = isset($input['updatesToKeep']) ? $input['updatesToKeep'] : '';
		$input['maintage'] = isset($input['backupDaysToKeep']) ? $input['backupDaysToKeep'] : 0;
		$input['warmspareenabled'] = isset($input['warmSpace']) &&  $input['warmSpace'] == true ? 'yes' : 'no';

		return $input;
	}

	/**
	 * addBackup
	 *
	 * @param  mixed $input
	 * @return void
	 */
	private function addBackup($input){
		$data = [];
		$data['id'] = isset($input['id']) ? $input['id'] : $this->freepbx->backup->generateID();
		$data['backup_items'] = $backup_items = isset($input['backupModules']) ? $input['backupModules'] : ['all'];
			$newbackup = array();
		if($backup_items[0] == 'all'){
			$backup =  $this->freepbx->backup->getModules();
			foreach($backup as $bckup){
				array_push($newbackup,array('modulename' => $bckup['rawname'], 'selected' => true, 'settings' => array()));
			}
		}else{
			$validiModuleNames = array();
			$backupModules = $this->freepbx->backup->getModules();
			foreach($backupModules as $module){
				array_push($validiModuleNames,$module['rawname']);
			}
			foreach($backup_items as $item){
				if(in_array($item,$validiModuleNames)){
					array_push($newbackup,array('modulename' => $item, 'selected' => true, 'settings' => array()));
				}else{
					return ['message' => _('Sorry Module name '. $item .' is invalid'), 'status' => false];
				}
			}
		}
		$data['backup_items'] = $backup_items = $newbackup;

		foreach ($this->freepbx->backup->backupFields as $col) {
		 	//This will be set independently
			if($col == 'immortal'){
				continue;
			}

			$value = isset($input[$col]) ? $input[$col] : ''; 
			if($col == 'name'){
				$value = str_replace(' ', '-', $value); 
				$value = preg_replace('/[^A-Za-z0-9\-]/', '', $value);
			}
			$this->freepbx->backup->updateBackupSetting($data['id'], $col, $value);
		}
		
		$backup_name = isset($input['name']) ? $input['name'] : ''; 
		$backup_name = str_replace(' ', '-', $backup_name); 
		$backup_name = preg_replace('/[^A-Za-z0-9\-]/', '', $backup_name);
		$description = isset($input['description']) ? $input['description'] : ''; 
		$cftype = isset($input['type']) ? $input['type'] : ''; 
		$path = isset($input['path']) ? $input['path'] : ''; 
		$exclude = isset($input['exclude']) ? $input['exclude'] : ''; 

		$res = $this->freepbx->backup->performBackup($data,$backup_name,$description,$backup_items,$cftype,$path,$exclude);
	  	if($res){
			return ['message' => _('Backup has been performed/schedules'), 'status'=> true, 'id' => $res];
		}else{
			return ['message' => _('Unable to perform backup'), 'status' => false];
		}
	}
	
	/**
	 * restoreBackup
	 *
	 * @param  mixed $input
	 * @return void
	 */
	private function restoreBackup($input){
		$filename = $input['name'];
		$txnId = $this->freepbx->api->addTransaction("Processing","restore","perform-restore");
		$res = \FreePBX::Sysadmin()->ApiHooks()->runModuleSystemHook('backup','perform-restore',array($filename,$txnId));
		if($res){
			return ['message' => _('Restore process has been initiated. Kindly check the fetchApiStatus api with the transaction id.'),'status' => true , 'transaction_id' => $txnId];
		}else{
			return ['message' => _('Sorry failed to perform restore'),'status' => false];
		}
	}
}
