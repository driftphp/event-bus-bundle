#!/bin/bash

while ! nc -z localhost 5672; do sleep 1; done
php vendor/bin/phpunit
