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
 ampbackup.pl <Backup_Name> <Backup_Voicemail_(yes/no)> <Backup_Recordings_(yes/no)> <Backup_Configuration_files(yes/no)> 
 <Backup_CDR_(yes/no)> <Backup_FOP_(yes/no)

 example ampbackup.pl "My_Nightly_Backup" yes yes no no yes


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
//default some options if they are blank
if(!isset($amp_conf['AMPBACKADMIN'])){$amp_conf['AMPBACKADMIN']=true;}
if(!isset($amp_conf['AMPBACKUPEMAILFROM'])){$amp_conf['AMPBACKUPEMAILFROM']='backup@freepbx.org';}
if(!isset($amp_conf['AMPBACKUPEMAILMAX'])){$amp_conf['AMPBACKUPEMAILMAX']='10MB';}
//var_dump($amp_conf);
//$opts=getOpts();
$opts['ftpfile']="/tmp/freepbx-backup.ftp";
$opts['budir']=$amp_conf['ASTVARLIBDIR']."/backups";
$opts['now']=date('Ymd.h.i.s');
if($amp_conf['AMPBACKUPSUDO']==true){$sudo='/usr/bin/sudo';}
//connect to database
include_once('DB.php');
if(!isset($db)){$db = DB::connect('mysql://'.$amp_conf['AMPDBUSER'].':'.$amp_conf['AMPDBPASS'].'@'.$amp_conf['AMPDBHOST'].'/'.$amp_conf['AMPDBNAME']);} // attempt connection

if($argc == 1){//no args recieved - show help text
	showopts();
}elseif($argc == 2){//one arg recievied. Hmm, this sounds like a backup schedules id... Lets look in the DB for more details
	$sql = "SELECT Name, Voicemail, Recordings, Configurations, CDR, FOP from Backup where ID= ?";
	$res=$db->getRow($sql,array($argv[1]), DB_FETCHMODE_ASSOC);
	if(!$res){echo "No Backup Schedules defined in backup table or you may need to run this program with more arguments.\n";exit;}
	$opts['name']=isset($res['Name'])?$res['Name']:false;
	$opts['voicemail']=(isset($res['Voicemail'])&& $res['Voicemail']=='yes')?true:false;
	$opts['recordings']=(isset($res['Recordings'])&& $res['Recordings']=='yes')?true:false;
	$opts['configs']=(isset($res['Configurations'])&& $res['Configurations']=='yes')?true:false;
	$opts['cdr']=(isset($res['CDR'])&& $res['CDR']=='yes')?true:false;
	$opts['fop']=(isset($res['FOP'])&& $res['FOP']=='yes')?true:false;
}

$opts['name']=isset($argv[1])?$argv[1]:false;
$opts['voicemail']=(isset($argv[2])&& $argv[2]=='yes')?true:false;
$opts['recordings']=(isset($argv[3])&& $argv[3]=='yes')?true:false;
$opts['configs']=(isset($argv[4])&& $argv[4]=='yes')?true:false;
$opts['cdr']=(isset($argv[5])&& $argv[5]=='yes')?true:false;
$opts['fop']=(isset($argv[6])&& $argv[6]=='yes')?true:false;
//var_dump($opts);

