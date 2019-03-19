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
		preg_match("/(\d{8})-(\d{6})-(\d{10,11})-(.*)-\d*\.tar\.gz(.sha256sum)?/", $this->getFilename(), $output_array);
		$arraySize = sizeof($output_array);
		if ($arraySize != 5 && $arraySize != 6) {
			return false;
		}
		return [
			'datestring' => $output_array[1],
			'timestring' => $output_array[2],
			'timestamp' => $output_array[3],
			'framework' => $output_array[4],
			'isCheckSum' => ($arraySize == 6)
		];
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
		$tar->extract($backuptmpdir, '', '', '/(manifest|metadata\.json)/');
		$metafile = $backuptmpdir . '/metadata.json';
		$manafestfile = $backuptmpdir . '/manifest';
		$meta = [];
		if(file_exists($metafile)){
			$metadata = file_get_contents($metafile);
			$meta = json_decode($metadata, true);
		}
		if(file_exists($manafestfile)){
			$manifestdata = file_get_contents($manafestfile);
			$tmpdata = unserialize($manifestdata);
			$meta = [
				'date' => $tmpdata['ctime'],
				'backupInfo' => [
					'backup_name' => $tmpdata['name'],
					'backup_description' => _("Legacy Restore"),
				],
				'manifest' => $tmpdata,
			];
		}

		$tar->close();
		unset($tar);

		return $meta;
	}
}
