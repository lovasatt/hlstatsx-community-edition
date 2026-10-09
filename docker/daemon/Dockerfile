FROM debian:trixie-slim

RUN export DEBIAN_FRONTEND=noninteractive && \
    apt-get update && apt-get upgrade -y && apt-get install -y --no-install-recommends \
    perl \
    libdbi-perl \
    libdbd-mysql-perl \
    libio-socket-inet6-perl \
    libmath-int64-perl \
    libmaxmind-db-reader-perl \
    libgeoip2-perl \
    libsyntax-keyword-try-perl \
    netcat-openbsd \
    cron \
    gosu \
    && apt-get clean \
    && apt-get autoclean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /var/cache/apt/archives/*

RUN sed -i '/pam_loginuid.so/s/^/#/' /etc/pam.d/cron

RUN useradd -m hlstats
WORKDIR /home/hlstats/scripts

COPY src/scripts /home/hlstats/scripts
COPY docker/daemon/hlx-cron /etc/cron.d/hlx-cron

RUN find /home/hlstats/scripts -type f \( -name "*.pl" -o -name "*.conf" -o -name "*.sh" -o -name "*.plib" \) -exec sed -i 's/\r$//' {} + && \
    sed -i 's/\r$//' /etc/cron.d/hlx-cron && \
    chmod 0644 /etc/cron.d/hlx-cron && \
    chmod +x /home/hlstats/scripts/*.pl && \
    chown -R hlstats:hlstats /home/hlstats/scripts

CMD cd /home/hlstats/scripts && \
    sed -i "s|^DBHost .*|DBHost \"hlx-db\"|" hlstats.conf && \
    sed -i "s|^DBUsername .*|DBUsername \"$DB_USER\"|" hlstats.conf && \
    sed -i "s|^DBPassword .*|DBPassword \"$DB_PASS\"|" hlstats.conf && \
    sed -i "s|^DBName .*|DBName \"$DB_NAME\"|" hlstats.conf && \
    cron && \
    until nc -z hlx-db 3306; do echo "Waiting for DB..."; sleep 2; done; \
    exec gosu hlstats perl hlstats.pl --port 27500