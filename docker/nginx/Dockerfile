FROM nginx:1.27-alpine

WORKDIR /var/www/html

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

RUN mkdir -p /var/www/html/public /var/www/html/storage/app/public
