#!/bin/bash

# Production Deployment Script for Laguna Integrations
# This script safely deploys updates to the existing EC2 server

set -e

# Configuration - UPDATE THESE VALUES
SERVER_IP="13.58.71.133"
KEY_PATH="/var/www/lag-int/integrations-lagunatools.pem"
SERVER_USER="ubuntu"  # Change to appropriate user for your setup
APP_PATH="/var/www/html"
WEB_USER="www-data"  # Change to appropriate user for your setup


# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to execute commands on remote server
remote_exec() {
    ssh -i "$KEY_PATH" "$SERVER_USER@$SERVER_IP" "$1"
}

# Function to copy files to remote server
remote_copy() {
    scp -i "$KEY_PATH" -r "$1" "$SERVER_USER@$SERVER_IP:$2"
}

main() {
    print_status "Starting deployment to production server..."
    
    # Test connection
    print_status "Testing connection to server..."
    if ! remote_exec "echo 'Connection successful'"; then
        print_error "Cannot connect to server. Check your SSH configuration."
        exit 1
    fi
    
    # Create backup
    print_status "Creating backup of current version..."
    remote_exec "cd $APP_PATH && sudo cp -r . ../backup-\$(date +%Y%m%d-%H%M%S)"
    
    # Pull latest changes
    print_status "Pulling latest changes from Git..."
    remote_exec "cd $APP_PATH && sudo git pull origin main"
    
    # Pull LFS files
    print_status "Pulling Git LFS files..."
    remote_exec "cd $APP_PATH && sudo git lfs pull"
    
    # Update dependencies if needed
    print_status "Updating Composer dependencies..."
    remote_exec "cd $APP_PATH && sudo composer install --no-dev --optimize-autoloader"
    
    # Run database migrations
    print_status "Running database migrations..."
    remote_exec "cd $APP_PATH && mysql -u \$DB_USER -p\$DB_PASS \$DB_NAME < database/notification_settings_schema.sql"
    
    # Set proper permissions
    print_status "Setting proper file permissions..."
    remote_exec "cd $APP_PATH && sudo chown -R $WEB_USER:$WEB_USER ."
    remote_exec "cd $APP_PATH && sudo find . -type d -exec chmod 755 {} \;"
    remote_exec "cd $APP_PATH && sudo find . -type f -exec chmod 644 {} \;"
    remote_exec "cd $APP_PATH && sudo chmod -R 777 logs/ uploads/ public/pulse/"
    
    # Restart web server
    print_status "Restarting web server..."
    remote_exec "sudo systemctl restart httpd || sudo systemctl restart apache2"
    remote_exec "sudo systemctl restart php-fpm || true"
    
    # Verify deployment
    print_status "Verifying deployment..."
    if remote_exec "curl -s -o /dev/null -w '%{http_code}' http://localhost/status.php | grep -q '200'"; then
        print_status "✅ Deployment successful! Application is responding."
    else
        print_warning "⚠️  Application may not be responding correctly. Please check manually."
    fi
    
    print_status "Deployment completed!"
    print_warning "Please verify:"
    print_warning "1. Visit your website to ensure it's working"
    print_warning "2. Check the new Pulse directory: https://your-domain.com/public/pulse/"
    print_warning "3. Test any integrations that may be affected"
}

# Check if configuration is set
if [ "$SERVER_IP" = "YOUR_SERVER_IP" ]; then
    print_error "Please update the configuration variables at the top of this script"
    exit 1
fi

# Run deployment
main "$@"