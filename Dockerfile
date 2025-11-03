# Étape 1 : build avec Composer
FROM composer:2.7 AS build

WORKDIR /app

# Copier les fichiers composer
COPY composer.json composer.lock ./

# Installer les dépendances sans les dev et avec autoloader optimisé
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

# Copier le reste du code
COPY . .

# Étape 2 : runtime
FROM php:8.3-fpm

# Installer les dépendances système et extensions PHP
RUN apt-get update && apt-get install -y \
  git unzip libpq-dev curl postgresql-client && \
    docker-php-ext-install pdo pdo_pgsql && \
    rm -rf /var/lib/apt/lists/*

# Créer le groupe et l'utilisateur non-root
RUN groupadd -g 1000 laravel && \
    useradd -u 1000 -g laravel -m -s /bin/bash laravel

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier le code et les dépendances depuis l’étape build
COPY --from=build /app ./

# Créer un script d’entrée simple et le rendre exécutable
RUN echo '#!/bin/bash\n\
# Generate APP_KEY if not provided via env\n\
if [ -z "$APP_KEY" ]; then\n\
  echo "APP_KEY not set — generating one"\n\
  php artisan key:generate --force || true\n\
fi\n\
\n\
# Wait for database to be ready\n\
echo "Waiting for database to be ready..."\n\
MAX_WAIT=120\n\
WAITED=0\n\
while ! pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" >/dev/null 2>&1; do\n\
  if [ "$WAITED" -ge "$MAX_WAIT" ]; then\n\
    echo "Timeout waiting for database after ${MAX_WAIT}s" >&2\n\
    exit 1\n\
  fi\n\
  echo "Database is unavailable - sleeping 1s (waited=${WAITED}s)"\n\
  sleep 1\n\
  WAITED=$((WAITED+1))\n\
done\n\
\n\
echo "Database is up - executing migrations"\n\
# Clear caches\n\
php artisan route:clear || true\n\
php artisan config:clear || true\n\
php artisan cache:clear || true\n\
\n\
# Run migrations\n\
php artisan migrate --force || true\n\
\n\
echo "Starting Laravel application..."\n\
exec "$@"' > /usr/local/bin/docker-entrypoint.sh && \
chmod +x /usr/local/bin/docker-entrypoint.sh

# Changer le propriétaire du code vers l’utilisateur non-root
RUN chown -R laravel:laravel /var/www/html

# Passer à l’utilisateur non-root
USER laravel

# Exposer le port
EXPOSE 8000

# Entrypoint et commande par défaut
ENTRYPOINT ["docker-entrypoint.sh"]
# Use the PORT env var provided by Render if present, otherwise fallback to 8000
CMD ["sh", "-lc", "php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
