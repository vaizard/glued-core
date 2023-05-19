# glued-core
Core microservice for Glued

```
# Ubuntu is really slow on backporting fixes. We use sury
LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php -y
LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/nginx-mainline -y
apt update


apt install -y nginx libnginx-mod-http-headers-more-filter php php-fpm php-apcu php-bcmath php-curl php-dev php-gd php-gmp php-imap php-json php-mbstring php-mysql php-pear php-readline php-soap php-xml php-zip apache2-utils git mysql-server jq mc
systemctl start php8.2-fpm
systemctl start nginx
systemctl enable php8.2-fpm
systemctl enable nginx
sudo curl -fsSL -o /usr/local/bin/dbmate https://github.com/amacneil/dbmate/releases/latest/download/dbmate-linux-amd64
sudo chmod +x /usr/local/bin/dbmate
sudo curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
```

```
mysql -e "CREATE DATABASE glued /*\!40100 DEFAULT CHARACTER SET utf8 */;"

mysql -e "CREATE USER glued@localhost IDENTIFIED BY 'glued-pw';"
mysql -e "GRANT ALL PRIVILEGES ON glued.* TO 'glued'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

mysql -e "CREATE USER 'glued'@'%' IDENTIFIED BY 'glued-pw';"
mysql -e "GRANT ALL PRIVILEGES ON glued.* TO 'glued'@'%';"
mysql -e "FLUSH PRIVILEGES;"
```