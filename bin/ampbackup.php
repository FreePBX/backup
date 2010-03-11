#!/usr/bin/env php
<?php
/*
 ampbackup.php Copyright (C) 2009 Moshe Brevda
 original perl version (C) 2004 Coalescent Systems Inc. (info@coalescentsystems.ca)

 this program is in charge of looking into the database to pick up the backup sets name and options
 Then it creates the tar files and places them in the /var/lib/asterisk/backups folder

 The program if run from asterisk users crontab it is run as ampbackup.php <Backup Job Record Number in Mysql> 
 OR
 The program is called from the backup.php script and implemented immediately as such:
 ampbackup.php <Backup_Name> <Backup_Voicemail_(yes/no)> <Backup_Recordings_(yes/no)> <Backup_Configuration_files(yes/no)> 
 <Backup_CDR_(yes/no)> <Backup_FOP_(yes/no)

 example ampbackup.php "My_Nightly_Backup" yes yes no no yes


 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
*/


//get options
$amp_conf=getconf((isset($_ENV['FREEPBXCONFIG']) && strlen($_ENV['FREEPBXCONFIG']))?$_ENV['FREEPBXCONFIG']:'/etc/amportal.conf');
$ast_conf=getconf((isset($_ENV['ASTERISKCONFIG']) && strlen($_ENV['ASTERISKCONFIG']))?$_ENV['ASTERISKCONFIG']:'/etc/asterisk/asterisk.conf');

//connect to database
include_once('DB.php');
if(!isset($db)){$db = DB::connect('mysql://'.$amp_conf['AMPDBUSER'].':'.$amp_conf['AMPDBPASS'].'@'.$amp_conf['AMPDBHOST'].'/'.$amp_conf['AMPDBNAME']);} // attempt connection
if($argc == 1){//no args recieved - show help text
	showopts();
}elseif($argc == 2){//one arg recievied. Hmm, this sounds like a backup schedules id... Lets look in the DB for more details
	$sql = "SELECT * FROM backup WHERE id= ?";
	$opts=$db->getRow($sql,array($argv[1]), DB_FETCHMODE_ASSOC);
	if(!$opts){echo "No Backup Schedules defined in backup table or you may need to run this program with more arguments.\n";exit;}
}else{
	$opts['name']=isset($argv[1])?$argv[1]:false;
	$opts['voicemail']=(isset($argv[2])&& $argv[2]=='yes')?true:false;
	$opts['recordings']=(isset($argv[3])&& $argv[3]=='yes')?true:false;
	$opts['configurations']=(isset($argv[4])&& $argv[4]=='yes')?true:false;
	$opts['cdr']=(isset($argv[5])&& $argv[5]=='yes')?true:false;
	$opts['fop']=(isset($argv[6])&& $argv[6]=='yes')?true:false;
}
$opts['ftpfile']='/tmp/freepbx-backup.ftp';
$opts['budir']=$amp_conf['ASTVARLIBDIR'].'/backups';
$opts['now']=date('Ymd.h.i.s');

