FROM rcrtv/php:7.4-fpm

RUN apt-get update \
  && apt-get install -y supervisor \
  && rm -rf /var/lib/apt/lists/*
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
CMD ["/usr/bin/supervisord"]