#!/bin/bash

# Local deployment script for testing
# This simulates what the GitHub Actions workflow does

set -e

# Configuration
DOCKER_IMAGE="commnerd/foca"
DOCKER_TAG="${1:-latest}"  # Use first argument as tag, default to 'latest'
CONTAINER_NAME="foca-app"

echo "🚀 Starting local deployment process..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker and try again."
    exit 1
fi

# Build the Docker image
echo "📦 Building Docker image with tag: $DOCKER_TAG"
docker build -t $DOCKER_IMAGE:$DOCKER_TAG .

# Test the image locally
echo "🧪 Testing Docker image..."
docker run -d --name foca-test -p 8080:80 $DOCKER_IMAGE:$DOCKER_TAG

# Wait for container to start
sleep 5

# Test if the application is responding
if curl -f http://localhost:8080 > /dev/null 2>&1; then
    echo "✅ Application is responding on http://localhost:8080"
else
    echo "❌ Application is not responding"
    docker logs foca-test
    docker stop foca-test
    docker rm foca-test
    exit 1
fi

# Test if the PNG image is accessible
if curl -f http://localhost:8080/storage/foca-icon.png > /dev/null 2>&1; then
    echo "✅ PNG image is accessible"
else
    echo "❌ PNG image is not accessible"
fi

# Clean up test container
echo "🧹 Cleaning up test container..."
docker stop foca-test
docker rm foca-test

echo "✅ Local deployment test completed successfully!"
echo ""
echo "To push to Docker Hub and deploy to your server:"
echo "1. docker login"
echo "2. docker push $DOCKER_IMAGE:$DOCKER_TAG"
echo "3. Push your code to GitHub to trigger automatic deployment"
echo ""
echo "Usage examples:"
echo "  ./deploy-local.sh              # Uses 'latest' tag"
echo "  ./deploy-local.sh main         # Uses 'main' tag"
echo "  ./deploy-local.sh deployment   # Uses 'deployment' tag"

