<?php
namespace FreePBX\modules\Backup\Models;

use SplFileInfo;
use splitbrain\PHPArchive\Tar;

class BackupFile extends SplFileInfo{

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
		define('BACKUPTMPDIR', '/var/spool/asterisk/tmp');

		$tar = new Tar();
		$tar->open($this->getPathname());
		$tar->extract(BACKUPTMPDIR, '', '', '/metadata\.json/');

		$metadata = file_get_contents(BACKUPTMPDIR . '/metadata.json');
		$meta = json_decode($metadata, true);

		$tar->close();

		unset($tar);
		return $meta;
	}
}