if($opts['sudo']==true){$sudo='/usr/bin/sudo';}else{$sudo='';}
//if all options are set to no/false, return an error
if(!$opts['voicemail']&&!$opts['recordings']&&!$opts['configurations']&&!$opts['cdr']&&!$opts['fop']&&!$opts['include']){echo "Backup Error: You need to set at least one option to yes\n";showopts();}
system('/bin/rm -rf /tmp/ampbackups.'.$opts['now'].' > /dev/null  2>&1');//remove stale backup
system('/bin/mkdir /tmp/ampbackups.'.$opts['now'].' > /dev/null  2>&1');//create directory for current backup
//backup voicmail if requested
if($opts['voicemail']){system('/bin/tar -Pcz -f /tmp/ampbackups.'.$opts['now'].'/voicemail.tar.gz '.$amp_conf['ASTSPOOLDIR'].'/voicemail');}
//backup recordings in requested
if($opts['recordings']){system('/bin/tar -Pcz -f /tmp/ampbackups.'.$opts['now'].'/recordings.tar.gz '.$amp_conf['ASTVARLIBDIR'].'/sounds/custom');}
//backup configurations if requested
if($opts['configurations']){
	system($amp_conf['ASTVARLIBDIR'].'/bin/dumpastdb.php '.$opts['now'].' > /dev/null');

	$cmd='/bin/tar -Pcz -f /tmp/ampbackups.'.$opts['now'].'/configurations.tar.gz '.$amp_conf['ASTVARLIBDIR'].'/agi-bin/ ';
	$cmd.=$amp_conf['ASTVARLIBDIR'].'/bin/ '.$amp_conf['ASTETCDIR'].' /etc/amportal.conf ';
	if($opts['admin']=='yes'){$cmd.=$amp_conf['AMPWEBROOT'].'/admin ';}//include admin/ unless otherwise requested
	if(isset($amp_conf['ZAP2DAHDICOMPAT']) && $amp_conf['ZAP2DAHDICOMPAT']==true){$cmd.='/etc/dahdi ';}else{$cmd.='/etc/zaptel.conf ';}//include zap OR dahdi
	$cmd.='/tmp/ampbackups.'.$opts['now'].'/astdb.dump';
	system($cmd);
	if($opts['include']){
		$exclude='';
		if(isset($opts['exclude']) && $opts['exclude']!=''){
			$excludes=str_replace(array("\n","\r","\r\n"),' ',$opts['exclude']);
			$ex=explode('  ',$excludes);
			foreach($ex as $x){ //exclude each option in the space delimited list
				$exclude.='--exclude='.$x.' ';
			} 
		}
		$inculde=str_replace(array("\n","\r","\r\n"),' ',$opts['include']);
		$exec=$sudo.' /bin/tar -Pcz -f /tmp/ampbackups.'.$opts['now'].'/phoneconfig.tar.gz '.$inculde.'  '.$exclude;
		exec($exec,$ret);
	}
	system('mysqldump --add-drop-table -h'.$amp_conf['AMPDBHOST'].' -u'.$amp_conf['AMPDBUSER'].' -p'.$amp_conf['AMPDBPASS'].' --database '.$amp_conf['AMPDBNAME'].' > /tmp/ampbackups.'.$opts['now'].'/asterisk.sql');
}
//backup cdr if requested
if($opts['cdr']){
	system('/bin/tar -Pcz -f /tmp/ampbackups.'.$opts['now'].'/cdr.tar.gz '.$amp_conf['AMPWEBROOT'].'/admin/cdr');
	system('mysqldump --add-drop-table -h '.$amp_conf['AMPDBHOST'].' -u '.$amp_conf['AMPDBUSER'].' -p'.$amp_conf['AMPDBPASS'].' --database asteriskcdrdb > /tmp/ampbackups.'.$opts['now'].'/asteriskcdr.sql');
}
//backup FOP if requested
if($opts['fop']){system('/bin/tar -Pcz -f /tmp/ampbackups.'.$opts['now'].'/fop.tar.gz '.$amp_conf['AMPWEBROOT'].'/panel');}
system('/bin/mkdir -p '.$opts['budir'].'/'.$opts['name'].' > /dev/null  2>&1');
system('/bin/tar -Pcz -f '.$opts['budir'].'/'.$opts['name'].'/'.$opts['now'].'.tar.gz /tmp/ampbackups.'.$opts['now']);
system('/bin/rm -rf /tmp/ampbackups.'.$opts['now'].' > /dev/null  2>&1');

/*
 FTP Sucessfull Backup's to FTPSERVER
 leaves $ftpbackup which gets overwritten next time but can be checked to see if there were errors.
 IMPORTANT - if testing as root, delete files since backup runs as asterisk and will fail here since
             root leave the file around and asterisk can't overwrite it.
 Note - the hardcoded full backup that cron does will overwrite each day at destination.
*/
if( $opts['ftpuser'] && $opts['ftppass'] && $opts['ftphost']){
	$fh=fopen($opts['ftpfile'], 'w');
	$data='user '.$opts['ftpuser'].' '.$opts['ftppass']." \n";
	$data.="binary\n";
	if($opts['ftpdir']!=''){$data.="cd ${opts['ftpdir']} \n";}
	$data.='lcd '.$opts['budir'].'/'.$opts['name']."/\n";
	$data.='put '.$opts['now'].".tar.gz\n";
	$data.='bye\n';
	fwrite($fh, $data);
	fclose($fh);
	exec('ftp -n '.$opts['ftphost'].' < '.$opts['ftpfile'].' ',$ftpres);
}

//ssh backup
if($opts['sshkey'] && $opts['sshhost']){
	if(!$opts['sshuser']){exec('whoami',$opts['sshuser']);}//use username of whoever scrip is running as if username isnt set
	if($opts['sshdir']!=''){  //ensure that remote directory exists
		system('/usr/bin/ssh -o StrictHostKeyChecking=no -i '.$opts['sshkey'].' '.$opts['sshuser'].'\@'.$opts['sshhost'].' mkdir -p '.$opts['sshdir']);
	}
	system('/usr/bin/scp -o StrictHostKeyChecking=no -i '.$opts['sshkey'].' '.$opts['budir'].'/'.$opts['name'].'/'.$opts['now'].'.tar.gz '.$opts['sshuser'].'\@'.$opts['sshhost'].':'.$opts['sshdir']);
}

