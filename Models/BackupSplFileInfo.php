<?php
namespace FreePBX\modules\Backup\Models;
use Symfony\Component\Filesystem\Filesystem;
use SplFileInfo;
use splitbrain\PHPArchive\Tar;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;

/**
 * Used to read information about a backup file
 * Utilizes SplFileInfo
 */
class BackupSplFileInfo extends SplFileInfo{

	/**
	* Parse the filename in to components based on the file format
	* current format yyyymmdd-hhmmss-unixtime-frameworkversion-random.tar.gz
	* current format yyyymmdd-hhmmss-unixtime-frameworkversion-random.tar.gz.sha256sum
	*
	* @return array file components
	*/
	public function backupData(){
		//20171012-130011-1507838411-15.0.1alpha1-42886857.tar.gz
		preg_match("/(\d{8})-(\d{6})-(\d{10,11})(?:-(.*))?-(\d+)\.(tar\.gz|tgz)(\.sha256sum)?/", $this->getFilename(), $output_array);
		$arraySize = sizeof($output_array);
		if ($arraySize < 5) {
			return false;
		}
		return [
			'datestring' => $output_array[1],
			'timestring' => $output_array[2],
			'timestamp' => $output_array[3],
			'framework' => !empty($output_array[4]) ? $output_array[4] : 'legacy',
			'isCheckSum' => ($arraySize == 6),
			'size' => $this->getSize(),
		];
	}

	/**
	* Get the size of the file
	* 
	* @return integer Returns the filesize in bytes for the file referenced or -1 if file not exist.
	*/
	public function getSize(): int|false{
		$data_return = -1;
		if (file_exists($this->getPathname()))
		{
			$data_return = parent::getSize();
		}
		return $data_return;
	}
	
	/**
	* Gets the manifest from the file
	*
	* @return array manifest
	*/
	public function getMetadata(){
		$backuptmpdir = sys_get_temp_dir().'/'.time();
		$fileSystem = new Filesystem();

		$fileSystem->mkdir($backuptmpdir, 0755);

		$tar = new Tar();
		$tar->open($this->getPathname());
		$tar->extract($backuptmpdir, '', '', '/(manifest|metadata\.json|files|modulejson|mysql-2.sql.gz|mysql-3.sql.gz|mysql-3.sql)/');
		$metafile = $backuptmpdir . '/metadata.json';
		$manafestfile = $backuptmpdir . '/manifest';
		$meta = [];
		if(file_exists($metafile)){
			$metadata = file_get_contents($metafile);
			$meta = json_decode($metadata, true);
		}
		if(file_exists($manafestfile)){
			$manifestdata = file_get_contents($manafestfile);

			$tmpdata = json_decode($manifestdata, true);
			if ($tmpdata === null || json_last_error() !== JSON_ERROR_NONE || !is_array($tmpdata)) {
				$tmpdata = unserialize($manifestdata, ['allowed_classes' => false]);
			}
			if (!is_array($tmpdata)) {
				throw new \Exception('Invalid manifest data format');
			}
			$meta = [
				'date' => isset($tmpdata['ctime']) ? $tmpdata['ctime'] : null,
				'backupInfo' => [
					'backup_name' => isset($tmpdata['name']) ? $tmpdata['name'] : '',
					'backup_description' => _("Legacy Restore"),
				],
				'manifest' => $tmpdata,
			];
		}
		$version = \FreePBX::Config()->get('ASTVERSION');
		$sipdriver = \FreePBX::Config()->get('ASTSIPDRIVER');
		if(version_compare($version, '21', 'ge')) {
			$chansipDevExists = $this->checkChansipDevice($backuptmpdir);
			$meta['chansipexists'] = $chansipDevExists;
			$chansipTrunkExists = $this->checkChansipTrunk($backuptmpdir);
			$meta['chansipTrunkExists'] = $chansipTrunkExists;
		}

		$tar->close();
		unset($tar);
		return $meta;
	}

