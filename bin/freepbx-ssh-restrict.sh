#!/bin/bash
# Restrict incoming SSH to backup / adv_recovery / warm spare operations only.

CMD="${SSH_ORIGINAL_COMMAND:-}"
LOG="/var/log/asterisk/freepbx-ssh-restrict.log"
SFTP_SERVER="/usr/lib/openssh/sftp-server"

if [ ! -x "$SFTP_SERVER" ] && [ -x /usr/lib/sftp-server ]; then
	SFTP_SERVER="/usr/lib/sftp-server"
fi

log_msg() {
	printf '%s from=%s user=%s cmd=[%s] %s\n' \
		"$(date '+%Y-%m-%d %H:%M:%S')" \
		"${SSH_CLIENT:-unknown}" \
		"${USER:-unknown}" \
		"$CMD" \
		"$1" >> "$LOG"
}

# SFTP subsystem (filestore backup upload/download)
if [ -z "$CMD" ]; then
	log_msg "allow:sftp-empty"
	exec "$SFTP_SERVER"
fi

case "$CMD" in
	# SFTP
	sftp|/usr/lib/openssh/sftp-server|/usr/lib/sftp-server|/usr/lib/openssh/sftp-server\ *)
		log_msg "allow:sftp"
		exec "$SFTP_SERVER"
		;;

	# Warm spare + adv recovery restore
	/usr/sbin/fwconsole\ backup\ --restore*)
		log_msg "allow"
		exec /bin/bash -c "$CMD"
		;;
	/usr/sbin/fwconsole\ backup\ --externbackup*)
		log_msg "allow"
		exec /bin/bash -c "$CMD"
		;;

	# Advanced Recovery
	/usr/sbin/fwconsole\ advr)
		log_msg "allow"
		exec /bin/bash -c "$CMD"
		;;
	/usr/sbin/fwconsole\ advr\ --*)
		log_msg "allow"
		exec /bin/bash -c "$CMD"
		;;

	# Service control
	/usr/sbin/fwconsole\ pm2\ --stop\ advrecovery*)
		log_msg "allow"
		exec /bin/bash -c "$CMD"
		;;
	/usr/sbin/fwconsole\ pm2\ --restart\ advrecovery*)
		log_msg "allow"
		exec /bin/bash -c "$CMD"
		;;

	# Incron hooks
	/usr/bin/touch\ /var/spool/asterisk/incron/adv_recovery.*)
		log_msg "allow"
		exec /bin/bash -c "$CMD"
		;;

	# Filesystem
	/usr/bin/mkdir\ -p\ *)
		log_msg "allow"
		exec /bin/bash -c "$CMD"
		;;

	# Asterisk
	asterisk|asterisk\ *)
		log_msg "allow"
		exec /bin/bash -c "$CMD"
		;;
	/usr/sbin/asterisk|/usr/sbin/asterisk\ *)
		log_msg "allow"
		exec /bin/bash -c "$CMD"
		;;

	# Legacy backup maintenance + filestore connection test
	ls\ -1\ *)
		log_msg "allow"
		exec /bin/bash -c "$CMD"
		;;
	rm\ *)
		log_msg "allow"
		exec /bin/bash -c "$CMD"
		;;
	cd\ *)
		log_msg "allow"
		exec /bin/bash -c "$CMD"
		;;

	*)
		log_msg "deny"
		echo "command not allowed" >&2
		exit 1
		;;
esac
