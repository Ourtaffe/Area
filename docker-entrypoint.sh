#!/bin/bash
set -e

# Wait for database to be ready
echo "Waiting for database..."
until php -r "
try {
    \$pdo = new PDO('mysql:host=${DB_HOST:-db};port=${DB_PORT:-3306}', '${DB_USERNAME:-area}', '${DB_PASSWORD:-area}');
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$dbname = "${DB_DATABASE:-action_reaction}";
    \$pdo->exec("CREATE DATABASE IF NOT EXISTS \`\$dbname\`");
    \$pdo->exec("USE \`\$dbname\`");
    \$pdo->query('SELECT 1');
    echo 'Database is ready';
    exit(0);
} catch (PDOException \$e) {
    echo 'Connection failed: ' . \$e->getMessage() . '\n';
    exit(1);
}
"; do
  echo "Database is unavailable - sleeping"
  sleep 2
done

echo "Database is ready!"

# Install dependencies if vendor directory doesn't exist
if [ ! -d "/var/www/html/vendor" ]; then
    echo "Vendor directory not found, installing dependencies..."
    cd /var/www/html
    composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts || {
        echo "Warning: composer install failed, trying with dev dependencies..."
        composer install --no-interaction --no-progress --prefer-dist --no-scripts
    }
fi

# Create .env file using environment variables provided by Docker
echo "Creating .env file from Docker environment variables..."
cat > /var/www/html/.env <<ENVEOF
APP_NAME=Laravel
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY:-base64:6zIKjIaAWEB/ruCLBCzjrDtRy1IA/T/dtkLLMHZOwS4=}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}

DB_CONNECTION=mysql
DB_HOST=${DB_HOST:-db}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-action_reaction}
DB_USERNAME=${DB_USERNAME:-area}
DB_PASSWORD=${DB_PASSWORD:-area}

GOOGLE_CLIENT_ID=${GOOGLE_CLIENT_ID}
GOOGLE_CLIENT_SECRET=${GOOGLE_CLIENT_SECRET}
GOOGLE_REDIRECT_URL=${GOOGLE_REDIRECT_URL:-http://localhost:8082/api/auth/google/callback}
FRONTEND_URL=${FRONTEND_URL:-http://localhost:8083}
ENVEOF

# Only run Laravel commands if vendor exists
if [ -d "/var/www/html/vendor" ]; then
    echo "Clearing Laravel caches..."
    php artisan config:clear || true
    php artisan cache:clear || true
    
    echo "Running migrations..."
    php artisan migrate --force || echo "Migration failed or already applied"
    
    echo "Seeding database..."
    php artisan db:seed --force || echo "Seeding failed or already applied"
else
    echo "Error: Vendor directory still not available"
    exit 1
fi

# Start the server
echo "Starting Laravel server..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
