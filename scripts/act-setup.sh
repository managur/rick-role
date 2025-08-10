#!/bin/bash

# Act setup script for Rick-Role CI workflow testing
# This script helps set up and configure act for local workflow testing

set -e

echo "🎵 Setting up act for Rick-Role CI workflow testing..."

# Check if act is installed
if ! command -v act >/dev/null 2>&1; then
    echo "📦 Installing act..."
    
    # Try to install without sudo first
    if curl -s https://raw.githubusercontent.com/nektos/act/master/install.sh | bash; then
        echo "✅ act installed successfully (local installation)!"
    else
        echo "⚠️  Local installation failed. Trying alternative methods..."
        
        # Try downloading directly to ./bin
        mkdir -p bin
        echo "📥 Downloading act binary to ./bin/..."
        curl -L https://github.com/nektos/act/releases/latest/download/act_Linux_x86_64.tar.gz | tar -xz -C bin/ act
        chmod +x bin/act
        echo "✅ act installed to ./bin/act"
    fi
    
    # Add ./bin to PATH for this session if act was installed locally
    if [ -f "./bin/act" ]; then
        export PATH="./bin:$PATH"
        echo "📝 Added ./bin to PATH for this session"
    fi
else
    echo "✅ act is already installed"
fi

# Check Docker
if ! command -v docker >/dev/null 2>&1; then
    echo "❌ Docker is required but not installed"
    echo "Please install Docker first: https://docs.docker.com/get-docker/"
    exit 1
fi

# Check if Docker daemon is running
if ! docker info >/dev/null 2>&1; then
    echo "❌ Docker daemon is not running"
    echo "Please start Docker and try again"
    exit 1
fi

echo "✅ Docker is available"

# Create environment file if it doesn't exist
if [ ! -f "env.act" ]; then
    echo "📝 Creating env.act file..."
    cat > env.act << EOF
# Environment variables for act (local GitHub Actions testing)
PHP_VERSION=8.4
XDEBUG_MODE=coverage
DB_HOST=localhost
DB_PORT=3306
DB_NAME=rick_role_test
DB_USER=rick_role
DB_PASS=test_password
COMPOSER_ALLOW_SUPERUSER=1
COMPOSER_NO_INTERACTION=1
PHPUNIT_COVERAGE=1
EOF
    echo "✅ env.act file created"
fi

# Test act configuration - use the correct path
echo "🧪 Testing act configuration..."
if [ -f "./bin/act" ]; then
    ./bin/act --dryrun --job test
else
    act --dryrun --job test
fi

echo ""
echo "🎵 Act setup complete! You can now run:"
echo "  make workflow-ci      # Run the test job"
echo "  make workflow-ci-full # Run all jobs"
echo "  make workflow-ci-dry  # Dry run to see what would happen"
echo ""
echo "For more information, visit: https://github.com/nektos/act" 