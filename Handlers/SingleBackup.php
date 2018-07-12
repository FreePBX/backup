<?php
/**
 * Copyright Sangoma Technologies 2018
 */
namespace FreePBX\modules\Backup\Handlers;
use Phar;
use PharData;
use FreePBX\modules\Backup\Models\Backup as BackupObj;


class SingleBackup{

    public function __construct($module, $FreePBX, $savePath = ''){
        $this->FreePBX = $FreePBX;
        $this->module = $module;
        $this->savePath = $savePath;
        $this->backupObj = new BackupObj($this->FreePBX);
    }

    public function doSingleBackup(){
        $tmpDir = '/tmp/SingleBackup';
        @unlink($tmpDir);
        $backup = $this->backupObj;
        $this->FreePBX->Backup->fs->mkdir($tmpDir);
        $moduleInfo = $this->FreePBX->Modules->getInfo(strtolower($this->module));
        $pharfilename = sprintf('%s-%s%s-%s-%s', $this->module, date("Ymd-His-"), time(), $moduleInfo[strtolower($this->module)]['version'], rand());
        $pharnamebase = sprintf('%s/%s', $tmpDir, $pharfilename);
        $phargzname = sprintf('%s.tar.gz', $pharnamebase);
        $pharname = sprintf('%s.tar', $pharnamebase);
        $phar = new PharData($pharname);
        $phar->addEmptyDir('/modulejson');
        $phar->addEmptyDir('/files');
        $phar->setSignatureAlgorithm(Phar::SHA256);
        $class = sprintf('\\FreePBX\\modules\\%s\\Backup', ucfirst($this->module));
        if(!class_exists($class)){
            exit(sprintf("The module %s doesn't seem to support Backup, no backup created".PHP_EOL, $this->module));
        }
        $class = new $class($backup, $this->FreePBX);
        $class->runBackup(1, 'pharnamebase');
        if ($backup->getModified() === false) {
            exit(sprintf("The module %s returned no data, No backup created".PHP_EOL,$this->module));
        }
        $dependencies = $backup->getDependencies();
        foreach ($dependencies as $value) {
            echo sprintf("The module %s says it depends on %s which will not be backed up in this mode".PHP_EOL,$this->module, $value);
        }
        $moddata = $backup->getData();
        foreach ($moddata['dirs'] as $dir) {
            if (empty($dir)) {
                continue;
            }
            $dirs[] = $this->FreePBX->Backup->getPath('files/' . ltrim($dir, '/'));
        }
        foreach ($moddata['files'] as $file) {
            $srcpath = isset($file['pathto']) ? $file['pathto'] : '';
            if (empty($srcpath)) {
                continue;
            }
            $srcfile = $srcpath . '/' . $file['filename'];
            $destpath = $this->FreePBX->Backup->getPath('files/' . ltrim($file['pathto'], '/'));
            $destfile = $destpath . $file['filename'];
            $dirs[] = $destpath;
            $files[$srcfile] = $destfile;
            $phar->addFile($srcfile, $destfile);
        }
        $modjson = $tmpdir . '/modulejson/' . ucfirst($this->module) . '.json';
        if (!$this->FreePBX->Backup->fs->exists(dirname($modjson))) {
            $this->FreePBX->Backup->fs->mkdir(dirname($modjson));
        }
        file_put_contents($modjson, json_encode($moddata, JSON_PRETTY_PRINT));
        $phar->addFile($modjson, 'modulejson/' . $mod['name'] . '.json');
        $data = $moddata;
        $cleanup = $moddata['garbage'];
        if (is_array($dirs)) {
            foreach ($dirs as $dir) {
                $phar->addEmptyDir($dir);
            }
        }
        $phar->setMetadata(['moddata' => $moddata, 'module' => $moduleInfo]);
        $phar->compress(Phar::GZ);
        $signatures = $phar->getSignature();
        unset($phar);
        $this->FreePBX->Backup->fs->rename($pharname, $phargzname);
        @unlink($pharname);
        if(empty($this->savePath)){
            $this->savePath = rtrim(getcwd());
        }
        $this->FreePBX->Backup->fs->rename($phargzname, $this->savePath .'/'. $pharfilename . '.tar.gz');
        @unlink($phargzname);
        exit(sprintf("Your backup can be found at: %s".PHP_EOL, $this->savePath .'/'. $pharfilename . '.tar.gz'));
    }

}