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
use Symfony\Component\Process\Process;
use modgettext;
abstract class Common extends \FreePBX\modules\Backup\Handlers\CommonFile {
	protected $tar;
	protected $filename;
	protected $defaultFallback = false;

	public function __construct($freepbx, $filePath, $transactionId, $pid){
		parent::__construct($freepbx, $filePath, $transactionId, $pid);
		$this->filePath = $filePath;
	}

	public function setnametodb($transactionId,$buid,$filename) {
		$arr = array("backupid"=> $buid,"filename"=> $filename);
		$this->freepbx->Backup->SetConfig($transactionId,$arr,"filenames");
	}
	/**
	 * Set default Fallback flag
	 *
	 * @param boolean $value
	 * @return void
	 */
	public function setDefaultFallback($value) {
		$this->defaultFallback = !empty($value);
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
		$this->fs->mkdir(dirname($this->file));
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

	protected function processModule($id, $module) {
		$this->log(sprintf(_("Working with %s module"), $module['rawname']));
		// Skip modules backup if system is not activated
		$skipModule = array("vqplus");
		if(!defined('ZEND_LICENSE_LOADED') && in_array($module['rawname'], $skipModule)) {
			$msg = sprintf(_("System is not Activated,Skipping %s module"),$module['rawname']);
			$this->log($msg,'WARNING');
			$this->addWarning($msg);
			return [];
		}
		//check to make sure the module supports backup
		if($module['rawname'] === 'framework') {
			$class = 'FreePBX\Builtin\Backup';
		} else {
			$class = sprintf('\\FreePBX\\modules\\%s\\Backup', $module['ucfirst']);
		}

		if(!class_exists($class)){
			$msg = sprintf(_("The module %s doesn't seem to support Backup"),$module['rawname']);
			$this->log($msg,'WARNING');
			$this->addWarning($msg);
			if(!$this->defaultFallback) {
				return [];
			}
			$this->log(_("Using default backup strategy"),'WARNING');
			$class = 'FreePBX\modules\Backup\BackupBase';
		}


		$modData = [
			"module" => $module['rawname'],
			"version" => null
		];

		//Ask the module for data
		$class = new $class($this->freepbx, $this->backupModVer, $this->getLogger(), $this->transactionId, $modData, $this->defaultFallback);
		try {
			$class->runBackup($id, $this->transactionId);
		}  catch (\RuntimeException $e) {
			$this->addError($e->getMessage());
		}catch ( \Exception $e ) {
			$this->addError($e->getMessage());
		}
		
		if ($class->getModified() === false) {
			$msg = sprintf(_("The module %s returned no data, No backup created"),$module['rawname']);
			$this->log("\t".$msg,'WARNING');
			$this->addWarning($msg);
			return [];
		}

		foreach ($class->getDirs() as $dir) {
			if (empty($dir)) {
				continue;
			}
			$fdir = $this->Backup->getPath('/' . ltrim($dir, '/'));
			$this->log("\t".sprintf(_('Adding directory to tar: %s'),$fdir),'DEBUG');
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
			if(file_exists($srcfile)) {
				$this->log("\t".sprintf(_('Adding file to tar: %s'),$destfile),'DEBUG');
				$this->tar->addFile($srcfile, $destfile);
			}
		}

		$modjson = $this->tmp . '/modulejson/' . $module['ucfirst'] . '.json';
		file_put_contents($modjson, json_encode($class->getData(), JSON_PRETTY_PRINT));
		$this->log("\t".sprintf(_('Adding module manifest for %s'),$module['rawname']),'DEBUG');
		$this->tar->addFile($modjson, 'modulejson/' . $module['ucfirst'] . '.json');

		return $class->getData();
	}

	public function closeFile() {
		$this->tar->close();
		unset($this->tar);
		$this->fs->rename($this->tmp .'/'. $this->filename, $this->file);
		$this->fs->remove($this->tmp);
	}

	public function addcustomFiles($cfiles) {
		$custfiles = json_decode($cfiles, true);
		foreach($custfiles as $cfvalue) {
			if($cfvalue['type'] == 'file') {
				$srcpath = $this->Backup->getPath($cfvalue['path']);
				if(file_exists($srcpath)) {
					$destpath = $this->Backup->getPath('customfiles/' . ltrim($srcpath, '/'));
					$this->log("\t".sprintf(_('Adding custom file to tar: %s'),$destpath),'DEBUG');
					if (!file_exists($srcpath)) {
						$this->log("\t".sprintf(_('%s does not exist'),$srcpath),'DEBUG');
					}else {
						$this->tar->addFile($srcpath, $destpath);
					}
				} else {
					$this->log("\t".sprintf(_('Custom file not exists: %s'),$srcpath),'DEBUG');
				}
			}
			if($cfvalue['type'] == 'dir') {
				$dir = $this->Backup->getPath($cfvalue['path']);
				if(is_dir($dir)) {
					$fdir = $this->Backup->getPath('customdir/' . ltrim($dir, '/'));
					$this->fs->mkdir($this->tmp . '/' . $fdir);
					$dst = $this->tmp . '/' . $fdir;
					$excludes = " --exclude='node_modules' ";
					$excludes .= "--exclude='*tgz' --exclude='*gpg' ";
					$excludes .= "--exclude='.git' ";
					if($cfvalue['exclude']) {
						if (!is_array($cfvalue['exclude'])) {
							$xArr = explode("\n", $cfvalue['exclude']);
						} else {
							$xArr = $cfvalue['exclude'];
						}
						foreach ($xArr as $x) {
							if ($x[0] === "/") {
								$excludes .= " --filter='-/ $x'";
							} else {
								$excludes .= " --exclude='$x'";
							}
						}
					}
					$cmd = fpbx_which('rsync')." $excludes -rlptgov $dir/ $dst/";
					$process= new Process($cmd);
					try {
						$process->setTimeout(null);
						$process->mustRun();
					} catch(ProcessFailedException $e) {
						$this->log(sprintf(_($e->getMessage()),'DEBUG'));
					}
					$this->log("\t".sprintf(_('Adding custom directory to tar: %s'),$fdir),'DEBUG');
					$fileList = $this->getFileList("$dst/");
					foreach($fileList as $file) {
						if (!file_exists($dst.'/'.$file)) {
							$this->log("\t".sprintf(_('Folder %s does not exist'),$file),'DEBUG');
						}else {
							$this->tar->addFile($dst.'/'.$file, $fdir.'/'.$file);
						}
					}
				} else {
					$this->log("\t".sprintf(_('Custom directory not exists: %s'),$dir),'DEBUG');
				}
			}
		}
	}

	private function recurseDirectory($dir, &$retarr, $strip) {
		$dirarr = scandir($dir);
		foreach ($dirarr as $d) {
			// Always exclude hidden files.
			if ($d[0] == ".") {
				continue;
			}
			$fullpath = "$dir/$d";
			if (is_dir($fullpath)) {
				$this->recurseDirectory($fullpath, $retarr, $strip);
			} else {
				$retarr[] = substr($fullpath, $strip);
			}
		}
	}

	private function getFileList($dir) {
		$retarr = array();
		$this->recurseDirectory($dir, $retarr, strlen($dir)+1);
		return $retarr;
	}

}
