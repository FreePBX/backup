<?php
/**
 * Copyright Sangoma Technologies Inc 2018
 */
namespace FreePBX\modules\Backup\Handlers;
use FreePBX\modules\Backup\Handlers\FreePBXModule;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use splitbrain\PHPArchive\Tar;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
/**
 * Class used for a single module restore
 */
class Single extends Common {
	private $restoreFile;
	private $freepbx;
	private $Backup;
	private $jobid;
	private $out;
	private $backuptmpdir;

	/**
	 * Construct
	 *
	 * @param FreePBX $freepbx The FreePBX BMO Object
	 * @param string $file The file to restore from
	 * @param string|null $jobid The job ID
	 */
	public function __construct($freepbx, $file, $jobid){
		$this->restoreFile = $file;
		$this->freepbx = $freepbx;
		$this->Backup = $freepbx->Backup;
		$this->jobid = $jobid;
		$this->out = new ConsoleOutput();
		$this->backuptmpdir = $this->freepbx->Config->get('ASTSPOOLDIR').'/tmp';
	}

	/**
	 * Process the single restore
	 *
	 * @return void
	 */
	public function process() {
		//remove backup tmp dir for extraction
		$this->Backup->fs->remove($this->backuptmpdir);

		//add tmp dir back
		$this->Backup->fs->mkdir($this->backuptmpdir);

		//prepare to extract file
		$tar = new Tar();
		$tar->open($this->restoreFile);
		$tar->extract($this->backuptmpdir);

		//attempt to read manifest from tar
		$metapath = $this->backuptmpdir . '/metadata.json';
		if(!file_exists($metapath)){
			throw new \Exception(_("Could not locate the manifest for this file"));
		}
		$restoreData = json_decode(file_get_contents($metapath), true);

		//Determine module from single restore file
		$moduleInfo = $restoreData['module'];
		$moduleName = $moduleInfo['rawname'];

		//Get the version of the backup module currently in use
		$backupModVer = (string)$this->freepbx->Modules->getInfo('backup')['backup']['version'];

		//Get module specific manifest
		$modJsonPath = $this->backuptmpdir . '/modulejson/' . ucfirst($moduleName) . '.json';
		if(!file_exists($modJsonPath)){
			throw new \Exception(sprintf(_("Can't find the module data for %s"),$moduleName));
		}
		$modData = json_decode(file_get_contents($modJsonPath), true);

		$this->out->writeln("<error>***"._("In single restores mode dependencies are NOT processed")."***</error>");

		//Load the FreePBX Module Handler
		$modulehandler = new FreePBXModule($this->freepbx);

		//Change the Text Domain
		$this->out->writeln('<info>'.sprintf(_('Resetting %s module data'),$moduleName).'</info>');
		\modgettext::push_textdomain($moduleName);
		$modulehandler->reset($moduleName, $moduleInfo['version']);
		$class = sprintf('\\FreePBX\\modules\\%s\\Restore', ucfirst($moduleName));
		$class = new $class($this->freepbx, $modData, $backupModVer, $this->backuptmpdir);
		\modgettext::pop_textdomain();
		$this->out->write('<info>' . _('Restoring Data...') . '</info>');
		\modgettext::push_textdomain($moduleName);
		$this->out->writeln('<info>' . _('Done') . '</info>');
		$class->runRestore($this->jobid);
		\modgettext::pop_textdomain();
		$this->out->writeln('<info>' . _('Finished') . '</info>');
		needreload();
	}
}
