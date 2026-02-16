# Usa l'immagine base PHP con Apache
FROM php:8.2-apache

# Installa le estensioni PHP necessarie e utility per Composer
# CRITICO: zip extension + unzip + git sono necessari per Composer
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    libzip-dev \
    msmtp \
    msmtp-mta \
    && docker-php-ext-install zip mysqli pdo pdo_mysql \
    && docker-php-ext-enable zip mysqli \
    && rm -rf /var/lib/apt/lists/*

# Configura msmtp per usare Mailpit
RUN echo "account default\n\
host mailpit\n\
port 1025\n\
from no-reply@bibliotech.local\n\
auth off\n\
tls off\n\
logfile /var/log/msmtp.log" > /etc/msmtprc

# Configura PHP per usare msmtp come sendmail
RUN echo "sendmail_path = /usr/bin/msmtp -t" > /usr/local/etc/php/conf.d/mail.ini

# Crea il file di log con i permessi corretti
RUN touch /var/log/msmtp.log && chmod 666 /var/log/msmtp.log

# Installa Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Imposta la directory di lavoro
WORKDIR /var/www/html

# Copia composer.json e composer.lock per installare le dipendenze
COPY composer.json composer.lock ./

# Installa le dipendenze PHP con Composer
# Ora funzionerà perché abbiamo zip extension, unzip e git
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copia il codice PHP nel container
COPY ./src /var/www/html

# Assicurati che Apache abbia i permessi corretti
RUN chown -R www-data:www-data /var/www/html
