# Install Apache
sudo yum install httpd
sudo service httpd start

# Install PHP
sudo yum install php php-mysql

# Set the processes to run automatically when the server boots
sudo chkconfig httpd on

# Copy the main page at the proper place to be served
sudo cp -r * /var/www/html
sudo service httpd restart

# Install the scripts that will be ran regularly for fetching metrics etc.
sudo mkdir -p /var/opt/gpu-monitor/scripts
sudo cp ./scripts/* /var/opt/gpu-monitor/scripts
sudo cp ./scripts/fetch_stats.py /var/www/html/data/fetch_stats.py

sudo cp ./scripts/init.d/* /etc/init.d

sudo chmod 755 /etc/init.d/gpu-readings-fetching.sh
sudo systemctl daemon-reload

