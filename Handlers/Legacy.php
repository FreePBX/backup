<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 * Handle legacy backup files
 */
namespace FreePBX\modules\Backup\Handlers;
use FreePBX\modules\Backup\Modules as Module;
use PDO;
class Legacy{
    public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \InvalidArgumentException('Not given a BMO Object');
		}
		$this->FreePBX = $freepbx;
        $this->Backup = $freepbx->Backup;
        $webrootpath = $this->FreePBX->Config->get('AMPWEBROOT');
        $webrootpath = (isset($webrootpath) && !empty($webrootpath)) ? $webrootpath : '/var/www/html';
        $this->data = [];

        define('WEBROOT', $webrootpath);
        define('BACKUPTMPDIR', '/var/spool/asterisk/tmp');
    }
    public function process($restore, $job, $warmspare){
        $this->extractFile($restore);
        $this->buildData($restore);
        $this->parseSQL();
    }
    public function getModuleTables(){
        $moduleManager = new FreePBXModule($this->FreePBX);
        $amodules = $this->FreePBX->Modules->getActiveModules();
        foreach ($amodules as $mod => $data) {
            $modTables = $moduleManager->getTables($mod);
            foreach ($modTables as $table) {
                $this->moduleData['tables'][$table] = $mod;
            }
        }
        return $this->moduleData['tables'];
    }
    public function buildData(){
        $this->data['manifest'] = [];
        $this->data['astdb'] = [];
        if(file_exists(BACKUPTMPDIR . '/manifest')){
            $this->data['manifest'] = unserialize(file_get_contents(BACKUPTMPDIR.'/manifest'));
        }
        if(file_exists(BACKUPTMPDIR . '/astdb')){
            $this->data['astdb'] = unserialize(file_get_contents(BACKUPTMPDIR.'/astdb'));
        }
    }
    
    public function extractFile($filepath){
        $this->Backup->fs->remove(BACKUPTMPDIR);
        $this->Backup->fs->mkdir(BACKUPTMPDIR);
        //We have to go the exec route because legacy backups root is ./ which breaks things
        exec('tar -xzvf '.$filepath.' -C '.BACKUPTMPDIR, $out, $ret);
        return $ret;
    }
    
    public function parseSQL(){
        $tables = $this->getModuleTables();
        $files = [];
        foreach (glob(BACKUPTMPDIR."/*.sql.gz") as $filename) {
            $files[] = $filename;
        }
        foreach($files as $file){
            $amodules = $this->FreePBX->Modules->getActiveModules();
            foreach ($amodules as $key => $value) {
                $final[$key] = [];
            }
            $pdo = $this->setupTempDb($file);
            $loadedTables = $pdo->query("SHOW TABLES");
            $final = ['unknown' => []];
            while ($current = $loadedTables->fetch(PDO::FETCH_COLUMN)) {
                if(!isset($tables[$current])){
                    $final['unknown'][] = $current;
                    continue;
                }
                $final[$tables[$current]][] = $current;
            }

            foreach ($final as $key => $value) {
                if($key === 'unknown' || $key === 'framework'){
                    continue;
                }
                $namespace = '\\FreePBX\\modules\\'.ucfirst($key).'\\Restore';
                if(!class_exists($namespace)){
                    continue;
                }
                $class = new $namespace(null,$this->FreePBX, BACKUPTMPDIR);
                if(method_exists($class,'processLegacy')){
                    echo sprintf(_("Calling legacy restore on module %s".PHP_EOL),$key);
                    $class->processLegacy($pdo, $this->data, $value, $final['unknown'],BACKUPTMPDIR);
                    unset($class);
                    continue;
                }
                echo sprintf(_("The module %s does not seem to support legacy restores." . PHP_EOL), $key);
            }
        }
    }
    public function setupTempDb($file){
        exec('mysqladmin -f DROP asterisktemp', $out, $ret);
        exec('mysqladmin CREATE asterisktemp', $out, $ret);
        exec('gunzip < '.$file.'  | mysql asterisktemp', $out, $ret);
        $host = '127.0.0.1';
        $db = 'asterisktemp';
        $user = 'root';
        $pass = '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $this->tempDB = new PDO($dsn, $user, $pass, $opt);
        return $this->tempDB;
    }
}
