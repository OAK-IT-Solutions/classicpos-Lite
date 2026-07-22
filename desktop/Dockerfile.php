# ClassicPOS Static PHP Builder
# WARNING: This produces a LINUX binary (ELF format).
# For Windows builds, use spc.exe locally: see build-php-windows.bat
# For macOS builds, use spc on macOS or cross-compile in CI

FROM ubuntu:24.04 AS builder

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    curl git build-essential autoconf automake libtool pkg-config bison re2c \
    cmake ninja-build libxml2-dev libsqlite3-dev libssl-dev libcurl4-openssl-dev \
    libpng-dev libjpeg-dev libfreetype-dev libzip-dev libonig-dev libreadline-dev \
    zip unzip flex gperf autopoint gettext ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL https://dl.static-php.dev/v3/spc-bin/nightly/spc-linux-x86_64 \
    -o /usr/local/bin/spc && chmod +x /usr/local/bin/spc

WORKDIR /build
COPY craft.yml .

RUN spc doctor --auto-fix || true

RUN spc build:php \
    "pdo_sqlite,sqlite3,bcmath,ctype,dom,fileinfo,filter,iconv,mbstring,pcntl,session,tokenizer,openssl,sodium,curl,libxml,simplexml,xml,xmlreader,xmlwriter,zip,zlib,gd,json" \
    --build-cli \
    --dl-with-php=8.4 \
    --dl-parallel=10

RUN mkdir -p /app/output && cp /build/buildroot/bin/php /app/output/php && chmod +x /app/output/php && /app/output/php -v

FROM scratch AS output
COPY --from=builder /app/output/php /php