//if all options are set to no/false, return an error
if(!$opts['voicemail']&&!$opts['recordings']&&!$opts['configs']&&!$opts['cdr']&&!$opts['fop']){echo "Backup Error: You need to set at least one option to yes\n";showopts();}
system('/bin/rm -rf /tmp/ampbackups.'.$opts['now'].' > /dev/null  2>&1');//remove stale backup
system('/bin/mkdir /tmp/ampbackups.'.$opts['now'].' > /dev/null  2>&1');//create directory for current backup
//backup voicmail if requested
if($opts['voicemail']){system('/bin/tar -Pcz -f /tmp/ampbackups.'.$opts['now'].'/voicemail.tar.gz '.$amp_conf['ASTSPOOLDIR'].'/voicemail');}
//backup recordings in requested
if($opts['recordings']){system('/bin/tar -Pcz -f /tmp/ampbackups.'.$opts['now'].'/recordings.tar.gz '.$amp_conf['ASTVARLIBDIR'].'/sounds/custom');}
//backup configs if requested
if($opts['configs']){
	system($amp_conf['ASTVARLIBDIR'].'/bin/dumpastdb.php '.$opts['now'].' > /dev/null');

	$cmd='/bin/tar -Pcz -f /tmp/ampbackups.'.$opts['now'].'/configurations.tar.gz '.$amp_conf['ASTVARLIBDIR'].'/agi-bin/ ';
	$cmd.=$amp_conf['ASTVARLIBDIR'].'/bin/ '.$amp_conf['ASTETCDIR'].' /etc/amportal.conf ';
	if($amp_conf['AMPBACKADMIN']!='false'){$cmd.=$amp_conf['AMPWEBROOT'].'/admin ';}//include admin/ unless otherwise requested
	if(isset($amp_conf['ZAP2DAHDICOMPAT']) && $amp_conf['ZAP2DAHDICOMPAT']==true){$cmd.='/etc/dahdi ';}else{$cmd.='/etc/zaptel.conf ';}//include zap OR dahdi
	$cmd.='/tmp/ampbackups.'.$opts['now'].'/astdb.dump';
	system($cmd);
	if ($amp_conf['AMPPROVROOT']){
	$xfile='';
		if(isset($amp_conf['AMPPROVEXCLUDE']) && $amp_conf['AMPPROVEXCLUDE']){$xfile='--exclude-from '.$amp_conf['AMPPROVEXCLUDE'];};//file containing exclude list
		if(isset($amp_conf['AMPPROVEXCLUDELIST']) && $amp_conf['AMPPROVEXCLUDELIST']){
			$exclude='';
			$ex=explode(' ',$amp_conf['AMPPROVEXCLUDELIST']);
			foreach($ex as $x){ //exclude each option in the space delimited list
				$exclude.='--exclude='.$x.' ';
			} 
		}
		system($sudo.' /bin/tar -Pcz -f /tmp/ampbackups.'.$opts['now'].'/phoneconfig.tar.gz '.$amp_conf['AMPPROVROOT'].' '.$xfile.' '.$exclude);
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
if(isset($amp_conf['FTPBACKUP']) && $amp_conf['FTPBACKUP']=='yes'){
	$fh=fopen($opts['ftpfile'], 'w')|| die('Failed to open '.$opts['ftpfile']."\n");
	$data='user '.$amp_conf['FTPUSER'].' '.$amp_conf['FTPPASSWORD']." \n";
	$data.="binary\n";
	if($amp_conf['FTPSUBDIR']!=''){$data.="cd $ftpsubdir \n";}
	$data.='lcd '.$opts['budir'].'/'.$opts['name']."/\n";
	$data.='put '.$opts['now'].".tar.gz\n";
	$data.='bye\n';
	fwrite($fh, $data);
	fclose($fh);
	system ('ftp -n '.$amp_conf['FTPSERVER'].' < '.$opts['ftpfile'].' > /dev/null  2>&1');
}

//SSH backup
if(isset($amp_conf['SSHBACKUP']) && $amp_conf['SSHBACKUP']=='yes'){
	if(($amp_conf['SSHRSAKEY']!='') && ($amp_conf['SSHSERVER']!='')){
		if ($amp_conf['SSHUSER']!=''){
			$amp_conf['SSHUSER'] = system('whoami');
		}
		if ($amp_conf['SSHSUBDIR']!=''){
			system('/usr/bin/ssh -o StrictHostKeyChecking=no -i '.$amp_conf['SSHRSAKEY'].' '.$amp_conf['SSHUSER'].'\@'.$amp_conf['SSHSERVER'].' mkdir -p '.$amp_conf['SSHSUBDIR']);
		}
		system('/usr/bin/scp -o StrictHostKeyChecking=no -i '.$amp_conf['SSHRSAKEY'].' '.$opts['budir'].'/'.$opts['name'].'/'.$opts['now'].'.tar.gz '.$amp_conf['SSHUSER'].'\@'.$amp_conf['SSHSERVER'].':'.$amp_conf['SSHSUBDIR']);
	}
}

//EMAIL backup
if($amp_conf['AMPBACKUPEMAIL']=='yes' && isset($amp_conf['AMPBACKUPEMAILADDR'])){
	if(filesize($opts['budir'].'/'.$opts['name']) <= size2bytes(strtoupper($amp_conf['AMPBACKUPEMAILMAX']))){
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
		//echo "To:\n";echo $amp_conf['AMPBACKUPEMAILADDR'];echo "\n\nSubject:\n";echo $subject;echo "\n\nMessage:\n";echo $message;echo "\n\nHeaders:\n";echo $headers."\n";
		// Send the message
		mail($amp_conf['AMPBACKUPEMAILADDR'], $subject, $message, $headers);
	}
}


function size2bytes($str){ 
  $bytes=0; 
  $bytes_array=array('B' => 1, 'KB' => 1024, 'MB' => 1024 * 1024, 'GB' => 1024 * 1024 * 1024, 
								'TB' => 1024 * 1024 * 1024 * 1024, 'PB' => 1024 * 1024 * 1024 * 1024 * 1024,); 
  $bytes=floatval($str);
  if(preg_match('#([KMGTP]?B)$#si', $str, $matches) && !empty($bytes_array[$matches[1]])){ 
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