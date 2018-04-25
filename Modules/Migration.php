<?php
namespace FreePBX\modules\Backup\Modules;

class Migration(){
    public function __construct($freepbx=''){
        $this->freepbx = $freepbx;
        if(empty($freepbx)){
            throw \InvalidArgumentException("You must provide a FreePBX object");
        }
    }
}