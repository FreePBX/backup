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
					])
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
				'name' => [
					'type' => Type::nonNull(Type::string()),
					'description' => 'Warm spare Backup file name is required',
				],
			];
		});

		$user->setConnectionResolveNode(function ($edge) {
			return $edge['node'];
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
}
