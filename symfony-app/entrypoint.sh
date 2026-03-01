#!/bin/bash

set -e

composer update

php bin/console doctrine:database:create --if-not-exists --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction

php -S 0.0.0.0:8000 -t public public/index.php