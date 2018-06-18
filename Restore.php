<?php
namespace FreePBX\modules\Backup;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
    public function runRestore($jobid){
        $settings = $this->getConfigs();
        foreach ($settings as $key => $value) {
            $this->FreePBX->Backup->setMultiConfig($value, $key);
        }
    }
}