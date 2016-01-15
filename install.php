<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

global $db, $amp_conf;

$autoincrement = ($amp_conf["AMPDBENGINE"] == "sqlite3") ? "AUTOINCREMENT" : "AUTO_INCREMENT";
$sql[] = $bu_table = 'CREATE TABLE IF NOT EXISTS `backup` (
			`id` int(11) NOT NULL ' . $autoincrement . ',
			`name` varchar(50) default NULL,
			`description` varchar(255) default NULL,
			`immortal` varchar(25) default NULL,
			`data` longtext default NULL,
			`email` longtext default NULL,
			PRIMARY KEY  (`id`)
			)';

$sql[] = 'CREATE TABLE IF NOT EXISTS `backup_details` (
			`backup_id` int(11) NOT NULL,
			`key` varchar(50) default NULL,
			`index` varchar(25) default NULL,
			`value` varchar(250) default NULL
			)';

$sql[] = 'CREATE TABLE IF NOT EXISTS `backup_items` (
			`backup_id` int(11) NOT NULL,
			`type` varchar(50) default NULL,
			`path` text,
			`exclude` text
			)';

$sql[] = 'CREATE TABLE IF NOT EXISTS `backup_cache` (
			`id` varchar(50) NOT NULL,
			`manifest` longtext,
			UNIQUE KEY `id` (`id`)
			)';

$sql[] = 'CREATE TABLE IF NOT EXISTS `backup_servers` (
			`id` int(11) NOT NULL ' . $autoincrement . ',
			`name` varchar(50) default NULL,
			`desc` varchar(255) default NULL,
			`type` varchar(50) default NULL,
			`readonly` varchar(250) default NULL,
			`immortal` varchar(25) default NULL,
			`data` longtext default NULL,
			PRIMARY KEY  (`id`)
			)';

$sql[] = 'CREATE TABLE IF NOT EXISTS `backup_server_details` (
			`server_id` int(11) NOT NULL,
			`key` varchar(50) default NULL,
			`value` varchar(250) default NULL
			)';

$sql[] = 'CREATE TABLE IF NOT EXISTS `backup_templates` (
			`id` int(11) NOT NULL ' . $autoincrement . ',
			`name` varchar(50) default NULL,
			`desc` varchar(255) default NULL,
			`immortal` varchar(25) default NULL,
			`data` longtext default NULL,
			PRIMARY KEY  (`id`)
			)';

$sql[] = 'CREATE TABLE IF NOT EXISTS `backup_template_details` (
			`template_id` int(11) NOT NULL,
			`type` varchar(50) default NULL,
			`path` text,
			`exclude` text
			)';

foreach($sql as $q) {
	db_e($db->query($q), 'die_freepbx', 0, _("Can not create backup tables"));
}
unset($sql);

// Default servers
$server = array(
	'local' => array(
		'id'		=> '',
		'name'		=> 'Local Storage',
		'desc'		=> _('Storage location for backups'),
		'immortal'	=> 'true',
		'type'		=> 'local',
		'path'		=> '__ASTSPOOLDIR__/backup',
	),
	'mysql' => array(
		'id'		=> '',
		'name'		=> 'Config server',
		'desc'		=> _('PBX config server, generally a local database server'),
		'immortal'	=> 'true',
		'type'		=> 'mysql',
		'host'		=> '__AMPDBHOST__',
		'port'		=> 3306,
		'user'		=> '__AMPDBUSER__',
		'password'	=> '__AMPDBPASS__',
		'dbname'	=> '__AMPDBNAME__',
	),
	'cdr' => array(
		'id'		=> '',
		'name'		=> 'CDR server',
		'desc'		=> _('CDR server, generally a local database server'),
		'immortal'	=> 'true',
		'type'		=> 'mysql',
		'host'		=> '__CDRDBHOST__',
		'port'		=> '__CDRDBPORT__',
		'user'		=> '__CDRDBUSER__',
		'password'	=> '__CDRDBPASS__',
		'dbname'	=> '__CDRDBNAME__',
	),
);

