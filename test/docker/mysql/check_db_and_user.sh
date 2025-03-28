#!/bin/bash

# it may take a while for mysql to setup completely
# when container is being created for the first time
# thus we need this check

# Check if MySQL is up
mysqladmin ping -u root -p"$MYSQL_ROOT_PASSWORD" -h localhost --silent
if [ $? -ne 0 ]; then
  echo "MySQL is not available"
  exit 1
fi

# Check if database exists
DB_EXISTS=$(mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SHOW DATABASES LIKE '$MYSQL_DATABASE'" | grep -c "$MYSQL_DATABASE")
if [ "$DB_EXISTS" -eq 0 ]; then
  echo "Database $MYSQL_DATABASE does not exist"
  exit 1
fi

# Check if user exists
USER_EXISTS=$(mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT EXISTS(SELECT 1 FROM mysql.user WHERE user = '$MYSQL_USER')" | grep -c "1")
if [ "$USER_EXISTS" -eq 0 ]; then
  echo "User $MYSQL_USER does not exist"
  exit 1
fi

echo "Database and user exist"
exit 0
