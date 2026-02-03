# glued-core
Core microservice for Glued

## Running in incus

```bash
sudo incus launch images:ubuntu/noble/cloud glued
incus config device add glued projects disk source=/home/$USER/Projects path=/var/www/html shift=true
```

## Installing glued-core
```bash
#!/bin/bash
set -e

# Detect Ubuntu codename/version
DISTRO_CODENAME=$(lsb_release -cs)
DISTRO_VERSION=$(lsb_release -rs)

# Update & base setup
sudo apt update
sudo apt install -y ca-certificates curl gnupg software-properties-common
sudo apt-mark hold apache2
sudo mkdir -p /etc/apt/keyrings

# PHP + NGINX stable (NOT mainline)
sudo add-apt-repository -y ppa:ondrej/php
sudo add-apt-repository -y ppa:ondrej/nginx

# Node.js 20 (official)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | sudo gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg

# Microsoft SQL repo
curl -fsSL https://packages.microsoft.com/config/ubuntu/$DISTRO_VERSION/prod.list | sudo tee /etc/apt/sources.list.d/msprod.list
curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | sudo gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg

# PostgreSQL official repo
sudo install -d /usr/share/postgresql-common/pgdg
sudo curl -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc https://www.postgresql.org/media/keys/ACCC4CF8.asc
echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt ${DISTRO_CODENAME}-pgdg main" | sudo tee /etc/apt/sources.list.d/pgdg.list

# Update all sources
sudo apt-get update

# Install NGINX + headers-more
sudo apt install -y nginx libnginx-mod-http-headers-more-filter

# PHP 8.3 + extensions
sudo apt install -y \
  php8.3 php8.3-fpm php8.3-apcu php8.3-bcmath php8.3-curl php8.3-dev \
  php8.3-gd php8.3-gmp php8.3-imap php-json php8.3-pgsql php8.3-mbstring \
  php8.3-mysql php-pear php8.3-readline php8.3-soap php8.3-xml php8.3-yaml php8.3-zip

sudo update-alternatives --set php /usr/bin/php8.3

# Composer
sudo curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer

# dbmate
sudo curl -fsSL -o /usr/local/bin/dbmate https://github.com/amacneil/dbmate/releases/latest/download/dbmate-linux-amd64
sudo chmod +x /usr/local/bin/dbmate

# git
sudo apt install git
git config --global --add safe.directory /var/www/html/glued-core

# MSSQL ODBC + PHP
curl -sSL -O https://packages.microsoft.com/config/ubuntu/${DISTRO_VERSION}/packages-microsoft-prod.deb
sudo dpkg -i packages-microsoft-prod.deb
rm packages-microsoft-prod.deb
sudo apt-get update
sudo ACCEPT_EULA=Y apt-get install -y msodbcsql18 mssql-tools18
echo 'export PATH="$PATH:/opt/mssql-tools18/bin"' | sudo tee /etc/profile.d/mssql-tools.sh
source /etc/profile.d/mssql-tools.sh
sudo apt-get install -y unixodbc-dev
sudo pecl update-channels
sudo pecl upgrade sqlsrv pdo_sqlsrv
PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
echo "extension=pdo_sqlsrv.so" | sudo tee /etc/php/${PHP_VER}/mods-available/pdo_sqlsrv.ini
echo "extension=sqlsrv.so"     | sudo tee /etc/php/${PHP_VER}/mods-available/sqlsrv.ini
sudo phpenmod pdo_sqlsrv sqlsrv

# PostgreSQL + MySQL + SSHpass
sudo apt install -y postgresql mysql-server sshpass

# Node.js + corepack
sudo apt install -y nodejs
sudo corepack enable

# CLI helpers
sudo apt install -y jq mc httpie

# Enable services
sudo systemctl enable --now php8.3-fpm nginx postgresql mysql
sudo systemctl restart postgresql

# PostgreSQL auth config
PG_HBA=/etc/postgresql/17/main/pg_hba.conf
sudo sed -i '/^#.*local\s\+all\s\+all\s\+peer/b; /local\s\+all\s\+all\s\+peer/s/^/#/' "$PG_HBA"
sudo grep -q "local\s\+all\s\+all\s\+scram-sha-256" "$PG_HBA" || echo "local   all   all   scram-sha-256" | sudo tee -a "$PG_HBA"
sudo grep -q "host\s\+all\s\+all\s\+all\s\+scram-sha-256" "$PG_HBA" || echo "host    all   all   all   scram-sha-256" | sudo tee -a "$PG_HBA"
sudo systemctl restart postgresql

# PostgreSQL DB + user
sudo -u postgres psql <<EOF
CREATE DATABASE glued;
CREATE USER glued WITH PASSWORD 'glued-pw';
GRANT ALL PRIVILEGES ON DATABASE glued TO glued;
EOF

# MySQL DB + user
sudo mysql -uroot <<EOF
CREATE DATABASE IF NOT EXISTS glued /*!40100 DEFAULT CHARACTER SET utf8 */;
CREATE USER IF NOT EXISTS 'glued'@'localhost' IDENTIFIED BY 'glued-pw';
GRANT ALL PRIVILEGES ON *.* TO 'glued'@'localhost';
GRANT SUPER ON *.* TO 'glued'@'localhost';
CREATE USER IF NOT EXISTS 'glued'@'%' IDENTIFIED BY 'glued-pw';
GRANT ALL PRIVILEGES ON glued.* TO 'glued'@'%';
GRANT SUPER ON *.* TO 'glued'@'%';
FLUSH PRIVILEGES;
EOF

# Deploy glued-core
sudo mkdir -p /var/www/html
cd /var/www/html
git clone https://github.com/vaizard/glued-core.git
cd glued-core
[ ! -f ../.env ] && cp .env.dist ../.env
[ ! -f .env ] && ln -s ../.env .env
COMPOSER_ALLOW_SUPERUSER=1 composer update --no-interaction --no-dev
sudo COMPOSER_ALLOW_SUPERUSER=1 composer nginx --no-interaction
```

Running the above will setup glued-core according to .env.dist configuration. If you need to change anything here,
modify the script above. What you end with is

- all dependencies setup
- glued-core installed in /var/www/html/glued-core
- nginx configured to use glued-core as the default server responding to https://glued (using a self-signed certificate). Additionally, https://openapi.glued will be accessible too. Make sure to setup your DNS.
- make sure to set up correct 

## Installing additional services

To install additional glued services do

```bash
pushd /var/www/html
git clone https://github.com/vaizard/glued-<service>.git
pushd glued-<service>
[ ! -f .env ] && ln -s ../.env .env
COMPOSER_ALLOW_SUPERUSER=1 composer update --no-interaction --no-dev
sudo COMPOSER_ALLOW_SUPERUSER=1 composer nginx --no-interaction
```

Additional services will (if installed as described above)

- install their own nginx config that integrates into what glued-core provides to run on a dedicated port (8001, 8002, etc.)
- use the common .env file located in /var/www/html
- use /var/www/html/data/glued-<service> to store their data, logs and configuration
- (this is then read by glued-core to)

Data exposed in /var/www/html/data/*/cache/*yaml (i.e. openapi, routes) are then consumed by glued-core.


## Usage

- https://glued/api/core/v1/routes lists all routes
- https://glued/api/core/v1/openapis lists all openapi specs exposed to glued-core
