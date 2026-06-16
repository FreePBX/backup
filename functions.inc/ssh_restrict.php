<?php
/**
* Build prefixed remote commands for freepbx-ssh-restrict.sh.
 * Prefixes must stay in sync with backup/bin/freepbx-ssh-restrict.sh.
 */
namespace FreePBX\modules\Backup;

class SshRestrict {
	private static function assertPath(string $path): string {
		if ($path === '' || preg_match('/\.\./', $path) || !preg_match('/^[a-zA-Z0-9\/_.@+=:-]+$/', $path)) {
			throw new \InvalidArgumentException('Invalid path for restricted SSH command');
		}
		return $path;
	}

	private static function assertId(string $id): string {
		if ($id === '' || !preg_match('/^[a-zA-Z0-9_.-]+$/', $id)) {
			throw new \InvalidArgumentException('Invalid id for restricted SSH command');
		}
		return $id;
	}

	private static function assertBase64(string $data): string {
		if ($data === '' || !preg_match('/^[a-zA-Z0-9+\/=_-]+$/', $data)) {
			throw new \InvalidArgumentException('Invalid base64 payload for restricted SSH command');
		}
		return $data;
	}

	public static function mkdir(string $path): string {
		return 'RESTRICT-MKDIR-001 ' . self::assertPath($path);
	}

	public static function asteriskStart(): string {
		return 'RESTRICT-ASTERISK-001';
	}

	public static function fwconsoleBackupRestoreEq(string $path, string $transaction): string {
		return 'RESTRICT-FWCONSOLE-002 --restore=' . self::assertPath($path)
			. ' --transaction=' . self::assertId($transaction);
	}

	public static function fwconsoleBackupRestore(string $path, string $transaction): string {
		return 'RESTRICT-FWCONSOLE-003 --restore ' . self::assertPath($path)
			. ' --transaction=' . self::assertId($transaction);
	}

	public static function fwconsoleBackupExtern(string $payload, ?string $transaction = null): string {
		$cmd = 'RESTRICT-FWCONSOLE-004 --externbackup=' . self::assertBase64($payload);
		if ($transaction !== null && $transaction !== '') {
			$cmd .= ' --transaction=' . self::assertId($transaction);
		}
		return $cmd;
	}

	public static function fwconsoleAdvr(): string {
		return 'RESTRICT-FWCONSOLE-005 advr';
	}

	public static function fwconsoleAdvrGenapi(): string {
		return 'RESTRICT-FWCONSOLE-006 advr --genapi';
	}

	public static function fwconsoleAdvrAddSecondaryRestoreLog(string $file, string $transaction): string {
		return 'RESTRICT-FWCONSOLE-007 advr --addsecondaryrestorelog --file='
			. self::assertPath($file) . ' --transaction=' . self::assertId($transaction);
	}

	public static function fwconsoleAdvrUnsetPrimaryDown(string $buid): string {
		return 'RESTRICT-FWCONSOLE-008 advr --unsetprimarydown ' . self::assertId($buid);
	}

	public static function fwconsoleAdvrTrunksOnSwitchover(): string {
		return 'RESTRICT-FWCONSOLE-009 advr --trunksonswitchover';
	}

	public static function fwconsoleAdvrRunSwitchoverHook(string $id): string {
		return 'RESTRICT-FWCONSOLE-010 advr --runswtichoverhook ' . self::assertId($id);
	}

	public static function fwconsoleAdvrSetPrimaryDown(string $id): string {
		return 'RESTRICT-FWCONSOLE-011 advr --setprimarydown ' . self::assertId($id);
	}

	public static function fwconsolePm2Stop(string $service = 'advrecovery'): string {
		if ($service !== 'advrecovery') {
			throw new \InvalidArgumentException('Unsupported pm2 service for restricted SSH command');
		}
		return 'RESTRICT-FWCONSOLE-012 pm2 --stop advrecovery';
	}

	public static function fwconsolePm2Restart(string $service = 'advrecovery'): string {
		if ($service !== 'advrecovery') {
			throw new \InvalidArgumentException('Unsupported pm2 service for restricted SSH command');
		}
		return 'RESTRICT-FWCONSOLE-013 pm2 --restart advrecovery';
	}

	public static function touchFwconsoleChown(): string {
		return 'RESTRICT-TOUCH-001';
	}

	public static function touchSwitchoverReload(): string {
		return 'RESTRICT-TOUCH-002';
	}

	public static function touchFwconsoleRestart(): string {
		return 'RESTRICT-TOUCH-003';
	}

	public static function touchAsteriskStop(): string {
		return 'RESTRICT-TOUCH-004';
	}

	public static function touchFwconsoleStop(): string {
		return 'RESTRICT-TOUCH-005';
	}

	public static function ls(string $path): string {
		return 'RESTRICT-LS-001 ' . self::assertPath($path);
	}

	public static function rm(string $path): string {
		return 'RESTRICT-RM-001 ' . self::assertPath($path);
	}

	public static function cd(string $path): string {
		return 'RESTRICT-CD-001 ' . self::assertPath($path);
	}
}