//email backup
if($opts['emailaddr']){
	if(filesize($opts['budir'].'/'.$opts['name']) <= size2bytes($opts['emailmaxsize'].$opts['emailmaxtype'])){
		//credit to: http://articles.sitepoint.com/print/advanced-email-php
		$hostname=exec('/bin/hostname',$name);
		$subject = 'FreePBX backup of '.$hostname;
		$emessage = sprintf("Hello. Attached, please find your FreePBX backup file from backup set: %s, run on %s, at %s",$opts['name'],$hostname,date('Y-m-d h:i:sa')); 
		
		// Obtain file info
		$filename=$opts['budir'].'/'.$opts['name'].'/'.$opts['now'].'.tar.gz';
		exec('file -bi '.$filename,$type);
		$file_type=$type[0];
		
		$headers = 'From: '.$amp_conf['AMPBACKUPEMAILFROM'];
		// Read the file to be attached ('rb' = read binary)
		$file = fopen($filename,'rb');
		$data = fread($file,filesize($filename));
		fclose($file);
		
		// Generate a random boundary string
		$mime_boundary='==Multipart_Boundary_x'.md5(time()).'x';
		// Add the headers for a file attachment
		$headers.="\nMIME-Version: 1.0\n";
		$headers.="Content-Type: multipart/mixed;\n";
		$headers.=" boundary=\"{$mime_boundary}\"";
		// Add a multipart boundary above the plain message
		$message="This is a multi-part message in MIME format.\n\n";
		$message.="--{$mime_boundary}\n";
		$message.="Content-Type: text/plain; charset=\"iso-8859-1\"\n";
		$message.="Content-Transfer-Encoding: 7bit\n\n";
		$message.=$emessage . "\n\n";
		// Base64 encode the file data
		$data = chunk_split(base64_encode($data));
		// Add file attachment to the message
		$message.="--{$mime_boundary}\n";
		$message.="Content-Type: {$file_type};\n";
		$message.=' name="'.$opts['now'].'.tar.gz'."\"\n";
		//$message.="Content-Disposition: attachment;\n";
		//$message.=" filename=\"{$file}\"\n";
		$message.="Content-Transfer-Encoding: base64\n\n";
		$message.=$data . "\n\n";
		$message.="--{$mime_boundary}--\n";
		
		//debug output
		//echo "To:\n";echo $opts['emailaddr'];echo "\n\nSubject:\n";echo $subject;echo "\n\nMessage:\n";echo $message;echo "\n\nHeaders:\n";echo $headers."\n";
		// Send the message
		mail($opts['emailaddr'], $subject, $message, $headers);
	}
}


function size2bytes($str){ 
  $bytes=0; 
  $bytes_array=array('B'=>1, 'KB'=>1024, 'MB'=>1024*1024, 'GB'=>1024*1024*1024, 
								'TB'=>1024*1024*1024*1024, 'PB'=>1024*1024*1024*1024*1024); 
  $bytes=floatval($str);
  if(preg_match('#([KMGTP]?B)$#si',$str,$matches) && !empty($bytes_array[$matches[1]])){ 
      $bytes*=$bytes_array[$matches[1]]; 
  } 
  $bytes=intval(round($bytes, 2)); 
  return $bytes; 
} 

function showopts(){
	echo "\n";
	echo "# ampbackup.php Backup-set-ID \n";
	echo "This script Reads the backup options from the BackupTable then runs the backup picking up the items that were turned.\n";
	echo "\n";echo "                         --OR--                         \n";echo "\n";
	echo "The program is called from the backup.php script and implemented immediately as such:\n";
	echo "# ampbackup.php Name Voicemail(yes/no) Recordings(yes/no) Config_files(yes/no) CDR(yes/no) FOP(yes/no)\n";
	echo " \n";
	echo "example: ampbackup.php \"My_Nightly_Backup\" yes yes no no yes\n";
	exit(1);
}

function getconf($filename) {
  $file = file($filename);
  foreach($file as $line => $cont){
  	if(substr($cont,0,1)!='#'){
  		$d=explode('=',$cont);
  		if(isset($d['0'])&& isset($d['1'])){$conf[trim($d['0'])]=trim($d['1']);}
  	}
  }
  return $conf;
}

function getOpts(){
	array_shift($_SERVER['argv']);
	foreach($_SERVER['argv'] as $arg){
		$arg=explode('=',$arg);
		//remove leading '--'
		if(substr($arg['0'],0,2) == '--'){$arg['0']=substr($arg['0'],2);}
		$opts[$arg['0']]=isset($arg['1'])?$arg['1']:null;
	}
	return isset($opts)?$opts:false;
}


function dbug($disc=null,$msg=null){
	$debug=true;
	if ($debug){
	$fh = fopen("/tmp/freepbx_debug.log", 'a') or die("can't open file");
	if($disc){$disc=' \''.$disc.'\':';}
	fwrite($fh,date("Y-M-d H:i:s").$disc."\n"); //add timestamp
	if (is_array($msg)) {
		fwrite($fh,print_r($msg,true)."\n");
	} else {
		fwrite($fh,$msg."\n");
	}
	fclose($fh);
	}
}

?>
