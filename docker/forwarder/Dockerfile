FROM debian:trixie-slim

RUN export DEBIAN_FRONTEND=noninteractive && \
    apt-get update && apt-get upgrade -y && apt-get install -y --no-install-recommends \
    wget \
    ca-certificates \
    && apt-get clean \
    && apt-get autoclean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /var/cache/apt/archives/*

WORKDIR /app
RUN wget https://github.com/startersclan/source-udp-forwarder/releases/download/v0.3.0/source-udp-forwarder_v0.3.0_linux_amd64 -O forwarder \
    && chmod +x forwarder
ENTRYPOINT ["./forwarder"]