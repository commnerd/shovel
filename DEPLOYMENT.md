# Deployment Guide

This repository includes a GitHub Actions workflow that automatically builds and deploys the Foca application to your Linux server.

## Prerequisites

1. **Docker Hub Account**: You need a Docker Hub account to push images
2. **Linux Server**: A Linux server with Docker installed
3. **SSH Access**: SSH key-based authentication to your server

## Setup Instructions

### 1. GitHub Secrets Configuration

Go to your GitHub repository → Settings → Secrets and variables → Actions, and add the following secrets:

#### Docker Hub Secrets
- `DOCKER_USERNAME`: Your Docker Hub username
- `DOCKER_PASSWORD`: Your Docker Hub password or access token

#### Server Connection Secrets
- `HOST`: Your server's IP address or domain name
- `USERNAME`: SSH username for your server
- `SSH_KEY`: Your private SSH key (the entire content of your private key file)
- `PORT`: SSH port (usually 22)

### 2. Server Preparation

On your Linux server, ensure Docker is installed and running:

```bash
# Install Docker (Ubuntu/Debian)
sudo apt update
sudo apt install docker.io
sudo systemctl start docker
sudo systemctl enable docker

# Add your user to docker group (optional, to run without sudo)
sudo usermod -aG docker $USER
```

### 3. SSH Key Setup

If you don't have SSH key authentication set up:

```bash
# On your local machine, generate SSH key pair
ssh-keygen -t rsa -b 4096 -C "your-email@example.com"

# Copy public key to server
ssh-copy-id username@your-server-ip

# Test connection
ssh username@your-server-ip
```

## How It Works

### Automatic Deployment
The workflow triggers on:
- **Push to main branch**: Every push to main automatically deploys
- **Manual trigger**: You can manually trigger deployment from GitHub Actions tab

### Deployment Process
1. **Build**: Creates Docker image with multi-architecture support (AMD64/ARM64)
2. **Push**: Uploads image to `commnerd/foca` on Docker Hub
3. **Deploy**: SSH into your server and:
   - Pulls the latest image
   - Stops the old container
   - Starts the new container
   - Cleans up old images

### Container Configuration
The deployed container runs with:
- **Name**: `foca-app`
- **Port**: Maps host port 80 to container port 80
- **Restart Policy**: `unless-stopped` (auto-restarts on server reboot)
- **Image**: `commnerd/foca:latest`

## Manual Deployment

If you need to deploy manually:

```bash
# On your server
docker pull commnerd/foca:latest
docker stop foca-app 2>/dev/null || true
docker rm foca-app 2>/dev/null || true
docker run -d --name foca-app --restart unless-stopped -p 80:80 commnerd/foca:latest
```

## Monitoring

Check deployment status:
- **GitHub Actions**: Go to Actions tab in your repository
- **Server**: `docker ps` to see running containers
- **Logs**: `docker logs foca-app` to view application logs

## Troubleshooting

### Common Issues

1. **SSH Connection Failed**
   - Verify SSH key is correctly added to GitHub secrets
   - Check server IP and port are correct
   - Ensure SSH service is running on server

2. **Docker Login Failed**
   - Verify Docker Hub credentials in GitHub secrets
   - Check if Docker Hub account has push permissions

3. **Container Won't Start**
   - Check server has port 80 available
   - Verify Docker is running on server
   - Check container logs: `docker logs foca-app`

4. **Image Pull Failed**
   - Verify image was pushed successfully to Docker Hub
   - Check Docker Hub repository permissions

### Useful Commands

```bash
# View running containers
docker ps

# View container logs
docker logs foca-app

# View container details
docker inspect foca-app

# Restart container
docker restart foca-app

# Stop and remove container
docker stop foca-app && docker rm foca-app
```

