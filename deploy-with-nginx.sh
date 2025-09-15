#!/bin/bash

# Deployment script for Foca with Nginx
# This script updates the foca-app container and restarts nginx

set -e

# Configuration
DOCKER_IMAGE="commnerd/foca"
NGINX_CONTAINER="foca-nginx"
APP_CONTAINER="foca-app"
NETWORK_NAME="foca-network"

echo "🚀 Starting Foca deployment with Nginx..."

# Check if nginx container exists, if not create it
if ! docker ps -a --format "table {{.Names}}" | grep -q "^${NGINX_CONTAINER}$"; then
    echo "📦 Creating nginx container..."
    docker run -d \
        --name ${NGINX_CONTAINER} \
        --network ${NETWORK_NAME} \
        -p 80:80 \
        -v $(pwd)/nginx.conf:/etc/nginx/conf.d/default.conf:ro \
        -v $(pwd)/static/michaeljmiller.net:/var/www/michaeljmiller.net:ro \
        nginx:alpine
fi

# Check if network exists, if not create it
if ! docker network ls --format "table {{.Name}}" | grep -q "^${NETWORK_NAME}$"; then
    echo "🌐 Creating Docker network..."
    docker network create ${NETWORK_NAME}
fi

# Connect nginx to the network if not already connected
if ! docker inspect ${NGINX_CONTAINER} | grep -q ${NETWORK_NAME}; then
    echo "🔗 Connecting nginx to network..."
    docker network connect ${NETWORK_NAME} ${NGINX_CONTAINER}
fi

# Get the latest tag (assuming it's passed as an argument or use latest)
TAG=${1:-latest}
echo "🏷️  Deploying with tag: ${TAG}"

# Pull the new image
echo "📥 Pulling new image..."
docker pull ${DOCKER_IMAGE}:${TAG}

# Stop and remove existing app container
echo "🛑 Stopping existing app container..."
docker stop ${APP_CONTAINER} 2>/dev/null || true
docker rm ${APP_CONTAINER} 2>/dev/null || true

# Run the new app container
echo "🚀 Starting new app container..."
docker run -d \
    --name ${APP_CONTAINER} \
    --network ${NETWORK_NAME} \
    --restart unless-stopped \
    -e APP_URL=https://getfoca.com \
    ${DOCKER_IMAGE}:${TAG}

# Restart nginx to pick up any changes
echo "🔄 Restarting nginx..."
docker restart ${NGINX_CONTAINER}

# Clean up old images
echo "🧹 Cleaning up old images..."
docker image prune -f

# Show running containers
echo "📋 Current running containers:"
docker ps

echo "✅ Deployment complete!"
echo "🌐 Your sites are available at:"
echo "   - http://michaeljmiller.net (static site)"
echo "   - http://getfoca.com (Docker app)"
