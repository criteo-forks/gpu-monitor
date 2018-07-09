#!/bin/bash

case "$1" in
start)
   {
   pushd .
   cd /var/www/html/data
   python  fetch_stats.py &
   echo $!>/var/run/gpu-fetching.pid
   popd
   } &> /dev/null
   ;;
stop)
   kill `cat /var/run/gpu-fetching.pid`
   rm /var/run/gpu-fetching.pid
   ;;
restart)
   $0 stop
   $0 start
   ;;
status)
   if [ -e /var/run/gpu-fetching.pid ]; then
      echo GPU stats fetching is running, pid=`cat /var/run/gpu-fetching.pid`
   else
      echo GPU stats fetching is NOT running
      exit 1
   fi
   ;;
*)
   echo "Usage: $0 {start|stop|status|restart}"
esac

exit 0
