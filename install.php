<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//TODO MIGRATE OLD STUFF TO NEW STUFF TO NEW STUFF

$freepbx_conf = freepbx_conf::create();

// AMPBACKUPEMAILFROM
//
$set['value'] = '';
$set['defaultval'] =& $set['value'];
$set['readonly'] = 0;
$set['hidden'] = 0;
$set['level'] = 0;
$set['module'] = 'backup';
$set['category'] = 'Backup Module';
$set['emptyok'] = 1;
$set['name'] = 'Email "From:" Address';
$set['description'] = 'The From: field for emails when using the backup email feature.';
$set['type'] = CONF_TYPE_TEXT;
$freepbx_conf->define_conf_setting('AMPBACKUPEMAILFROM',$set,true);
