<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules\Backup\Handlers;

class FreePBXModule{
    public $moduleXML = false;
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
		if($this->getModuleVersion($module) !== $version && !$developer){
            $xml = $this->mf->getModuleDownloadByModuleNameAndVersion($module, $version);
			$this->processRemote($xml);
        }
        $uninstall = $this->uninstall($module);
        $install = $this->install($module);
		return $this;
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

    //future functionality for resetting database and astdb

    public function truncateTables($module){
        $tables = $this->getTables($module);
        $stmt = $this->FreePBX->Database->prepare('TRUNCATE TABLE :table');
        foreach($tables as $table){
            $stmt->execute([':table' => $table]);
        }
        return $this;
    }
    //future functionality clean data
    public function cleanAstdb($module){
        if(!$this->FreePBX->astman->connected()){
            return false;
        }
        $keys = $this->getAstdb($module);
        foreach ($keys as $key) {
            $this->FreePBX->astman->database_deltree($key);
        }
        return $this;
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
    
    public function getAstdb($module){
        $keys = [];
        $this->loadModuleXML($module);
        if(!$this->moduleXML){
            return [];
        }
        foreach ($this->moduleXML->astdb->key as $key) {
            $kname = (string)$table->attributes()->name;
            $keys[] = $kname;
        }
        return $keys;
    }
    public function loadModuleXML($module){
        if($this->ModuleXML){
            return $this;
        }
        $dir = $this->FreePBX->Config->get('AMPWEBROOT') . '/admin/modules/' . $module;
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
