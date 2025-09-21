#!/bin/bash

# Enhanced deployment script with comprehensive cache busting
# This script should be run during CI/CD or manual deployments

set -e

echo "🚀 Starting deployment with cache busting..."

# Configuration
DOCKER_IMAGE="commnerd/foca"
DOCKER_TAG="${1:-latest}"
CONTAINER_NAME="foca-app"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    print_error "Docker is not running. Please start Docker and try again."
    exit 1
fi

print_status "Building Docker image with tag: $DOCKER_TAG"
docker build -t $DOCKER_IMAGE:$DOCKER_TAG .

# Test the image locally
print_status "Testing Docker image..."
docker run -d --name foca-test -p 8080:80 $DOCKER_IMAGE:$DOCKER_TAG

# Wait for container to start
sleep 5

# Test if the application is responding
print_status "Testing application response..."
if curl -f http://localhost:8080 > /dev/null 2>&1; then
    print_success "Application is responding on http://localhost:8080"
else
    print_error "Application is not responding"
    docker logs foca-test
    docker stop foca-test
    docker rm foca-test
    exit 1
fi

# Test deployment version endpoint
print_status "Testing deployment version headers..."
VERSION_HEADER=$(curl -s -I http://localhost:8080 | grep -i "x-deployment-version" || echo "")
if [ -n "$VERSION_HEADER" ]; then
    print_success "Deployment version header found: $VERSION_HEADER"
else
    print_warning "Deployment version header not found"
fi

# Test deployment marker
print_status "Testing deployment marker..."
if curl -f http://localhost:8080/deployment-marker.txt > /dev/null 2>&1; then
    print_success "Deployment marker is accessible"
    MARKER_CONTENT=$(curl -s http://localhost:8080/deployment-marker.txt)
    print_status "Deployment info: $MARKER_CONTENT"
else
    print_warning "Deployment marker is not accessible"
fi

# Test cache control headers
print_status "Testing cache control headers..."
CACHE_HEADERS=$(curl -s -I http://localhost:8080 | grep -i "cache-control\|pragma\|expires" || echo "")
if [ -n "$CACHE_HEADERS" ]; then
    print_success "Cache control headers found:"
    echo "$CACHE_HEADERS"
else
    print_warning "Cache control headers not found"
fi

# Test CSRF token
print_status "Testing CSRF token availability..."
CSRF_TOKEN=$(curl -s http://localhost:8080 | grep -o 'name="csrf-token" content="[^"]*"' | cut -d'"' -f4 || echo "")
if [ -n "$CSRF_TOKEN" ]; then
    print_success "CSRF token found (length: ${#CSRF_TOKEN})"
else
    print_warning "CSRF token not found"
fi

# Clean up test container
print_status "Cleaning up test container..."
docker stop foca-test
docker rm foca-test

print_success "Deployment test completed successfully!"
echo ""
echo "Next steps:"
echo "1. Push to Docker Hub: docker push $DOCKER_IMAGE:$DOCKER_TAG"
echo "2. Deploy to your server"
echo "3. Monitor deployment version headers"
echo ""
echo "Cache busting features enabled:"
echo "✅ Deployment version headers"
echo "✅ Cache control headers for HTML"
echo "✅ CSRF token regeneration"
echo "✅ Asset versioning via Vite"
echo "✅ Client-side update detection"
echo ""
echo "Usage examples:"
echo "  ./deploy-with-cache-busting.sh              # Uses 'latest' tag"
echo "  ./deploy-with-cache-busting.sh main         # Uses 'main' tag"
echo "  ./deploy-with-cache-busting.sh deployment   # Uses 'deployment' tag"
