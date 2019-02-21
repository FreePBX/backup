<?php
/**
* Copyright Sangoma Technologies, Inc 2018
*/
namespace FreePBX\modules\Backup\Handlers;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class FreePBXModule{
	public $moduleXML = false;
	public function __construct($freepbx) {
		$this->freepbx = $freepbx;
		$this->mf = \module_functions::create();
	}
	public function reset($module,$version){
		$developer = $this->freepbx->Config->get('DEVEL');
		$module = \strtolower($module);
		$info = $this->mf->getInfo($module, false, true);
		if(!empty($info[$module]) && ($info[$module]['status'] === MODULE_STATUS_ENABLED)) {
			$uninstall = $this->uninstall($module);
		}
		$install = $this->install($module);
		return $this;
	}

	public function install($module){
		$install = $this->mf->install($module, 'true');
		if(is_array($install)){
			throw new \Exception(sprintf(_('Error installing %s reason(s): %s'),$module,implode(",",$install)));
		}
		/*
		$process = new Process(['fwconsole', 'ma', 'install', $module, '--force']);
		$process->mustRun();
		*/
		return true;
	}

	public function uninstall($module){
		$uninstall = $this->mf->uninstall($module, 'true');
		if(is_array($uninstall)){
			throw new \Exception(sprintf(_('Error uninstalling %s reason(s): %s'),$module,implode(",",$uninstall)));
		}
		/*
		$process = new Process(['fwconsole', 'ma', 'uninstall', $module, '--force']);
		$process->mustRun();
		*/
		return true;
	}

	public function getTables($module){
		$tables = [];
		$this->loadModuleXML($module);
		if (!$this->moduleXML) {
			return [];
		}
		$moduleTables = $this->moduleXML->database->table;
		if(!$moduleTables){
			return [];
		}
		foreach ($moduleTables as $table) {
			$tname = (string)$table->attributes()->name;
			$tables[] = $tname;
		}
		return $tables;
	}

	public function loadModuleXML($module){
		if($this->ModuleXML){
			return $this;
		}
		$dir = $this->freepbx->Config->get('AMPWEBROOT') . '/admin/modules/' . $module;
		if(!file_exists($dir.'/module.xml')){
			$this->moduleXML = false;
			return $this;
		}
		$xml = simplexml_load_file($dir . '/module.xml');
		$this->moduleXML = $xml;
		return $this;
	}

	public function getModuleVersion($module){
		$this->loadModuleXML($module);
		if(!$this->moduleXML){
			return '';
		}
		return (string)$this->moduleXML->attributes()->version;
	}
}
