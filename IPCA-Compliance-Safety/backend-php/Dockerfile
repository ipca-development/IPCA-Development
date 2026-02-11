FROM php:8.3-apache

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache rewrite
RUN a2enmod rewrite

# Change DocumentRoot to /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf || true

# Allow .htaccess overrides
RUN { \
  echo '<Directory /var/www/html/public>'; \
  echo '    AllowOverride All'; \
  echo '    Require all granted'; \
  echo '</Directory>'; \
} > /etc/apache2/conf-available/ipca.conf && a2enconf ipca

WORKDIR /var/www/html
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
