<?php
namespace FreePBX\modules\Backup\Modules;

class Servers extends Migration{
    public function migrate(){
        $stmt = $this->freepbx->db->query('SELECT `server_id`,`key`,`value` FROM backup_server_details');
		$servers = array();
		while($item = $stmt->fetch(\PDO::FETCH_ASSOC)){
		 $servers[$item['server_id']][$item['key']] = $item['value'];
		}
		$stmt = $this->db->query('SELECT * FROM backup_servers');
		while($item = $stmt->fetch(\PDO::FETCH_ASSOC)){
		 $servers[$item['id']]['name'] = $item['name'];
		 $servers[$item['id']]['description'] = $item['desc'];
		 $servers[$item['id']]['type'] = $item['type'];
		 $servers[$item['id']]['immortal'] = !is_null($item['immortal']);
		 $servers[$item['id']]['readonly'] = !is_null($item['readonly']);
		}
    }
    private function handleFTP($data){
        
    }
    private function handleSSH($data){
        
    }
    private function handleMySQL($data){
        
    }
    private function handleEmail($data){
        
    }
    private function handleLocal($data){
        
    }
    private function handleS3($data){
        
    }
    private function markMigrated($id,$newid){}
    
    
}