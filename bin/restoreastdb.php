#!/usr/bin/env php
<?
// No use outputting anything, as env forces php headers to appear. Sigh.

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
$dump = file_get_contents("/tmp/ampbackups.$argv[1]/astdb.dump");
$arr = explode("\n", $dump);
foreach ($arr as $line) {
	$result = preg_match("/\[(.+)\] \[(.+)\]/", $line, $matches);
	// Now, the bad ones we know about are the ones that start with //, anything starting with SIP or IAX,
	// and RG (which are only temporary anyway).
	if (!isset($matches[1]) || $matches[1] == "") { continue; }
	$pattern = "/(^\/\/)|(^\/IAX)|(^\/SIP)|(^\/RG)|(^\/BLKVM)|(^\/FM)/";
	if (preg_match($pattern, $matches[1])) { continue; }
	preg_match("/(.+)\/(.+)$/", $matches[1], $famkey);
	$famkey[1]=trim($famkey[1], '/');
	$astman->database_put($famkey[1], $famkey[2], $matches[2]);
}

?>

