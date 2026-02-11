FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli
RUN a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

# Force Apache to serve /public
RUN printf '%s\n' \
'<VirtualHost *:80>' \
'    DocumentRoot /var/www/html/public' \
'    <Directory /var/www/html/public>' \
'        AllowOverride All' \
'        Require all granted' \
'    </Directory>' \
'</VirtualHost>' \
> /etc/apache2/sites-available/000-default.conf

EXPOSE 80
