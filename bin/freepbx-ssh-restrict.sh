#!/bin/bash
# Restrict incoming SSH to backup / adv_recovery / warm spare operations only.
# Remote commands must use a RESTRICT-* prefix agreed with SshRestrict PHP helper.

CMD="${SSH_ORIGINAL_COMMAND:-}"
SFTP_SERVER="/usr/lib/openssh/sftp-server"
FWCONSOLE="/usr/sbin/fwconsole"
INCRON_DIR="/var/spool/asterisk/incron"

if [ ! -x "$SFTP_SERVER" ] && [ -x /usr/lib/sftp-server ]; then
	SFTP_SERVER="/usr/lib/sftp-server"
fi

die() {
	echo "command not allowed" >&2
	exit 1
}

validate_path() {
	case "$1" in
		""|*..*)
			return 1
			;;
	esac
	case "$1" in
		*[![:alnum:]/_.@+=:-]*)
			return 1
			;;
	esac
	return 0
}

validate_id() {
	case "$1" in
		""|*[![:alnum:]._-]*)
			return 1
			;;
	esac
	return 0
}

validate_base64() {
	case "$1" in
		""|*[![:alnum:]+/=_-]*)
			return 1
			;;
	esac
	return 0
}

# SFTP subsystem (filestore backup upload/download). Forced-command keys must not
# use the pty option or the SFTP binary protocol will fail client-side.
case "$CMD" in
	""|sftp|/usr/lib/openssh/sftp-server|/usr/lib/sftp-server)
		exec "$SFTP_SERVER"
		;;
esac

PREFIX="${CMD%% *}"
ARGS="${CMD#"$PREFIX"}"
ARGS="${ARGS# }"

case "$PREFIX" in
	RESTRICT-MKDIR-001)
		validate_path "$ARGS" || die
		exec /usr/bin/mkdir -p -- "$ARGS"
		;;

	RESTRICT-ASTERISK-001)
		[ -z "$ARGS" ] || die
		exec /usr/sbin/asterisk
		;;

	RESTRICT-FWCONSOLE-002)
		restore="${ARGS%% --transaction=*}"
		transaction="${ARGS#*--transaction=}"
		[ "${restore#--restore=}" != "$restore" ] || die
		[ "${ARGS#*--transaction=}" != "$ARGS" ] || die
		restore="${restore#--restore=}"
		validate_path "$restore" || die
		validate_id "$transaction" || die
		exec "$FWCONSOLE" backup --restore="$restore" --transaction="$transaction"
		;;

	RESTRICT-FWCONSOLE-003)
		set -- $ARGS
		[ "$1" = "--restore" ] || die
		[ -n "$2" ] || die
		[ "${3#--transaction=}" != "$3" ] || die
		[ -n "$3" ] || die
		validate_path "$2" || die
		validate_id "${3#--transaction=}" || die
		exec "$FWCONSOLE" backup --restore "$2" --transaction="${3#--transaction=}"
		;;

	RESTRICT-FWCONSOLE-004)
		extern="${ARGS%% --transaction=*}"
		transaction=""
		if [ "$extern" != "$ARGS" ]; then
			transaction="${ARGS#*--transaction=}"
		fi
		[ "${extern#--externbackup=}" != "$extern" ] || die
		extern="${extern#--externbackup=}"
		validate_base64 "$extern" || die
		if [ -n "$transaction" ]; then
			validate_id "$transaction" || die
			exec "$FWCONSOLE" backup --externbackup="$extern" --transaction="$transaction"
		fi
		exec "$FWCONSOLE" backup --externbackup="$extern"
		;;

	RESTRICT-FWCONSOLE-005)
		[ "$ARGS" = "advr" ] || die
		exec "$FWCONSOLE" advr
		;;

	RESTRICT-FWCONSOLE-006)
		[ "$ARGS" = "advr --genapi" ] || die
		exec "$FWCONSOLE" advr --genapi
		;;

	RESTRICT-FWCONSOLE-007)
		file="${ARGS%% --transaction=*}"
		transaction="${ARGS#*--transaction=}"
		[ "${file#advr --addsecondaryrestorelog --file=}" != "$file" ] || die
		[ "${ARGS#*--transaction=}" != "$ARGS" ] || die
		file="${file#advr --addsecondaryrestorelog --file=}"
		validate_path "$file" || die
		validate_id "$transaction" || die
		exec "$FWCONSOLE" advr --addsecondaryrestorelog --file="$file" --transaction="$transaction"
		;;

	RESTRICT-FWCONSOLE-008)
		[ "${ARGS#advr --unsetprimarydown }" != "$ARGS" ] || die
		id="${ARGS#advr --unsetprimarydown }"
		validate_id "$id" || die
		exec "$FWCONSOLE" advr --unsetprimarydown "$id"
		;;

	RESTRICT-FWCONSOLE-009)
		[ "$ARGS" = "advr --trunksonswitchover" ] || die
		exec "$FWCONSOLE" advr --trunksonswitchover
		;;

	RESTRICT-FWCONSOLE-010)
		[ "${ARGS#advr --runswtichoverhook }" != "$ARGS" ] || die
		id="${ARGS#advr --runswtichoverhook }"
		validate_id "$id" || die
		exec "$FWCONSOLE" advr --runswtichoverhook "$id"
		;;

	RESTRICT-FWCONSOLE-011)
		[ "${ARGS#advr --setprimarydown }" != "$ARGS" ] || die
		id="${ARGS#advr --setprimarydown }"
		validate_id "$id" || die
		exec "$FWCONSOLE" advr --setprimarydown "$id"
		;;

	RESTRICT-FWCONSOLE-012)
		[ "$ARGS" = "pm2 --stop advrecovery" ] || die
		exec "$FWCONSOLE" pm2 --stop advrecovery
		;;

	RESTRICT-FWCONSOLE-013)
		[ "$ARGS" = "pm2 --restart advrecovery" ] || die
		exec "$FWCONSOLE" pm2 --restart advrecovery
		;;

	RESTRICT-TOUCH-001)
		[ -z "$ARGS" ] || die
		exec /usr/bin/touch "$INCRON_DIR/adv_recovery.fwconsole-chown"
		;;

	RESTRICT-TOUCH-002)
		[ -z "$ARGS" ] || die
		exec /usr/bin/touch "$INCRON_DIR/adv_recovery.switchover-reload"
		;;

	RESTRICT-TOUCH-003)
		[ -z "$ARGS" ] || die
		exec /usr/bin/touch "$INCRON_DIR/adv_recovery.fwconsole-restart"
		;;

	RESTRICT-TOUCH-004)
		[ -z "$ARGS" ] || die
		exec /usr/bin/touch "$INCRON_DIR/adv_recovery.asterisk-stop"
		;;

	RESTRICT-TOUCH-005)
		[ -z "$ARGS" ] || die
		exec /usr/bin/touch "$INCRON_DIR/adv_recovery.fwconsole-stop"
		;;

	RESTRICT-LS-001)
		validate_path "$ARGS" || die
		exec /usr/bin/ls -1 -- "$ARGS"
		;;

	RESTRICT-RM-001)
		validate_path "$ARGS" || die
		exec /usr/bin/rm -- "$ARGS"
		;;

	RESTRICT-CD-001)
		validate_path "$ARGS" || die
		exec /bin/bash -c 'cd -- "$1"' _ "$ARGS"
		;;

	*)
		die
		;;
esac
