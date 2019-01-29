<?php
namespace FreePBX\modules\Backup;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
    public function runRestore($jobid){
        $settings = $this->getConfigs();
        foreach ($settings as $key => $value) {
            $this->freepbx->Backup->setMultiConfig($value, $key);
        }
    }
  public function processLegacy($pdo, $data, $tables, $unknownTables, $tmpfiledir){
    return $this->transformLegacyKV($pdo,'backup', $this->freepbx)
                ->transformNamespacedKV($pdo,'backup', $this->freepbx);
  }

}
