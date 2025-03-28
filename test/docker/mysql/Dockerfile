FROM mysql:latest
COPY init.sql /docker-entrypoint-initdb.d/
COPY check_db_and_user.sh /docker-entrypoint-initdb.d/
RUN microdnf install -y vim
