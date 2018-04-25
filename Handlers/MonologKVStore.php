<?php
namespace FreePBX\modules\Backup\Handlers;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

class MonologKVStore extends AbstractProcessingHandler{
    private $module;
    public function __construct($moduleObj, $level = Logger::INFO, $bubble = true){
        $this->module = $moduleObj;
        parent::__construct($level,$bubble);
    }
    protected function write(array $record){
        $this->module->setConfig($record['datetime']->format('U'),$record,'monolog');
    }

}