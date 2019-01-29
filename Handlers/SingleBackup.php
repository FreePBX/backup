<?php
/**
 * Copyright Sangoma Technologies 2018
 */
namespace FreePBX\modules\Backup\Handlers;
use splitbrain\PHPArchive\Tar;
use FreePBX\modules\Backup\Models\Backup as BackupObj;


class SingleBackup{

	public function __construct($module, $FreePBX, $savePath = ''){
		$this->freepbx = $FreePBX;
		$this->module = $module;
		$this->savePath = $savePath;
		$this->backupObj = new BackupObj($this->freepbx);
	}

	public function doSingleBackup(){
		$tmpDir = '/tmp/SingleBackup';
		@unlink($tmpDir);
		$backup = $this->backupObj;
		$this->freepbx->Backup->fs->mkdir($tmpDir);
		$moduleInfo = $this->freepbx->Modules->getInfo(strtolower($this->module));
		$tarfilename = sprintf('%s-%s%s-%s-%s', $this->module, date("Ymd-His-"), time(), $moduleInfo[strtolower($this->module)]['version'], rand());
		$tarnamebase = sprintf('%s/%s', $tmpdir, $tarfilename);
		$targzname = sprintf('%s.tar.gz', $tarfilename);

		$tar = new Tar();
		$tar->create($tmpdir .'/'. $targzname);
		$this->freepbx->Backup->fs->mkdir($tmpdir . '/modulejson');
		$this->freepbx->Backup->fs->mkdir($tmpdir . '/files');
		$tar->addFile($tmpdir . '/modulejson', 'modulejson');
		$tar->addFile($tmpdir . '/files', 'files');

		$class = sprintf('\\FreePBX\\modules\\%s\\Backup', ucfirst($this->module));
		if(!class_exists($class)){
			exit(sprintf("The module %s doesn't seem to support Backup, no backup created".PHP_EOL, $this->module));
		}
		$class = new $class($backup, $this->freepbx);
		$class->runBackup(1, 'tarnamebase');
		if ($backup->getModified() === false) {
			exit(sprintf("The module %s returned no data, No backup created".PHP_EOL,$this->module));
		}
		$dependencies = $backup->getDependencies();
		foreach ($dependencies as $value) {
			if (empty($value)) {
				continue;
			}
			echo sprintf("The module %s says it depends on %s which will not be backed up in this mode".PHP_EOL,$this->module, $value);
		}
		$moddata = $backup->getData();
		foreach ($moddata['dirs'] as $dir) {
			if (empty($dir)) {
				continue;
			}
			$fdir = $this->freepbx->Backup->getPath('/' . ltrim($dir, '/'));
			$dirs[] = $fdir;

		}
		foreach ($moddata['files'] as $file) {
			$srcpath = isset($file['pathto']) ? $file['pathto'] : '';
			if (empty($srcpath)) {
				continue;
			}
			$srcfile = $srcpath . '/' . $file['filename'];
			$destpath = $this->freepbx->Backup->getPath('files/' . ltrim($file['pathto'], '/'));
			$destfile = $destpath .'/'. $file['filename'];
			$dirs[] = $destpath;
			$files[$srcfile] = $destfile;
			$tar->addFile($srcfile, $destfile);
		}
		$modjson = $tmpdir . '/modulejson/' . ucfirst($this->module) . '.json';
		if (!$this->freepbx->Backup->fs->exists(dirname($modjson))) {
			$this->freepbx->Backup->fs->mkdir(dirname($modjson));
		}
		file_put_contents($modjson, json_encode($moddata, JSON_PRETTY_PRINT));
		$tar->addFile($modjson, 'modulejson/' . ucfirst($this->module) . '.json');
		$data = $moddata;
		$cleanup = $moddata['garbage'];
		if (is_array($dirs)) {
			$this->freepbx->Backup->fs->mkdir('/tmp/SingleBackup'.$dir);

			foreach ($dirs as $dir) {
				$tar->addFile('/tmp/SingleBackup'.$dir, $dir);
			}
		}
		$manifest = array(
			'moddata' => $moddata,
			'module' => $moduleInfo
		);
		$tar->addData('metadata.json', json_encode($manifest));
		$tar->close();
		unset($tar);
		if(empty($this->savePath)){
			$this->savePath = rtrim(getcwd());
		}
		$this->freepbx->Backup->fs->rename($tmpdir .'/'. $targzname, $this->savePath .'/'. $targzname);
		exit(sprintf("Your backup can be found at: %s".PHP_EOL, $this->savePath .'/'. $targzname));
	}

}
