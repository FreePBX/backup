#!/usr/bin/env php
<?
// No use outputting anything, as env forces php headers to appear. Sigh.

global $argv;
require_once("php-asmanager.php");

function getconf($filename) {
        $file = file($filename);
        foreach ($file as $line) {
                if (preg_match("/^\s*([\w]+)\s*=\s*\"?([\w\/\:\.\%-]*)\"?\s*([;#].*)?/",$line,$matches)) {
                        $conf[ $matches[1] ] = $matches[2];
                }
        }
        return $conf;
}

$amp_conf = getconf(AMP_CONF);

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
	if (preg_match("/^\/[SIP|IAX|\/|RG]/", $matches[1])) { continue; }
	$astman->database_put($matches[1], '', $matches[2]);
}

?>

