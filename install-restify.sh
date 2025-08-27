#!/bin/bash

# Laravel Restify Installation & Setup Tool
# This script automates the installation and setup process for Laravel Restify

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

print_header() {
    echo -e "\n${BLUE}================================${NC}"
    echo -e "${BLUE} Laravel Restify Installer${NC}"
    echo -e "${BLUE}================================${NC}\n"
}

# Check if we're in a Laravel project
check_laravel_project() {
    if [[ ! -f "artisan" ]]; then
        print_error "This doesn't appear to be a Laravel project (no artisan file found)"
        exit 1
    fi
    print_success "Laravel project detected"
}

# Check PHP version
check_php_version() {
    php_version=$(php -r "echo PHP_VERSION;" | cut -d. -f1,2)
    required_version="8.0"
    
    if (( $(echo "$php_version >= $required_version" | bc -l) )); then
        print_success "PHP version $php_version meets requirements (>= $required_version)"
    else
        print_error "PHP version $php_version does not meet requirements (>= $required_version)"
        exit 1
    fi
}

# Check Laravel version
check_laravel_version() {
    if [[ ! -f "composer.json" ]]; then
        print_error "composer.json not found"
        exit 1
    fi
    
    laravel_version=$(php -r "
        \$composer = json_decode(file_get_contents('composer.json'), true);
        \$require = \$composer['require'] ?? [];
        foreach (['laravel/framework', 'illuminate/foundation'] as \$package) {
            if (isset(\$require[\$package])) {
                echo \$require[\$package];
                break;
            }
        }
    " | sed 's/[^0-9.]//g' | cut -d. -f1)
    
    if [[ "$laravel_version" -ge 8 ]]; then
        print_success "Laravel version meets requirements"
    else
        print_warning "Could not determine Laravel version or it may be too old"
        read -p "Do you want to continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
}

# Install composer package
install_composer_package() {
    print_info "Installing binaryk/laravel-restify via Composer..."
    
    if composer require binaryk/laravel-restify; then
        print_success "Laravel Restify package installed successfully"
    else
        print_error "Failed to install Laravel Restify package"
        exit 1
    fi
}

# Run restify setup
run_restify_setup() {
    print_info "Running restify:setup command..."
    
    if php artisan restify:setup; then
        print_success "Restify setup completed successfully"
    else
        print_error "Failed to run restify:setup"
        exit 1
    fi
}

# Run migrations
run_migrations() {
    read -p "Do you want to run migrations now? (Y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Nn]$ ]]; then
        print_warning "Skipping migrations. Remember to run 'php artisan migrate' later."
        return
    fi
    
    print_info "Running migrations..."
    if php artisan migrate; then
        print_success "Migrations completed successfully"
    else
        print_error "Failed to run migrations"
        exit 1
    fi
}

# Setup mock data generation (optional)
setup_mock_data() {
    read -p "Do you want to install doctrine/dbal for mock data generation? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        return
    fi
    
    print_info "Installing doctrine/dbal..."
    if composer require doctrine/dbal --dev; then
        print_success "doctrine/dbal installed successfully"
        
        read -p "Do you want to generate mock users data? (y/N): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            read -p "How many users do you want to generate? (default: 10): " user_count
            user_count=${user_count:-10}
            
            print_info "Generating $user_count mock users..."
            if php artisan restify:stub users --count="$user_count"; then
                print_success "Mock users generated successfully"
            else
                print_warning "Failed to generate mock users"
            fi
        fi
    else
        print_warning "Failed to install doctrine/dbal"
    fi
}

# Configure middleware
configure_middleware() {
    read -p "Do you want to enable Sanctum authentication middleware? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        return
    fi
    
    config_file="config/restify.php"
    if [[ -f "$config_file" ]]; then
        print_info "Enabling Sanctum authentication in restify.php..."
        # Uncomment the auth:sanctum middleware
        sed -i.bak "s|// 'auth:sanctum',|'auth:sanctum',|g" "$config_file"
        print_success "Sanctum middleware enabled"
        print_warning "Make sure you have Laravel Sanctum installed and configured"
    else
        print_warning "restify.php config file not found"
    fi
}

# Configure API prefix
configure_prefix() {
    read -p "Do you want to customize the API prefix? Current: /api/restify (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        return
    fi
    
    read -p "Enter new API prefix (e.g., /api/v1): " new_prefix
    if [[ -n "$new_prefix" ]]; then
        config_file="config/restify.php"
        if [[ -f "$config_file" ]]; then
            print_info "Updating API prefix to $new_prefix..."
            sed -i.bak "s|'base' => '/api/restify'|'base' => '$new_prefix'|g" "$config_file"
            print_success "API prefix updated to $new_prefix"
        else
            print_warning "restify.php config file not found"
        fi
    fi
}

# Generate additional repositories
generate_repositories() {
    read -p "Do you want to generate repositories for all existing models? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        return
    fi
    
    print_info "Generating repositories for all models..."
    if php artisan restify:generate:repositories --skip-preview; then
        print_success "Repositories generated successfully"
    else
        print_warning "Failed to generate repositories or no models found"
    fi
}

# Main installation process
main() {
    print_header
    
    print_info "Checking requirements..."
    check_laravel_project
    check_php_version
    check_laravel_version
    
    print_info "\nStarting Laravel Restify installation..."
    install_composer_package
    run_restify_setup
    run_migrations
    
    print_info "\nOptional configurations..."
    configure_middleware
    configure_prefix
    setup_mock_data
    generate_repositories
    
    print_header
    print_success "Laravel Restify installation completed!"
    echo
    print_info "Next steps:"
    echo "  1. Visit your API at: http://your-app/api/restify/users"
    echo "  2. Check the documentation at: https://restify.binarcode.com"
    echo "  3. Create repositories with: php artisan restify:repository ModelRepository"
    echo "  4. Generate policies with: php artisan restify:policy ModelPolicy"
    echo
    print_info "Configuration files created:"
    echo "  • config/restify.php - Main configuration"
    echo "  • app/Providers/RestifyServiceProvider.php - Service provider"
    echo "  • app/Restify/ - Repository directory"
    echo "  • app/Restify/UserRepository.php - Example repository"
    echo
}

# Run the main function
main "$@"