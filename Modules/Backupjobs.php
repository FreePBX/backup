<?php
namespace FreePBX\modules\Backup\Modules;

class Backupjobs extends Migration{
    public function migrate(){
        $backups = $this->getLegacyBackups();
        foreach($backups as $backup){

        }
    }
    
    private function getLegacyBackups(){
        $sql = 'SELECT * FROM backup ORDER BY name';
        $ret = $this->freepbx->Database->query($sql,\PDO::FETCH_ASSOC);
        $backups = array();
        //set index to server id for easy retrieval
        $stmt = $this->freepbx->Database->prepare('SELECT * FROM backup WHERE id = ?');
        foreach ($ret as $s) {
            //set index to  id for easy retrieval
            $backups[$s['id']] = $s;
            //default name in one is missing
            if (!$backups[$s['id']]['name']) {
                $backups[$s['id']]['name'] = _('Backup') . ' ' . $s['id'];
            }
            $result = $stmt->execute([$s['id']]);
            $budetails = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach($budetails as $value){
                $backups[$s['id']][$value['key']] = $value['value'];
            }
        }
        return $backups;
    }
    private function setMigrated($old,$new){
        $sql = 'UPDATE backup set migrated = :new WHERE id = :old';
        $stmt = $this->freepbx->Database->prepare($sql);
        return $stmt->execute([':old' => $old, ':new' => $new]);
    }
}