// Load default servers if needed
if ($db->getOne('SELECT COUNT(*) FROM backup_servers') < 1) {

	$serverids = array();
	if (!function_exists("backup_put_server")) {
		include_once __DIR__."/functions.inc/servers.php";
	}
	foreach ($server as $id => $t) {
		$serverids[$id] = backup_put_server($t);
	}
	sql('UPDATE backup_servers SET readonly = "a:1:{i:0;s:1:\"*\";}"');
	sql('UPDATE backup_servers SET immortal = "true"');
	$createdby = serialize(array('created_by' => 'install.php'));
	sql('UPDATE backup_servers SET data = "' . addslashes($createdby) . '"');

	out(_('added default backup servers'));

	// Load default templates if needed
	if ($db->getOne('SELECT COUNT(*) FROM backup_templates') < 1) {

		//create default templates
		$template = array(
			'basic' => array(
				'id'		=> '',
				'name'		=> 'Config Backup',
				'desc'		=> _('Configurations only'),
				'immortal'	=> 'true',
				'type'		=> array( 'mysql', 'astdb'),
				'path'		=> array( 'server-' . $serverids['mysql'], ''),
				'exclude'	=> array( '', '')
			),
			'full' => array(
				'id'		=> '',
				'name'		=> 'Full Backup',
				'desc'		=> _('A full backup of core settings and web files, doesn\'t include system sounds or recordings.'),
				'type'		=> array( 'mysql', 'mysql', 'astdb', 'dir', 'dir', 'dir', 'dir'),
				'path'		=> array( 'server-'.$serverids['cdr'], 'server-'.$serverids['mysql'], 'astdb', '__ASTETCDIR__',
								'__AMPWEBROOT__', '__AMPBIN__', '/tftpboot' ),
				'exclude'	=> array( '', '', '', '', '', '', '' ),
			),
			'cdr' => array(
				'id'		=> '',
				'name'		=> 'CDRs',
				'desc'		=> _('Call Detail Records'),
				'immortal'	=> 'true',
				'type'		=> array( 'mysql' ),
				'path'		=> array( 'server-' . $serverids['cdr'] ),
				'exclude'	=> array( '' ),
			),
			'voicemail' =>  array(
				'id'		=> '',
				'name'		=> 'Voice Mail',
				'desc'		=> _('Voice Mail Storage'),
				'immortal'	=> 'true',
				'type'		=> array( 'dir' ),
				'path'		=> array( '__ASTSPOOLDIR__/voicemail' ),
				'exclude'	=> array( '' )
			),
			'recordings' => array(
				'id'		=> '',
				'name'		=> 'System Audio',
				'desc'		=> _('All system audio - including IVR prompts and Music On Hold. DOES NOT BACKUP VOICEMAIL'),
				'immortal'	=> 'true',
				'type'		=> array( 'dir', 'dir', 'dir' ),
				'path'		=> array( '__ASTVARLIBDIR__/moh', '__ASTVARLIBDIR__/sounds/custom', '__ASTVARLIBDIR__/sounds/*/custom' ),
				'exclude'	=> array( '', '', '' )
			),
			'safe_backup' => array(
				'id'		=> '',
				'name'		=> 'Exclude Backup Settings',
				'desc'		=> _('Exclude Backup\'s settings so that they dont get restored, useful for a remote restore'),
				'immortal'	=> 'true',
				'type'		=> array( 'mysql' ),
				'path'		=> array( 'server-' . $serverids['mysql'] ),
				'exclude'	=> array( "backup\nbackup_cache\nbackup_details\nbackup_items\n"
				. "backup_server_details\nbackup_servers\nbackup_template_details\n"
				. "backup_templates\n")
			),
		);
		if (PHP_OS == "FreeBSD") {
			$template['full']['type'][] = 'dir';
			$template['full']['path'][] = '/usr/local/etc/dahdi';
			$template['full']['exclude'][] = '';
			$template['full']['type'][] = 'dir';
			$template['full']['path'][] = '/usr/local/lib/dahdi';
			$template['full']['exclude'][] = '';
		} else {
			$template['full']['type'][] = 'dir';
			$template['full']['path'][] = '/etc/dahdi';
			$template['full']['exclude'][] = '';
		}

		if (!function_exists("backup_put_template")) {
			include_once __DIR__."/functions.inc/templates.php";
		}

		foreach ($template as $that => $t) {
			backup_put_template($t);
		}

		//lock this all down so that they're readonly
		sql('UPDATE backup_templates SET immortal = "true"');
		$createdby = serialize(array('created_by' => 'install.php'));
		sql('UPDATE backup_templates SET data = "' . addslashes($createdby) . '"');
		out(_('added default backup templates'));
	}
} else {
	// Load serverids. This is fixed. If you ever need to change these,
	// be smarter.
	$serverids = array ('local' => 1, 'mysql' => 2, 'cdr');
}


