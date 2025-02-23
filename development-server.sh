#!/bin/bash

clear

# Limpa os caches
rm -Rf _twigcache/*
rm -f _cache/b180345327557f4560fffe9b01b7aae4.xml
# rm -f debug.log

# Define ambiente de desenvolvimento
export APP_ENV="development"

# Inicia o servidor PHP
php -S 127.0.0.1:3000 -t . router.php
