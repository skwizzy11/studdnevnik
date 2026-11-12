FROM nginx:1.27-alpine
# Адрес бэкенда (Service crud-api в Kubernetes)
ENV API_HOST=crud-api.default.svc.cluster.local
COPY ./nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY ./index.html /usr/share/nginx/html/index.html
COPY ./app/vendor/ /usr/share/nginx/html/vendor/
EXPOSE 80
