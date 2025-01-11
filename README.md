# glued-core
Core microservice for Glued

## Installing glued-core
```bash
# Set up prerequisites and package holds
sudo apt update
sudo apt install -y ca-certificates curl gnupg software-properties-common
sudo apt-mark hold apache2
sudo mkdir -p /etc/apt/keyrings

# Add repositories
LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php -y
LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/nginx-mainline -y
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo bash -
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | sudo gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
curl https://packages.microsoft.com/config/ubuntu/$(lsb_release -rs)/prod.list | sudo tee /etc/apt/sources.list.d/msprod.list
curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | sudo gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg
curl https://packages.microsoft.com/config/ubuntu/22.04/prod.list | sudo tee /etc/apt/sources.list.d/mssql-release.list
sudo install -d /usr/share/postgresql-common/pgdg
sudo curl -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc --fail https://www.postgresql.org/media/keys/ACCC4CF8.asc
sudo sh -c 'echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'

# Install base packages
sudo apt-get update
apt install -y nginx libnginx-mod-http-headers-more-filter php8.3 php8.3-fpm php8.3-apcu php8.3-bcmath php8.3-curl php8.3-dev php8.3-gd php8.3-gmp php8.3-imap php-json php8.3-pgsql php8.3-mbstring php8.3-mysql php-pear php8.3-readline php8.3-soap php8.3-xml php8.3-yaml php8.3-zip apache2-utils git mysql-server postgresql sshpass

# Pin php version and setup composer
sudo update-alternatives --set php /usr/bin/php8.3
sudo curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer

# Install dbmate
sudo curl -fsSL -o /usr/local/bin/dbmate https://github.com/amacneil/dbmate/releases/latest/download/dbmate-linux-amd64
sudo chmod +x /usr/local/bin/dbmate

# Install mssql support
ACCEPT_EULA=Y apt install -y msodbcsql18 mssql-tools18 unixodbc-dev php-pear php-dev
pecl update-channels
pecl upgrade sqlsrv pdo_sqlsrv
echo "extension=pdo_sqlsrv.so" > /etc/php/$(php --ini | grep Loaded | cut -d'/' -f4)/mods-available/pdo_sqlsrv.ini
echo "extension=sqlsrv.so" > /etc/php/$(php --ini | grep Loaded | cut -d'/' -f4)/mods-available/sqlsrv.ini
phpenmod pdo_sqlsrv
phpenmod sqlsrv
echo 'export PATH="$PATH:/opt/mssql-tools18/bin"' >> ~/.bashrc
sudo sed -i '/^#.*local\s*all\s*all\s*peer/b; /local\s*all\s*all\s*peer/s/^/#/' /etc/postgresql/17/main/pg_hba.conf
grep -q "local\s*all\s*all\s*scram-sha-256" /etc/postgresql/17/main/pg_hba.conf || echo "local   all   all   scram-sha-256" | sudo tee -a /etc/postgresql/17/main/pg_hba.conf
grep -q "host\s*all\s*all\s*all\s*scram-sha-256" /etc/postgresql/17/main/pg_hba.conf || echo "host    all   all   all   scram-sha-256" | sudo tee -a /etc/postgresql/17/main/pg_hba.conf

# Install node
sudo apt install -y nodejs
sudo corepack enable

# Install helper tools
sudo apt install -y jq mc httpie

# Restart services
systemctl start php8.3-fpm
systemctl start nginx
systemctl enable php8.3-fpm
systemctl enable nginx
systemctl start postgresql
systemctl enable postgresql
systemctl restart postgresql
systemctl start mysql
systemctl enable mysql

# Setup postgres
sudo -u postgres psql -c "CREATE DATABASE glued"
sudo -u postgres psql -c "CREATE USER glued WITH PASSWORD 'glued-pw'"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE glued TO glued"

# Setup mysql
mysql -e "CREATE DATABASE glued /*\!40100 DEFAULT CHARACTER SET utf8 */;"
mysql -e "CREATE USER glued@localhost IDENTIFIED BY 'glued-pw';"
mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'glued'@'localhost';"
mysql -e "GRANT SUPER ON *.* TO 'glued'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
mysql -e "CREATE USER 'glued'@'%' IDENTIFIED BY 'glued-pw';"
mysql -e "GRANT ALL PRIVILEGES ON glued.* TO 'glued'@'%';"
mysql -e "GRANT SUPER ON *.* TO 'glued'@'%';"
mysql -e "FLUSH PRIVILEGES;"

# Deploy glued-core
pushd /var/www/html
git clone https://github.com/vaizard/glued-core.git
pushd glued-core
[ ! -f ../.env ] && cp .env.dist ../.env
[ ! -f .env ] && ln -s ../.env .env
COMPOSER_ALLOW_SUPERUSER=1 composer update --no-interaction --no-dev
COMPOSER_ALLOW_SUPERUSER=1 composer nginx --no-interaction
```

Running the above will setup glued-core according to .env.dist configuration. If you need to change anything here,
modify the script above. What you end with is

- all dependencies setup
- glued-core installed in /var/www/html/glued-core
- nginx configured to use glued-core as the default server responding to https://glued (using a self-signed certificate)

## Installing additional services

To install additional glued services do

```bash
pushd /var/www/html
git clone https://github.com/vaizard/glued-<service>.git
pushd glued-<service>
[ ! -f .env ] && ln -s ../.env .env
COMPOSER_ALLOW_SUPERUSER=1 composer update --no-interaction --no-dev
COMPOSER_ALLOW_SUPERUSER=1 composer nginx --no-interaction
```

Additional services will (if installed as described above)

- install their own nginx config that integrates into what glued-core provides to run on a dedicated port (8001, 8002, etc.)
- use the common .env file located in /var/www/html
- use /var/www/html/data/glued-<service> to store their data, logs and configuration
- (this is then read by glued-core to)

Data exposed in /var/www/html/data/*/cache/*yaml (i.e. openapi, routes) are then consumed by glued-core.



