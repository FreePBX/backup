<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

function backup_get_ssh_restrict_script_path(): string {
	return '/usr/local/bin/freepbx-ssh-restrict.sh';
}

function backup_get_deployed_ssh_restrict_hook_path(): string {
	return \FreePBX::Config()->get('AMPWEBROOT') . '/admin/modules/backup/hooks/install-ssh-restrict';
}

function backup_ensure_ssh_restrict_hook_deployed(): void {
	$webroot = \FreePBX::Config()->get('AMPWEBROOT');
	$moduleDir = $webroot . '/admin/modules/backup';
	$moduleHook = __DIR__ . '/hooks/install-ssh-restrict';
	$deployedHook = $moduleDir . '/hooks/install-ssh-restrict';
	$sourceBin = __DIR__ . '/bin/freepbx-ssh-restrict.sh';
	$deployedBin = $moduleDir . '/bin/freepbx-ssh-restrict.sh';

	if (is_dir($moduleDir . '/hooks') && !file_exists($deployedHook) && is_readable($moduleHook)) {
		@symlink($moduleHook, $deployedHook);
	}
	if (!is_dir($moduleDir . '/bin')) {
		@mkdir($moduleDir . '/bin', 0755, true);
	}
	if (!file_exists($deployedBin) && is_readable($sourceBin)) {
		@symlink($sourceBin, $deployedBin);
	}
}

function backup_install_ssh_restrict_script_via_hook(string $target): bool {
	if (!file_exists('/etc/incron.d/sysadmin') || !is_dir('/var/spool/asterisk/incron')) {
		return false;
	}
	backup_ensure_ssh_restrict_hook_deployed();
	if (!is_readable(backup_get_deployed_ssh_restrict_hook_path())) {
		return false;
	}
	try {
		if (!\FreePBX::Hooks()->runModuleSystemHook('backup', 'install-ssh-restrict')) {
			return false;
		}
	} catch (\Exception $e) {
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
	if (is_file($target) && is_executable($target)) {
		out(sprintf(_("SSH restrict script already installed at %s"), $target));
		return;
	}
	if (!\FreePBX::Modules()->checkStatus('sysadmin')) {
		out(_("Sysadmin module is required to install the SSH restrict script"));
		return;
	}
	if (backup_install_ssh_restrict_script_via_hook($target)) {
		out(sprintf(_("Installed SSH restrict script to %s"), $target));
		return;
	}
	out(sprintf(_("Failed to install SSH restrict script to %s via sysadmin hook"), $target));
}

backup_install_ssh_restrict_script();
