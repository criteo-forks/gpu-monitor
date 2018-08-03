#!/bin/bash
# Install all the needed pieces to collect and serve the status of GPU
# To execute it, just do 'sudo /bin/bash install_nodes.sh'

set -x
set -e

sudo mkdir -p /var/opt/gpu-monitor/scripts
sudo cp ./scripts/* /var/opt/gpu-monitor/scripts

sudo cp ./scripts/init.d/* /etc/init.d

sudo chmod 755 /etc/init.d/gpu-readings-serving.sh
sudo systemctl daemon-reload

sudo mkdir /tmp/gpuReadings

sudo crontab -l > mycron

echo "# Check if monitoring running each 5 min" >> mycron
echo "*/5 * * * *  /var/opt/gpu-monitor/scripts/gpu-check.sh $(hostname) > /dev/null 2>&1" >> mycron
echo "# Kill and restart the monitoring each 2 hours to cleanup the ouptput files of the monitors" >> mycron
echo "0 */2 * * *  /var/opt/gpu-monitor/scripts/gpu-check.sh kill > /dev/null 2>&1; /var/opt/gpu-monitor/scripts/gpu-check.sh $(hostname) > /dev/null 2>&1" >> mycron

sudo crontab mycron
cat mycron
rm mycron

sudo /sbin/service gpu-readings-serving start
