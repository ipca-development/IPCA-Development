FROM php:8.3-apache

# PHP extensions commonly needed
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache modules
RUN a2enmod rewrite

# Point Apache to /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Update Apache configs to use the new docroot
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf || true

# Allow .htaccess overrides (needed for routing/rewrite)
RUN { \
  echo '<Directory /var/www/html/public>'; \
  echo '    AllowOverride All'; \
  echo '    Require all granted'; \
  echo '</Directory>'; \
} > /etc/apache2/conf-available/ipca-public.conf && a2enconf ipca-public

WORKDIR /var/www/html
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html
EXPOSE 80
