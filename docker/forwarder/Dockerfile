FROM debian:trixie-slim
RUN apt-get update && apt-get install -y wget ca-certificates && rm -rf /var/lib/apt/lists/*
WORKDIR /app
RUN wget https://github.com/startersclan/source-udp-forwarder/releases/download/v0.3.0/source-udp-forwarder_v0.3.0_linux_amd64 -O forwarder \
    && chmod +x forwarder
ENTRYPOINT ["./forwarder"]