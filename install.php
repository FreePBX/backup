<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

function backup_get_ssh_restrict_script_path(): string {
	return '/usr/local/bin/freepbx-ssh-restrict.sh';
}

function backup_get_module_dir(): string {
	return \FreePBX::Config()->get('AMPWEBROOT') . '/admin/modules/backup';
}

function backup_get_deployed_hook_path(string $hookName): string {
	return backup_get_module_dir() . '/hooks/' . $hookName;
}

function backup_ensure_hook_deployed(string $hookName): void {
	$moduleDir = backup_get_module_dir();
	$moduleHook = __DIR__ . '/hooks/' . $hookName;
	$deployedHook = $moduleDir . '/hooks/' . $hookName;

	if (is_dir($moduleDir . '/hooks') && !file_exists($deployedHook) && is_readable($moduleHook)) {
		@symlink($moduleHook, $deployedHook);
	}
}

function backup_ensure_ssh_restrict_assets_deployed(): void {
	$moduleDir = backup_get_module_dir();
	$sourceBin = __DIR__ . '/bin/freepbx-ssh-restrict.sh';
	$deployedBin = $moduleDir . '/bin/freepbx-ssh-restrict.sh';

	backup_ensure_hook_deployed('install-ssh-restrict');
	backup_ensure_hook_deployed('install-freepbx-sftp');
	backup_ensure_hook_deployed('sync-sftp-authorized-keys');

	if (!is_dir($moduleDir . '/bin')) {
		@mkdir($moduleDir . '/bin', 0755, true);
	}
	if (!file_exists($deployedBin) && is_readable($sourceBin)) {
		@symlink($sourceBin, $deployedBin);
	}
}

function backup_run_sysadmin_hook(string $hookName, $params = false): bool {
	if (!file_exists('/etc/incron.d/sysadmin') || !is_dir('/var/spool/asterisk/incron')) {
		return false;
	}
	backup_ensure_ssh_restrict_assets_deployed();
	if (!is_readable(backup_get_deployed_hook_path($hookName))) {
		return false;
	}
	try {
		return (bool) \FreePBX::Hooks()->runModuleSystemHook('backup', $hookName, $params);
	} catch (\Exception $e) {
		return false;
	}
}

function backup_install_ssh_restrict_script_via_hook(string $target): bool {
	if (!backup_run_sysadmin_hook('install-ssh-restrict')) {
		return false;
	}
	for ($i = 0; $i < 10; $i++) {
		if (is_executable($target)) {
			return true;
		}
		usleep(500000);
	}
	return false;
}

function backup_install_ssh_restrict_script(): void {
	$source = __DIR__ . '/bin/freepbx-ssh-restrict.sh';
	$target = backup_get_ssh_restrict_script_path();
	if (!is_readable($source)) {
		out(_("SSH restrict script source not found, skipping install"));
		return;
	}
	if (!\FreePBX::Modules()->checkStatus('sysadmin')) {
		out(_("Sysadmin module is required to install the SSH restrict script"));
		return;
	}
	// Always refresh so SFTP denial / path limits are applied on upgrade.
	if (backup_install_ssh_restrict_script_via_hook($target)) {
		out(sprintf(_("Installed SSH restrict script to %s"), $target));
		return;
	}
	out(sprintf(_("Failed to install SSH restrict script to %s via sysadmin hook"), $target));
}

function backup_get_freepbx_sftp_install_error(): string {
	$errFlag = '/var/spool/asterisk/tmp/freepbx-sftp-install.err';
	if (!is_readable($errFlag)) {
		return '';
	}
	$msg = trim((string) @file_get_contents($errFlag));
	return $msg;
}

function backup_is_freepbx_sftp_installed(): bool {
	if (backup_get_freepbx_sftp_install_error() !== '') {
		return false;
	}
	$user = 'freepbx-sftp';
	$chroot = '/var/lib/freepbx-sftp';
	$dropIn = '/etc/ssh/sshd_config.d/sangoma-freepbx-sftp.conf';
	$managed = $chroot . '/.freepbx-managed';
	if (posix_getpwnam($user) === false || !is_dir($chroot . '/backup') || !is_file($dropIn)) {
		return false;
	}
	// Prefer managed marker; also accept our Match drop-in text for upgrades that
	// created the account before the marker existed.
	if (is_file($managed)) {
		return true;
	}
	$conf = (string) @file_get_contents($dropIn);
	return $conf !== '' && strpos($conf, 'Managed by FreePBX backup module') !== false;
}

function backup_install_freepbx_sftp_user(): void {
	if (!\FreePBX::Modules()->checkStatus('sysadmin')) {
		out(_("Sysadmin module is required to install the freepbx-sftp user"));
		return;
	}
	if (!backup_run_sysadmin_hook('install-freepbx-sftp')) {
		$msg = backup_get_freepbx_sftp_install_error();
		out($msg !== '' ? $msg : _("Failed to install freepbx-sftp user/chroot via sysadmin hook"));
		return;
	}
	for ($i = 0; $i < 10; $i++) {
		$msg = backup_get_freepbx_sftp_install_error();
		if ($msg !== '') {
			out($msg);
			return;
		}
		if (backup_is_freepbx_sftp_installed()) {
			out(_("Installed freepbx-sftp chrooted SFTP user"));
			return;
		}
		usleep(500000);
	}
	$msg = backup_get_freepbx_sftp_install_error();
	out($msg !== '' ? $msg : _("freepbx-sftp install hook ran but user/chroot was not detected yet"));
}

backup_install_ssh_restrict_script();
backup_install_freepbx_sftp_user();
