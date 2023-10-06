# glued-core
Core microservice for Glued

```
# Prereqs
sudo apt update
sudo apt install -y ca-certificates curl gnupg
sudo mkdir -p /etc/apt/keyrings

# Ubuntu is really slow on some packages. We add ppas
LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php -y
LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/nginx-mainline -y
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo bash -
curl https://packages.microsoft.com/config/ubuntu/$(lsb_release -rs)/prod.list | sudo tee /etc/apt/sources.list.d/msprod.list
curl https://packages.microsoft.com/keys/microsoft.asc | sudo tee /etc/apt/trusted.gpg.d/microsoft.asc
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | sudo gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg

# base
apt install -y nginx libnginx-mod-http-headers-more-filter php php-fpm php-apcu php-bcmath php-curl php-dev php-gd php-gmp php-imap php-json php-mbstring php-mysql php-pear php-readline php-soap php-xml php-zip apache2-utils git mysql-server
# composer
sudo curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
# mssql
apt install -y msodbcsql18 mssql-tools18 unixodbc-dev php-pear php-dev
pecl update-channels
pecl upgrade sqlsrv pdo_sqlsrv
echo "extension=pdo_sqlsrv.so" > /etc/php/$(php --ini | grep Loaded | cut -d'/' -f4)/mods-available/pdo_sqlsrv.ini
echo "extension=sqlsrv.so" > /etc/php/$(php --ini | grep Loaded | cut -d'/' -f4)/mods-available/sqlsrv.ini
phpenmod pdo_sqlsrv
phpenmod sqlsrv
echo 'export PATH="$PATH:/opt/mssql-tools18/bin"' >> ~/.bashrc

# tools
apt install -y jq mc
snap install httpie
# node

apt install -y nodejs
# dbmate
sudo curl -fsSL -o /usr/local/bin/dbmate https://github.com/amacneil/dbmate/releases/latest/download/dbmate-linux-amd64
sudo chmod +x /usr/local/bin/dbmate

systemctl start php8.2-fpm
systemctl start nginx
systemctl enable php8.2-fpm
systemctl enable nginx
```

```
mysql -e "CREATE DATABASE glued /*\!40100 DEFAULT CHARACTER SET utf8 */;"

mysql -e "CREATE USER glued@localhost IDENTIFIED BY 'glued-pw';"
mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'glued'@'localhost';"
mysql -e "GRANT SUPER ON *.* TO 'glued'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

mysql -e "CREATE USER 'glued'@'%' IDENTIFIED BY 'glued-pw';"
mysql -e "GRANT ALL PRIVILEGES ON glued.* TO 'glued'@'%';"
mysql -e "GRANT SUPER ON *.* TO 'glued'@'%';"
mysql -e "FLUSH PRIVILEGES;"
```

## Coding style

### Naming conventions



| Convention | JSON path     | PHP Class names | PHP method names | URIs      | Database tables/columns |
|------------|---------------|-----------------|------------------|-----------|-------------------------|
| camelCase  | supported     |                 | supported        |           | tolerated               |
| PascalCase | supported     | preferred       |                  |           |                         |
| snake_case | unsupported*) |                 |                  |           | preferred               |
| kebab-case | preferred     |                 | preferred        | preferred |                         |

*) the underscore will be 
