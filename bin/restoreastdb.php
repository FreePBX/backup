#!/usr/bin/env php
<?php
// No use outputting anything, as env forces php headers to appear. Sigh.

global $argv;

// Astdb trees that should be deleted before the restore
//
$deltree = array(
	'AMPUSER',
	'DEVICE',
	'CF',
	'CFB',
	'CFU',
	'CW',
	'DND',
	'DAYNIGHT',
);

function getconf($filename) {
        $file = file($filename);
        foreach ($file as $line) {
                if (preg_match("/^\s*([\w]+)\s*=\s*\"?([\w\/\:\.\%-]*)\"?\s*([;#].*)?/",$line,$matches)) {
                        $conf[ $matches[1] ] = $matches[2];
                }
        }
        return $conf;
}

$amp_conf = getconf("/etc/amportal.conf");

require_once($amp_conf['AMPWEBROOT']."/admin/common/php-asmanager.php");

$astman         = new AGI_AsteriskManager();
if (! $res = $astman->connect("127.0.0.1", $amp_conf["AMPMGRUSER"] , $amp_conf["AMPMGRPASS"])) {
        unset( $astman );
}

if (!$argv[1] || strstr($argv[1], "/") || strstr($argv[1], "..")) {
	// You must supply a single filename, which will be written to /tmp
	exit;
}
$dump = file_get_contents("/tmp/ampbackups.".$argv[1]."/astdb.dump");

// Before restoring, let's clear out all of the current settings for the main objects
// but as a safety, if the dump file is empy, we won't clear it out.
//
if (!empty($dump)) {
	$arr = explode("\n", $dump);
	foreach ($deltree as $family) {
		$astman->database_deltree($family);
	}
	foreach ($arr as $line) {
		$result = preg_match("/\[(.+)\] \[(.+)\]/", $line, $matches);
		// Now, the bad ones we know about are the ones that start with //, anything starting with SIP or IAX,
		// and RG (which are only temporary anyway).
		if (!isset($matches[1]) || $matches[1] == "") { continue; }
		$pattern = "/(^\/\/)|(^\/IAX)|(^\/SIP)|(^\/RG)|(^\/BLKVM)|(^\/FM)|(^\/dundi)/";
		if (preg_match($pattern, $matches[1])) { continue; }
		preg_match("/(.+)\/(.+)$/", $matches[1], $famkey);
		$famkey[1]=trim($famkey[1], '/');
		$astman->database_put($famkey[1], $famkey[2], '"'.$matches[2].'"');
	}
}

?>

