# Foca

A Laravel application with waitlist functionality, containerized for easy deployment.

[![Deploy to Production](https://github.com/commnerd/foca/actions/workflows/deploy.yml/badge.svg)](https://github.com/commnerd/foca/actions/workflows/deploy.yml)

## Features

- 🐧 **Laravel 11** with Inertia.js and Vue.js
- 📧 **Waitlist functionality** with email validation
- 🐳 **Dockerized** for easy deployment
- 🚀 **Automated deployment** via GitHub Actions
- 🎨 **Modern UI** with Tailwind CSS
- 📱 **Responsive design**

## Quick Start

### Local Development

```bash
# Clone the repository
git clone https://github.com/commnerd/foca.git
cd foca

# Start with Laravel Sail
./vendor/bin/sail up -d

# Run migrations
./vendor/bin/sail artisan migrate

# Build frontend assets
./vendor/bin/sail npm run build
```

### Docker Deployment

```bash
# Build and test locally
./deploy-local.sh

# Push to Docker Hub
docker login
docker push commnerd/foca:latest
```

### Production Deployment

The application automatically deploys to production when you push to the `main` branch. See [DEPLOYMENT.md](DEPLOYMENT.md) for setup instructions.

## Development

### Running Tests

```bash
# Run all tests
./vendor/bin/sail test

# Run specific test suite
./vendor/bin/sail test tests/Feature/WaitlistTest.php
```

### Building Assets

```bash
# Development build
./vendor/bin/sail npm run dev

# Production build
./vendor/bin/sail npm run build
```

## Architecture

- **Backend**: Laravel 11 with SQLite database
- **Frontend**: Vue.js 3 with Inertia.js
- **Styling**: Tailwind CSS
- **Container**: Docker with PHP 8.3 + Apache
- **Deployment**: GitHub Actions + Docker Hub

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).

