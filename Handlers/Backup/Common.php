<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules\Backup\Handlers\Backup;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
use splitbrain\PHPArchive\Tar;
use FreePBX\modules\Backup\Handlers\FreePBXModule;
use modgettext;
abstract class Common extends \FreePBX\modules\Backup\Handlers\CommonBase {
	protected $tar;
	protected $filename;

	public function __construct($freepbx, $filePath, $transactionId, $pid){
		parent::__construct($freepbx, $filePath, $transactionId, $pid);
		$this->filePath = $filePath;
	}

	public function setFilename($filename) {
		$this->filename = $filename;
		$this->file = $this->filePath.'/'.$this->filename;
	}

	public function getFile() {
		return $this->file;
	}

	public function process() {
		throw new \Exception("Nothing to process!");
	}

	protected function openFile() {
		//setup and clean out the singlebackup folder
		$this->fs->remove($this->tmp);
		$this->fs->mkdir($this->tmp);

		//open the tarball for writing
		$this->tar = new Tar();
		$this->tar->setCompression(9, Tar::COMPRESS_GZIP);
		$this->tar->create($this->tmp .'/'. $this->filename);

		$this->fs->mkdir($this->tmp . '/modulejson');
		$this->tar->addFile($this->tmp . '/modulejson', 'modulejson');

		$this->fs->mkdir($this->tmp . '/files');
		$this->tar->addFile($this->tmp . '/files', 'files');

		return $this->tar;
	}

	protected function processModule($module) {
		$this->log(sprintf(_("Working with %s module"), $module));
		//check to make sure the module supports backup
		$class = sprintf('\\FreePBX\\modules\\%s\\Backup', ucfirst($module));
		if(!class_exists($class)){
			$this->log("\t".sprintf(_("The module %s doesn't seem to support Backup, no backup created"), $module));
			return [];
		}

		//Ask the module for data
		$class = new $class($this->freepbx, $this->backupModVer);

		$class->runBackup($this->transactionId, 'tarnamebase');
		if ($class->getModified() === false) {
			$this->log("\t".sprintf(_("The module %s returned no data, No backup created"), $module));
			return [];
		}

		foreach ($class->getDirs() as $dir) {
			if (empty($dir)) {
				continue;
			}
			$fdir = $this->Backup->getPath('/' . ltrim($dir, '/'));
			$this->log("\t".sprintf(_('Adding directory to tar: %s'),$fdir));
			$this->fs->mkdir($this->tmp . '/' . $fdir);
			$this->tar->addFile($this->tmp . '/' . $fdir, $fdir);
		}

		foreach ($class->getFiles() as $file) {
			$srcpath = isset($file['pathto']) ? $file['pathto'] : '';
			if (empty($srcpath)) {
				continue;
			}
			$srcfile = $srcpath . '/' . $file['filename'];
			$destpath = $this->Backup->getPath('files/' . ltrim($file['pathto'], '/'));
			$destfile = $destpath .'/'. $file['filename'];
			$files[$srcfile] = $destfile;
			$this->log("\t".sprintf(_('Adding file to tar: %s'),$destfile));
			$this->tar->addFile($srcfile, $destfile);
		}

		$modjson = $this->tmp . '/modulejson/' . ucfirst($module) . '.json';
		file_put_contents($modjson, json_encode($class->getData(), JSON_PRETTY_PRINT));
		$this->log("\t".sprintf(_('Adding module manifest for %s'),$module));
		$this->tar->addFile($modjson, 'modulejson/' . ucfirst($module) . '.json');

		return $class->getData();
	}

	public function closeFile() {
		$this->tar->close();
		unset($this->tar);
		$this->fs->rename($this->tmp .'/'. $this->filename, $this->file);
	}
}