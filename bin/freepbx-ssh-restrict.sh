#!/bin/bash
# Restrict incoming SSH to backup / adv_recovery / warm spare operations only.

CMD="${SSH_ORIGINAL_COMMAND:-}"
SFTP_SERVER="/usr/lib/openssh/sftp-server"

if [ ! -x "$SFTP_SERVER" ] && [ -x /usr/lib/sftp-server ]; then
	SFTP_SERVER="/usr/lib/sftp-server"
fi

# SFTP subsystem (filestore backup upload/download)
if [ -z "$CMD" ]; then
	exec "$SFTP_SERVER"
fi

case "$CMD" in
	# SFTP
	sftp|/usr/lib/openssh/sftp-server|/usr/lib/sftp-server|/usr/lib/openssh/sftp-server\ *)
		exec "$SFTP_SERVER"
		;;

	# Warm spare + adv recovery restore
	/usr/sbin/fwconsole\ backup\ --restore*)
		exec /bin/bash -c "$CMD"
		;;
	/usr/sbin/fwconsole\ backup\ --externbackup*)
		exec /bin/bash -c "$CMD"
		;;

	# Advanced Recovery
	/usr/sbin/fwconsole\ advr)
		exec /bin/bash -c "$CMD"
		;;
	/usr/sbin/fwconsole\ advr\ --*)
		exec /bin/bash -c "$CMD"
		;;

	# Service control
	/usr/sbin/fwconsole\ pm2\ --stop\ advrecovery*)
		exec /bin/bash -c "$CMD"
		;;
	/usr/sbin/fwconsole\ pm2\ --restart\ advrecovery*)
		exec /bin/bash -c "$CMD"
		;;

	# Incron hooks
	/usr/bin/touch\ /var/spool/asterisk/incron/adv_recovery.*)
		exec /bin/bash -c "$CMD"
		;;

	# Filesystem
	/usr/bin/mkdir\ -p\ *)
		exec /bin/bash -c "$CMD"
		;;

	# Asterisk
	asterisk|asterisk\ *)
		exec /bin/bash -c "$CMD"
		;;
	/usr/sbin/asterisk|/usr/sbin/asterisk\ *)
		exec /bin/bash -c "$CMD"
		;;

	# Legacy backup maintenance + filestore connection test
	ls\ -1\ *)
		exec /bin/bash -c "$CMD"
		;;
	rm\ *)
		exec /bin/bash -c "$CMD"
		;;
	cd\ *)
		exec /bin/bash -c "$CMD"
		;;

	*)
		echo "command not allowed" >&2
		exit 1
		;;
esac
