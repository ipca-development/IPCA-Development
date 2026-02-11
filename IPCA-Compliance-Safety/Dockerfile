FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli
RUN a2enmod rewrite

WORKDIR /var/www/html

# Build context IS already backend-php, so copy everything from "."
COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

# Force Apache to serve /public
RUN printf '%s\n' \
'<VirtualHost *:80>' \
'  ServerName localhost' \
'  DocumentRoot /var/www/html/public' \
'  DirectoryIndex index.php index.html' \
'  <Directory /var/www/html/public>' \
'    Options FollowSymLinks' \
'    AllowOverride All' \
'    Require all granted' \
'  </Directory>' \
'</VirtualHost>' \
> /etc/apache2/sites-available/000-default.conf

EXPOSE 80
