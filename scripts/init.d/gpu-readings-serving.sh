#!/bin/bash
#should be in /etc/init.d

case "$1" in
start)
   {
   pushd .
   cd /tmp/gpuReadings
   python -m SimpleHTTPServer 8114 &
   echo $!>/var/run/gpu-reading.pid
   popd
   } &> /dev/null
   ;;
stop)
   kill `cat /var/run/gpu-reading.pid`
   rm /var/run/gpu-reading.pid
   ;;
restart)
   $0 stop
   $0 start
   ;;
status)
   if [ -e /var/run/gpu-reading.pid ]; then
      echo SimpleHTTPServer 8114 is running, pid=`cat /var/run/gpu-reading.pid`
   else
      echo SimpleHTTPServer 8114 is NOT running
      exit 1
   fi
   ;;
*)
   echo "Usage: $0 {start|stop|status|restart}"
esac

exit 0
