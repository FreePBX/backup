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

	public static function asteriskGracefulStop(): string {
		return 'RESTRICT-ASTERISK-002';
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

	public static function fwconsoleAdvrMarkRestoreDone(string $transaction): string {
		return 'RESTRICT-FWCONSOLE-014 advr --markrestoredone ' . self::assertId($transaction);
	}

	public static function fwconsoleStop(): string {
		return 'RESTRICT-FWCONSOLE-015 stop';
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

	/**
	 * Pick RESTRICT-* vs direct shell commands based on the remote peer's
	 * authorized_keys entry for our public key.
	 */
	public static function resolveRemoteCommand(string $host, string $restrictedCmd, string $privateKeyPath, string $user = 'asterisk'): string {
		try {
			if (class_exists('\FreePBX')) {
				return \FreePBX::Create()->Backup->resolveRemoteSshCommand($host, $restrictedCmd, $privateKeyPath, $user);
			}
		} catch (\Throwable $e) {
		}
		return self::toDirectCommand($restrictedCmd);
	}

	public static function toDirectCommand(string $restrictedCmd): string {
		$restrictedCmd = trim($restrictedCmd);
		if ($restrictedCmd === '' || strpos($restrictedCmd, 'RESTRICT-') !== 0) {
			return $restrictedCmd;
		}

		$spacePos = strpos($restrictedCmd, ' ');
		$prefix = $spacePos === false ? $restrictedCmd : substr($restrictedCmd, 0, $spacePos);
		$args = $spacePos === false ? '' : trim(substr($restrictedCmd, $spacePos + 1));
		$fwconsole = '/usr/sbin/fwconsole';
		$incronDir = '/var/spool/asterisk/incron';

		switch ($prefix) {
			case 'RESTRICT-MKDIR-001':
				return 'mkdir -p -- ' . self::assertPath($args);
			case 'RESTRICT-ASTERISK-001':
				return '/usr/sbin/asterisk';
			case 'RESTRICT-ASTERISK-002':
				return '/usr/sbin/asterisk -rx ' . escapeshellarg('core stop gracefully');
			case 'RESTRICT-FWCONSOLE-002':
				if (!preg_match('/^--restore=(.+?) --transaction=(.+)$/', $args, $matches)) {
					throw new \InvalidArgumentException('Invalid restricted restore command');
				}
				return $fwconsole . ' backup --restore=' . self::assertPath($matches[1])
					. ' --transaction=' . self::assertId($matches[2]);
			case 'RESTRICT-FWCONSOLE-003':
				if (!preg_match('/^--restore (.+?) --transaction=(.+)$/', $args, $matches)) {
					throw new \InvalidArgumentException('Invalid restricted restore command');
				}
				return $fwconsole . ' backup --restore ' . self::assertPath($matches[1])
					. ' --transaction=' . self::assertId($matches[2]);
			case 'RESTRICT-FWCONSOLE-004':
				if (preg_match('/^--externbackup=(.+?) --transaction=(.+)$/', $args, $matches)) {
					return $fwconsole . ' backup --externbackup=' . self::assertBase64($matches[1])
						. ' --transaction=' . self::assertId($matches[2]);
				}
				if (preg_match('/^--externbackup=(.+)$/', $args, $matches)) {
					return $fwconsole . ' backup --externbackup=' . self::assertBase64($matches[1]);
				}
				throw new \InvalidArgumentException('Invalid restricted extern backup command');
			case 'RESTRICT-FWCONSOLE-005':
				return $fwconsole . ' advr';
			case 'RESTRICT-FWCONSOLE-006':
				return $fwconsole . ' advr --genapi';
			case 'RESTRICT-FWCONSOLE-007':
				if (!preg_match('/^advr --addsecondaryrestorelog --file=(.+?) --transaction=(.+)$/', $args, $matches)) {
					throw new \InvalidArgumentException('Invalid restricted advr restore log command');
				}
				return $fwconsole . ' advr --addsecondaryrestorelog --file=' . self::assertPath($matches[1])
					. ' --transaction=' . self::assertId($matches[2]);
			case 'RESTRICT-FWCONSOLE-008':
				if (!preg_match('/^advr --unsetprimarydown (.+)$/', $args, $matches)) {
					throw new \InvalidArgumentException('Invalid restricted advr unset primary down command');
				}
				return $fwconsole . ' advr --unsetprimarydown ' . self::assertId($matches[1]);
			case 'RESTRICT-FWCONSOLE-009':
				return $fwconsole . ' advr --trunksonswitchover';
			case 'RESTRICT-FWCONSOLE-010':
				if (!preg_match('/^advr --runswtichoverhook (.+)$/', $args, $matches)) {
					throw new \InvalidArgumentException('Invalid restricted switchover hook command');
				}
				return $fwconsole . ' advr --runswtichoverhook ' . self::assertId($matches[1]);
			case 'RESTRICT-FWCONSOLE-011':
				if (!preg_match('/^advr --setprimarydown (.+)$/', $args, $matches)) {
					throw new \InvalidArgumentException('Invalid restricted set primary down command');
				}
				return $fwconsole . ' advr --setprimarydown ' . self::assertId($matches[1]);
			case 'RESTRICT-FWCONSOLE-012':
				return $fwconsole . ' pm2 --stop advrecovery';
			case 'RESTRICT-FWCONSOLE-013':
				return $fwconsole . ' pm2 --restart advrecovery';
			case 'RESTRICT-FWCONSOLE-014':
				if (!preg_match('/^advr --markrestoredone (.+)$/', $args, $matches)) {
					throw new \InvalidArgumentException('Invalid restricted mark restore done command');
				}
				return $fwconsole . ' advr --markrestoredone ' . self::assertId($matches[1]);
			case 'RESTRICT-FWCONSOLE-015':
				if ($args !== 'stop') {
					throw new \InvalidArgumentException('Invalid restricted fwconsole stop command');
				}
				return $fwconsole . ' stop';
			case 'RESTRICT-TOUCH-001':
				return 'touch ' . $incronDir . '/adv_recovery.fwconsole-chown';
			case 'RESTRICT-TOUCH-002':
				return 'touch ' . $incronDir . '/adv_recovery.switchover-reload';
			case 'RESTRICT-TOUCH-003':
				return 'touch ' . $incronDir . '/adv_recovery.fwconsole-restart';
			case 'RESTRICT-TOUCH-004':
				return 'touch ' . $incronDir . '/adv_recovery.asterisk-stop';
			case 'RESTRICT-TOUCH-005':
				return 'touch ' . $incronDir . '/adv_recovery.fwconsole-stop';
			case 'RESTRICT-LS-001':
				return 'ls -1 -- ' . self::assertPath($args);
			case 'RESTRICT-RM-001':
				return 'rm -- ' . self::assertPath($args);
			case 'RESTRICT-CD-001':
				return 'cd -- ' . self::assertPath($args);
			default:
				throw new \InvalidArgumentException('Unsupported restricted SSH command: ' . $prefix);
		}
	}
}