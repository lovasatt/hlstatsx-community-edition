FROM debian:trixie-slim
RUN apt-get update && apt-get install -y \
    perl libdbi-perl libdbd-mysql-perl libio-socket-inet6-perl \
    cpanminus build-essential libssl-dev zlib1g-dev \
    libmaxminddb-dev netcat-openbsd cron && rm -rf /var/lib/apt/lists/*
RUN cpanm MaxMind::DB::Reader GeoIP2::Database::Reader Syntax::Keyword::Try
RUN useradd -m hlstats
WORKDIR /home/hlstats/scripts
COPY --chown=hlstats:hlstats src/scripts /home/hlstats/scripts
RUN sed -i 's/DBHost "localhost"/DBHost "db"/' hlstats.conf
RUN chmod +x *.pl
USER hlstats
CMD until nc -z db 3306; do echo "Waiting for DB..."; sleep 2; done; perl hlstats.pl