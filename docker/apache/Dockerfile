FROM node:22-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json* ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM httpd:2.4-alpine

WORKDIR /var/www/html

COPY public ./public
COPY --from=frontend /app/public/build ./public/build
COPY docker/apache/httpd.conf /usr/local/apache2/conf/httpd.conf
COPY docker/apache/app.conf /usr/local/apache2/conf/extra/app.conf
