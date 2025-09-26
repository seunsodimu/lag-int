# Production Deployment Script for Laguna Integrations (PowerShell)
# This script safely deploys updates to the existing EC2 server

param(
    [Parameter(Mandatory=$true)]
    [string]$ServerIP,
    
    [Parameter(Mandatory=$true)]
    [string]$KeyPath,
    
    [string]$ServerUser = "ec2-user",
    [string]$AppPath = "/var/www/html",
    [string]$WebUser = "apache"
)

# Function to execute SSH commands
function Invoke-SSHCommand {
    param([string]$Command)
    
    $sshArgs = @(
        "-i", $KeyPath,
        "$ServerUser@$ServerIP",
        $Command
    )
    
    & ssh @sshArgs
    if ($LASTEXITCODE -ne 0) {
        throw "SSH command failed: $Command"
    }
}

# Function to print colored output
function Write-Status {
    param([string]$Message)
    Write-Host "[INFO] $Message" -ForegroundColor Green
}

function Write-Warning {
    param([string]$Message)
    Write-Host "[WARNING] $Message" -ForegroundColor Yellow
}

function Write-Error {
    param([string]$Message)
    Write-Host "[ERROR] $Message" -ForegroundColor Red
}

try {
    Write-Status "Starting deployment to production server..."
    
    # Test connection
    Write-Status "Testing connection to server..."
    Invoke-SSHCommand "echo 'Connection successful'"
    
    # Create backup
    Write-Status "Creating backup of current version..."
    Invoke-SSHCommand "cd $AppPath && sudo cp -r . ../backup-`$(date +%Y%m%d-%H%M%S)"
    
    # Pull latest changes
    Write-Status "Pulling latest changes from Git..."
    Invoke-SSHCommand "cd $AppPath && sudo git pull origin main"
    
    # Pull LFS files
    Write-Status "Pulling Git LFS files..."
    Invoke-SSHCommand "cd $AppPath && sudo git lfs pull"
    
    # Update dependencies if needed
    Write-Status "Updating Composer dependencies..."
    Invoke-SSHCommand "cd $AppPath && sudo composer install --no-dev --optimize-autoloader"
    
    # Set proper permissions
    Write-Status "Setting proper file permissions..."
    Invoke-SSHCommand "cd $AppPath && sudo chown -R $WebUser`:$WebUser ."
    Invoke-SSHCommand "cd $AppPath && sudo find . -type d -exec chmod 755 {} \;"
    Invoke-SSHCommand "cd $AppPath && sudo find . -type f -exec chmod 644 {} \;"
    Invoke-SSHCommand "cd $AppPath && sudo chmod -R 777 logs/ uploads/ public/pulse/"
    
    # Restart web server
    Write-Status "Restarting web server..."
    Invoke-SSHCommand "sudo systemctl restart httpd || sudo systemctl restart apache2"
    Invoke-SSHCommand "sudo systemctl restart php-fpm || true"
    
    # Verify deployment
    Write-Status "Verifying deployment..."
    try {
        Invoke-SSHCommand "curl -s -o /dev/null -w '%{http_code}' http://localhost/status.php | grep -q '200'"
        Write-Status "✅ Deployment successful! Application is responding."
    }
    catch {
        Write-Warning "⚠️  Application may not be responding correctly. Please check manually."
    }
    
    Write-Status "Deployment completed!"
    Write-Warning "Please verify:"
    Write-Warning "1. Visit your website to ensure it's working"
    Write-Warning "2. Check the new Pulse directory: https://your-domain.com/public/pulse/"
    Write-Warning "3. Test any integrations that may be affected"
}
catch {
    Write-Error "Deployment failed: $($_.Exception.Message)"
    Write-Error "Please check the server manually and restore from backup if necessary."
    exit 1
}

# Usage example:
# .\deploy-to-production.ps1 -ServerIP "your-server-ip" -KeyPath "C:\path\to\your-key.pem"