	public function checkChansipDevice($backuptmpdir) {
		$chansipExists = false;
		$coreModule = $backuptmpdir . "/modulejson/Core.json";
		$devDumpFile = $backuptmpdir . "/files/tmp/Devices_dump/Devices.sql";
		$legacySqlFile = $backuptmpdir . "/mysql-2.sql.gz"; //legacy backup file containing sip devices (FreePBX v13,14 - mysql-2.sql.gz)
		if(!file_exists($legacySqlFile)) {
			$legacySqlFile = $backuptmpdir . "/mysql-3.sql.gz"; //on some 13 sql backup file found sip devices in this file
		}
		$legacyV2file = $backuptmpdir . "/mysql-3.sql"; //(FreePBX v2 - mysql-3.sql)
		if(file_exists($coreModule)){
			$coredata = file_get_contents($coreModule);
			$coremeta = json_decode($coredata, true);
			$device_details = isset($coremeta['configs']['Devices']['devices']) ? $coremeta['configs']['Devices']['devices'] : [];
			if(count($device_details) >0){
				foreach ($device_details as $dev) {
					if($dev['tech'] == 'sip') {
						$chansipExists = true;
						break;
					}
				}
			}
		} 
		if(!$chansipExists && file_exists($devDumpFile)) {
			$insertDevicesquery = false;
			$contents = file($devDumpFile);
			foreach($contents as $line) {
				if(str_contains($line,"INSERT INTO `devices`")) {
					$insertDevicesquery = true;
				} else {
					continue;
				}

				if($insertDevicesquery && str_contains($line,"'sip'")) {
					$chansipExists = true;
					break;
				}

				if(str_contains($line,";")) {
					break;
				}
			}
		} 
		if(!$chansipExists && file_exists($legacySqlFile)) {
			$insertDevicesquery = false;
			$sfp = gzopen($legacySqlFile, "r");
			while ($line = fgets($sfp)) {
				if(str_contains($line,"INSERT INTO `devices`")) {
					$insertDevicesquery = true;
				} else {
					continue;
				}

				if($insertDevicesquery && str_contains($line,"'sip'")) {
					$chansipExists = true;
					break;
				}

				if(str_contains($line,";")) {
					break;
				}
			}
		} 
		if(!$chansipExists && file_exists($legacyV2file)) {
			$insertDevicesquery = false;
			$contents = file($legacyV2file);
			foreach($contents as $line) {
				if(str_contains($line,"INSERT INTO `devices`")) {
					$insertDevicesquery = true;
				} else {
					continue;
				}

				if($insertDevicesquery && str_contains($line,"'sip'")) {
					$chansipExists = true;
					break;
				}

				if(str_contains($line,";")) {
					break;
				}
			}

		}
		return $chansipExists;
	}


	public function checkChansipTrunk($backuptmpdir) {
		$chansipExists = false;
		$coreModule = $backuptmpdir . "/modulejson/Core.json";
		$devDumpFile = $backuptmpdir . "/files/tmp/Devices_dump/Devices.sql";
		$legacySqlFile = $backuptmpdir . "/mysql-2.sql.gz"; //legacy backup file containing sip devices (FreePBX v13,14 - mysql-2.sql.gz)
		$legacyV2file = $backuptmpdir . "/mysql-3.sql"; //(FreePBX v2 - mysql-3.sql)
		if(file_exists($coreModule)){
			$coredata = file_get_contents($coreModule);
			$coremeta = json_decode($coredata, true);
			$device_details = isset($coremeta['configs']['Trunks']['trunks']) ? $coremeta['configs']['Trunks']['trunks'] : [];
			if(count($device_details) >0){
				foreach ($device_details as $dev) {
					if($dev['tech'] == 'sip') {
						$chansipExists = true;
						break;
					}
				}
			}
		} 
		if(!$chansipExists && file_exists($devDumpFile)) {
			$contents = file($devDumpFile);
			foreach($contents as $line) {
				if(str_contains($line,"INSERT INTO `trunks`") && str_contains($line,"'sip'")) {
					$chansipExists = true;
					break;
				} else {
					continue;
				}
				
				if(str_contains($line,";")) {
					break;
				}
			}
		} 
		if(!$chansipExists && file_exists($legacySqlFile)) {
			$sfp = gzopen($legacySqlFile, "r");
			while ($line = fgets($sfp)) {
				if(str_contains($line,"INSERT INTO `trunks`") && str_contains($line,"'sip'")) {
					$chansipExists = true;
					break;
				} else {
					continue;
				}

				if(str_contains($line,";")) {
					break;
				}
			}
		} 
		if(!$chansipExists && file_exists($legacyV2file)) {
			$insertDevicesquery = false;
			$contents = file($legacyV2file);
			dbug($contents);
			foreach($contents as $line) {
				if(str_contains($line,"INSERT INTO `trunks`") && str_contains($line,"'sip'")) {
					$chansipExists = true;
					break;
				} else {
					continue;
				}

				if(str_contains($line,";")) {
					break;
				}
			}

		}
		return $chansipExists;
	}

}
