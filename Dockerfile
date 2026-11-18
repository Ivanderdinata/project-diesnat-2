FROM php:8.2-apache

# Salin semua file project ke folder root web Apache
COPY . /var/www/html/

# Install ekstensi database yang mungkin dibutuhkan
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Ubah konfigurasi Apache agar mendengarkan port dinamis dari Railway ($PORT)
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Izinkan mod_rewrite jika project Anda pakai .htaccess untuk routing
RUN a2enmod rewrite