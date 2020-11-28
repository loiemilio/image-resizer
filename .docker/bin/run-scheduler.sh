#!/usr/bin/env bash

while [ true ]
do
  sleep 60
  php /var/www/artisan schedule:run --verbose --no-interaction &
done