// Do we need a default backup job?
if ($db->getOne('SELECT COUNT(*) FROM backup') < 1) {
	// Yes. Add a default backup
	$new = array(
		'id'		=> '',
		'name'		=> 'Default backup',
		'desc'		=> _('Default backup; automatically installed'),
		'cron_schedule'	=> 'monthly',
		'type'		=> array( 'mysql', 'astdb' ),
		'path'		=> array( 'server-'.$serverids['mysql'], 'astdb' ),
		'exclude'	=> array( '', '' ),
		'storage_servers' => array( $serverids['local'] ),
		'bu_server'	=> 0,
		'delete_amount'	=> 12,
	);
	if (!function_exists("backup_put_backup")) {
		include_once __DIR__."/functions.inc/backup.php";
	}
	backup_put_backup($new);
	$createdby = serialize(array('created_by' => 'install.php'));
	sql('UPDATE backup SET data = "' . addslashes($createdby) . '"');
}

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

// Upgrade to FreePBX 13
//
// Change sound recordings so that it backs up sounds/*/custom, in addition to sounds/custom.
//
// Which template is 'Sounds'?
$stmp = $db->getOne('SELECT `id` FROM `backup_templates` where `name`="System Audio"');
if (!$stmp) {
	// No system audio template?  Uh. Ok.
	return true;
}
// See if it has the new entry
if (!$db->getOne("SELECT COUNT(*) FROM `backup_template_details` WHERE `template_id`='$stmp' AND `path`='__ASTVARLIBDIR__/sounds/*/custom'")) {
	// Add it!
	$db->query("INSERT INTO `backup_template_details` (`template_id`, `type`, `path`, `exclude`) VALUES ('$stmp', 'dir', '__ASTVARLIBDIR__/sounds/*/custom', 'a:1:{i:0;s:0:\"\";}')");
	out(_('Updated System Recordings template'));
}

// Now, look at existing jobs. Find any that are backing up sounds/custom, and add sounds/*/custom if they don't already have it.
$jobs = $db->getAll("SELECT DISTINCT(`backup_id`) FROM `backup_items` WHERE `path`='__ASTVARLIBDIR__/sounds/custom'");
foreach ($jobs as $tmparr) {
	$jobid = $tmparr[0];
	// Does this job have __ASTVARLIBDIR__/sounds/*/custom?
	$hasnew = $db->getOne("SELECT COUNT(*) FROM `backup_items` WHERE `backup_id`='$jobid' AND `path`='__ASTVARLIBDIR__/sounds/*/custom'");
	if (!$hasnew) {
		// No. It doesn't. Add it!
		$db->query("INSERT INTO `backup_items` (`backup_id`, `type`, `path`, `exclude`) VALUES ('$jobid', 'dir', '__ASTVARLIBDIR__/sounds/*/custom', 'a:0:{}')");
		out(sprintf(_("Updated Job %s with new custom sounds directory"), $jobid));
	}
}

