FROM php:8.2-apache

# Instalar extensões PHP necessárias
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    git \
    curl \
    && docker-php-ext-install \
    pdo_mysql \
    mysqli \
    zip \
    gd \
    mbstring \
    xml \
    opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite do Apache
RUN a2enmod rewrite

# Copiar configuração do Apache
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# Configurar PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Definir diretório de trabalho
WORKDIR /var/www/html

# Copiar arquivos do projeto
COPY . .

# Instalar dependências do Composer sem dev
# Usa PHP para remover require-dev do composer.json (phpunit conflita com phpspreadsheet)
RUN php -r " \
    \$c = json_decode(file_get_contents('composer.json'), true); \
    unset(\$c['require-dev']); \
    file_put_contents('composer.json', json_encode(\$c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); \
    " && \
    rm -f composer.lock && \
    composer update --no-dev --optimize-autoloader --no-interaction --no-audit

# Criar diretório data se não existir e configurar permissões
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/data

# Copiar e configurar entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
