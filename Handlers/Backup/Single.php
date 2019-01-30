<?php
/**
 * Copyright Sangoma Technologies 2018
 */
namespace FreePBX\modules\Backup\Handlers\Backup;
use Symfony\Component\Console\Output\ConsoleOutput;
use splitbrain\PHPArchive\Tar;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
/**
 * Class used for Single Module Backup
 */
class Single{

	/**
	 * Construct
	 *
	 * @param FreePBX $freepbx The FreePBX BMO Object
	 * @param string $module The module rawname to backup
	 * @param string $savePath The save path directory
	 */
	public function __construct($freepbx, $module, $savePath = '', $jobid){
		$this->freepbx = $freepbx;
		$this->Backup = $freepbx->Backup;
		$this->module = strtolower($module);
		$this->out = new ConsoleOutput();
		$this->savePath = !empty($savePath) ? $savePath : rtrim(getcwd());
		$this->$jobid = $jobid;
	}

	public function process(){
		if(!is_dir($this->savePath)) {
			throw new \Exception(sprintf(_('%s is not a valid directory'),$this->savePath));
		}

		$moduleInfo = $this->freepbx->Modules->getInfo($this->module)[$this->module];
		if(empty($moduleInfo)) {
			throw new \Exception(sprintf(_('Unknown module %s'),$this->module));
		}

		//check to make sure the module supports backup
		$class = sprintf('\\FreePBX\\modules\\%s\\Backup', ucfirst($this->module));
		if(!class_exists($class)){
			$this->out->writeln('<error>'.sprintf(_("The module %s doesn't seem to support Backup, no backup created"), $this->module).'</error>');
			exit();
		}

		//setup and clean out the singlebackup folder
		$tmpdir = sys_get_temp_dir().'/singlebackup';
		$this->Backup->fs->remove($tmpdir);
		$this->freepbx->Backup->fs->mkdir($tmpdir);

		//setup tarball format
		$tarfilename = sprintf('%s-%s%s-%s-%s', $this->module, date("Ymd-His-"), time(), $moduleInfo['version'], rand());
		$tarnamebase = sprintf('%s/%s', $tmpdir, $tarfilename);
		$targzname = sprintf('%s.tar.gz', $tarfilename);

		//open the tarball for writing
		$tar = new Tar();
		$tar->setCompression(9, Tar::COMPRESS_GZIP);
		$tar->create($tmpdir .'/'. $targzname);
		$this->freepbx->Backup->fs->mkdir($tmpdir . '/modulejson');
		$this->freepbx->Backup->fs->mkdir($tmpdir . '/files');
		$tar->addFile($tmpdir . '/modulejson', 'modulejson');
		$tar->addFile($tmpdir . '/files', 'files');

		//Ask the module for data
		$class = new $class($this->freepbx);
		$class->runBackup($this->jobid, 'tarnamebase');
		if ($class->getModified() === false) {
			$this->out->writeln('<error>'.sprintf(_("The module %s returned no data, No backup created"), $this->module).'</error>');
			exit();
		}
		$dependencies = $class->getDependencies();
		foreach ($dependencies as $value) {
			if (empty($value)) {
				continue;
			}
			$this->out->writeln(sprintf(_("The module %s says it depends on %s which will not be backed up in this mode"),$this->module, $value));
		}
		$moddata = $class->getData();
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
			foreach ($dirs as $dir) {
				$this->freepbx->Backup->fs->mkdir($tmpdir . '/' . $dir);
				$tar->addFile($tmpdir . '/' . $dir, $dir);
			}
		}
		$manifest = array(
			'moddata' => $moddata,
			'module' => $moduleInfo
		);
		$tar->addData('metadata.json', json_encode($manifest, JSON_PRETTY_PRINT));
		$tar->close();
		unset($tar);
		$this->freepbx->Backup->fs->rename($tmpdir .'/'. $targzname, $this->savePath .'/'. $targzname);
		exit(sprintf("Your backup can be found at: %s".PHP_EOL, $this->savePath .'/'. $targzname));
	}

}
