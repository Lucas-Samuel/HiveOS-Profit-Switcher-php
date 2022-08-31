#!/usr/bin/env bash

sudo apt update -y
sudo apt install php php-curl -y

cd /home/user

wget https://github.com/Lucas-Samuel/HiveOS-Profit-Switcher/archive/refs/heads/main.zip
unzip profit.zip -d /usr/profit-switcher

printf "\n*/5 * * * * /usr/bin/php /usr/profit-switcher/profit.php >> /usr/profit-switcher/profit.log\n" >> /hive/etc/crontab.root