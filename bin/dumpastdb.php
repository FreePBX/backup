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

function getconf($filename) {
        $file = file($filename);
        foreach ($file as $line) {
                if (preg_match("/^\s*([\w]+)\s*=\s*\"?([\w\/\:\.\%-]*)\"?\s*([;#].*)?/",$line,$matches)) {
                        $conf[ $matches[1] ] = $matches[2];
                }
        }
        return $conf;
}

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
        echo "failed to open using".$amp_conf["ASTMANAGERHOST"] .":".$amp_conf["ASTMANAGERPORT"]." ". $amp_conf["AMPMGRUSER"] ." ". $amp_conf["AMPMGRPASS"]."\n";
}

if (!$argv[1] || strstr($argv[1], "/") || strstr($argv[1], "..")) {
	// You must supply a single filename, which will be written to /tmp
	exit;
}
@mkdir("/tmp/ampbackups.$argv[1]/");
$fh = fopen("/tmp/ampbackups.$argv[1]/astdb.dump", "w");
$astdb = $astman->database_show();
foreach ($astdb as $key => $val) {
	if ($key == "") { continue; }
	if ($key == "Privilege") { continue; }
	fwrite($fh, "[$key] [$val]\n");
}
fclose($fh);

?>

