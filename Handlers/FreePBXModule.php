<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules\Backup\Handlers;

class FreePBXModule{
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception('Not given a FreePBX Object');
		}
		$this->FreePBX = $freepbx;
		$this->mf = \module_functions::create();
	}
	public function reset($module,$version){
		$developer = $this->FreePBX->Config->get('DEVEL');
		$module = \strtolower($module);
		$xml = $this->mf->getModuleDownloadByModuleNameAndVersion($module, $version);
		if(!empty($xml) && !$developer && $xml['version'] !== $version){
			$this->processRemote($xml);
		}

		$uninstall = $this->uninstall($module);
		$install = $this->install($module);
		return ($uninstall && $install);
	}
	public function processRemote($xml){
		$module = $xml['rawname'];
		$download =  $this->mf->handledownload($xml['downloadurl']);
		if(is_array($download)){
			return false;
		}
		return true;
	}
	public function install($module){
		$install = $this->mf->install($module, 'true');
		if(is_array($install)){
			return false;
		}
		return true;
	}

	public function uninstall($module){
		$uninstall = $this->mf->uninstall($module, 'true');
		if(is_array($uninstall)){
			return false;
		}
		return true;
	}
}
