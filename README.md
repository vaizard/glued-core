# glued-core
Core microservice for Glued

```
add-apt-repository ppa:ondrej/nginx-mainline -y
apt install nginx libnginx-mod-http-headers-more-filter php php-fpm php-apcu php-bcmath php-curl php-gd php-gmp php-imap php-json php-mbstring php-mysqli php-readline php-soap php-xml apache2-utils composer git mysql-server
systemctl start php8.1-fpm
systemctl start nginx
sudo curl -fsSL -o /usr/local/bin/dbmate https://github.com/amacneil/dbmate/releases/latest/download/dbmate-linux-amd64
sudo chmod +x /usr/local/bin/dbmate
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