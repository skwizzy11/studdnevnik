FROM mysql:8.0
# Имя init.sql и путь ожидают temp-pod.yaml и db-loader.yaml из методички
COPY ./dump/init.sql /docker-entrypoint-initdb.d/init.sql
