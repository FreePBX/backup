<?php
/**
 * Copyright Sangoma Technologies Inc 2018
 */
namespace FreePBX\modules\Backup\Handlers;
use FreePBX\modules\Backup\Models as Model;
use FreePBX\modules\Backup\Handlers\FreePBXModule;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use splitbrain\PHPArchive\Tar;

class SingleRestore{
	public function __construct($file, $freepbx){
		$this->restoreFile = $file;
		$this->FreePBX = $freepbx;
		$this->Backup = $freepbx->Backup;
		$this->out = new ConsoleOutput();
		$this->progressBar = new ProgressBar($this->out, 4);
		$this->progressBar->setFormatDefinition('custom', ' %current%/%max% -- %message%'.PHP_EOL);
		$this->progressBar->setFormat('custom');

		define('BACKUPTMPDIR', '/var/spool/asterisk/tmp');
		define('REDTEXT', "\033[31m ");
		define('GREENTEXT', "\033[32m ");
		define('WHITETEXT', "\033[0m ");
	}

	public function doSingleRestore(){
		$this->Backup->fs->remove(BACKUPTMPDIR);
		$this->Backup->fs->mkdir(BACKUPTMPDIR);

		$tar = new Tar();
		$tar->open($this->restoreFile);
		$tar->extract(BACKUPTMPDIR);

		$metadata = file_get_contents(BACKUPTMPDIR . '/metadata.json');
		$restoreData = json_decode($metadata, true)

		$moduleInfo = $restoreData['module'];
		foreach ($moduleInfo as $key => $value) {
			if($key === 'builtin'){
				continue;
			}
			$moduleName = $key;
			$moduleInfo = $value;
			break;
		}

		$modJson = BACKUPTMPDIR . '/modulejson/' . ucfirst($moduleName) . '.json';
		if(!file_exists($modJson)){
			exit(sprintf(REDTEXT ._("Can't find the module data for %s").PHP_EOL.WHITETEXT,$moduleName));
		}
		$modData = json_decode(file_get_contents($modJson), true);
		$restore = new Model\Restore($this->Backup->FreePBX, $modData);    
		echo REDTEXT."***"._("In single restore mode dependencies are NOT processed")."***".PHP_EOL.WHITETEXT;
		$modulehandler = new FreePBXModule($this->FreePBX);
		\modgettext::push_textdomain($moduleName);
		$this->progressBar->setMessage(GREENTEXT._('Resetting module data, depending on the module it may take a bit.').WHITETEXT);
		$this->progressBar->start();
		$modulehandler->reset($moduleName, $moduleInfo['version']);
		$this->progressBar->advance();
		$class = sprintf('\\FreePBX\\modules\\%s\\Restore', ucfirst($moduleName));
		$class = new $class($restore, $this->FreePBX, BACKUPTMPDIR);
		$this->progressBar->advance();
		$this->progressBar->setMessage(GREENTEXT . _('Restoring Data...') . WHITETEXT);
		$class->runRestore('');
		$this->progressBar->advance();
		$this->progressBar->setMessage(GREENTEXT . _('Finished...') . WHITETEXT);
		$this->progressBar->finish();
		needreload();
		$this->out->writeln("Please run fwconsole reload");
	}
}
