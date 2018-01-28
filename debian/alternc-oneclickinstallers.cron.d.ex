#
# Regular cron jobs for the alternc-oneclickinstallers package
#
0 4	* * *	root	[ -x /usr/bin/alternc-oneclickinstallers_maintenance ] && /usr/bin/alternc-oneclickinstallers_maintenance
