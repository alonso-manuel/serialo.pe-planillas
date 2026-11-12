#!/bin/sh
# Primera vez que arranca MySQL: el usuario de la app crea y usa las bases de los estudios (planillas_*).
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "GRANT ALL PRIVILEGES ON \`planillas\_%\`.* TO '$MYSQL_USER'@'%'; FLUSH PRIVILEGES;"
