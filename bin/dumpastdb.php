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
$restrict_mods = true;
$bootstrap_settings['freepbx_auth'] = false;
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
	include_once('/etc/asterisk/freepbx.conf');
}
global $argv;


if (!isset($amp_conf["ASTMANAGERHOST"])) {
  $amp_conf["ASTMANAGERHOST"] = '127.0.0.1';
}
if (!isset($amp_conf["ASTMANAGERPORT"])) {
  $amp_conf["ASTMANAGERPORT"] = '5038';
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
	if ($key == "RG") { continue; }
	if ($key == "BLKVM") { continue; }
	fwrite($fh, "[$key] [$val]\n");
}
fclose($fh);

?>

