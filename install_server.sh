#!/bin/bash
# Install all the needed pieces to run UI and display status of GPU
# To execute it, just do 'sudo /bin/bash install_server.sh'

set -x
set -e

# Install Apache
sudo yum -y install httpd
sudo service httpd start

# Install PHP
sudo yum -y install php

# Set the processes to run automatically when the server boots
sudo chkconfig httpd on

# Copy the main page at the proper place to be served
sudo cp -r * /var/www/html

pushd /var/www/html
sudo chmod a+r -R .
sudo chmod a+x . css fonts js
popd

sudo service httpd restart

# Install the scripts that will be ran regularly for fetching metrics etc.
sudo mkdir -p /var/opt/gpu-monitor/scripts
sudo cp ./scripts/*.py ./scripts/*.sh /var/opt/gpu-monitor/scripts


sudo mkdir -p /var/www/html/data
sudo chmod a+w /var/www/html/data
sudo cp ./scripts/fetch_stats.py /var/www/html/data/fetch_stats.py

sudo cp ./scripts/init.d/* /etc/init.d

sudo chmod 755 /etc/init.d/gpu-readings-fetching.sh
sudo systemctl daemon-reload

sudo /sbin/service gpu-readings-fetching start
