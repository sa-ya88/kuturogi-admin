FROM mcr.microsoft.com/devcontainers/php:3-8.3-trixie

# OS packages + PHP extensions for Laravel / Filament
RUN apt-get update && export DEBIAN_FRONTEND=noninteractive \
    && apt-get install -y \
        mariadb-client \
        libsqlite3-dev \
        libicu-dev \
        libzip-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        intl \
        mysqli \
        pdo_mysql \
        pdo_sqlite \
        zip \
    && apt-get clean -y && rm -rf /var/lib/apt/lists/*

# Node.js for Vite frontend builds
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean -y && rm -rf /var/lib/apt/lists/*
