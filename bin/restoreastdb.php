#!/usr/bin/env php
<?php
// No use outputting anything, as env forces php headers to appear. Sigh.
//This file is part of FreePBX.
//
//    FreePBX is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 2 of the License, or
//    (at your option) any later version.
//
//    FreePBX is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with FreePBX.  If not, see <http://www.gnu.org/licenses/>.
//

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

# Get configuration
$amportalconf = (isset($_ENV["FREEPBXCONFIG"]) && strlen($_ENV["FREEPBXCONFIG"])) ? $_ENV["FREEPBXCONFIG"] : "/etc/amportal.conf";
$amp_conf = getconf($amportalconf);

if (!isset($amp_conf["ASTMANAGERHOST"])) {
  $amp_conf["ASTMANAGERHOST"] = '127.0.0.1';
}
if (!isset($amp_conf["ASTMANAGERPORT"])) {
  $amp_conf["ASTMANAGERPORT"] = '5038';
}

require_once($amp_conf['AMPWEBROOT']."/admin/functions.inc.php");
require_once($amp_conf['AMPWEBROOT']."/admin/common/php-asmanager.php");

$astman         = new AGI_AsteriskManager();
if (! $res = $astman->connect($amp_conf["ASTMANAGERHOST"] . ":".$amp_conf["ASTMANAGERPORT"], $amp_conf["AMPMGRUSER"] , $amp_conf["AMPMGRPASS"])) {
  unset( $astman );
  exit(10);
}

if (!$argv[1] || strstr($argv[1], "/") || strstr($argv[1], "..")) {
	// You must supply a single filename, which will be written to /tmp
	exit(1);
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
} else {
  exit(20);
}

?>

