FROM debian:trixie-slim

RUN apt-get update && apt-get install -y \
    perl libdbi-perl libdbd-mysql-perl libio-socket-inet6-perl \
    cpanminus build-essential libssl-dev zlib1g-dev \
    libmaxminddb-dev netcat-openbsd cron && rm -rf /var/lib/apt/lists/*

RUN cpanm --notest Math::Int64 MaxMind::DB::Reader GeoIP2::Database::Reader Syntax::Keyword::Try

RUN useradd -m hlstats
WORKDIR /home/hlstats/scripts
COPY src/scripts /home/hlstats/scripts

RUN sed -i 's/DBHost\s*"localhost"/DBHost "db"/' /home/hlstats/scripts/hlstats.conf
RUN chmod +x /home/hlstats/scripts/*.pl
RUN chown -R hlstats:hlstats /home/hlstats/scripts

USER hlstats
CMD until nc -z db 3306; do echo "Waiting for DB..."; sleep 2; done; perl hlstats.pl