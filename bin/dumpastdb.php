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
$fh = fopen("/tmp/$argv[1]", "w");
$astdb = $astman->database_show();
foreach ($astdb as $key => $val) {
	if ($key == "") { continue; }
	if ($key == "Privilege") { continue; }
	fwrite($fh, "[$key] [$val]\n");
}
fclose($fh);

?>

