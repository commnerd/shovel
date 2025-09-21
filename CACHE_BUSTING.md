# Cache Busting Implementation

This document explains the comprehensive cache busting system implemented to solve deployment caching issues, especially for pages with CSRF tokens.

## 🚀 Overview

The cache busting system addresses multiple layers of caching to ensure users always get the latest version of the application after deployments:

1. **Server-side cache control headers**
2. **Asset versioning via Vite**
3. **Deployment version tracking**
4. **CSRF token regeneration**
5. **Client-side update detection**

## 🛠️ Components

### 1. Deployment Version Service (`app/Services/DeploymentVersionService.php`)

Manages deployment versions and metadata:

- Generates semantic version numbers
- Tracks build numbers
- Stores deployment timestamps
- Manages git commit hashes
- Clears application caches during deployment

### 2. Deployment Headers Middleware (`app/Http/Middleware/AddDeploymentHeaders.php`)

Adds cache control and version headers to all responses:

- `X-Deployment-Version`: Current deployment version
- `X-Deployment-Timestamp`: When the deployment was made
- Cache control headers for HTML (no-cache)
- Cache control headers for API responses

### 3. Deployment Command (`app/Console/Commands/DeployCommand.php`)

Artisan command for managing deployments:

```bash
php artisan app:deploy --force
```

Features:
- Generates new deployment version
- Creates deployment marker file
- Clears application caches
- Optimizes for production
- Regenerates Wayfinder files

### 4. Client-Side Cache Buster (`resources/js/lib/cacheBuster.ts`)

JavaScript utility for client-side cache management:

- Detects deployment updates
- Shows update notifications to users
- Provides cache-busted URLs
- Handles automatic refresh prompts

### 5. Enhanced .htaccess Rules

Apache-level cache control:

- Static assets cached for 1 year with `immutable` flag
- HTML files never cached
- Deployment marker never cached
- Proper cache control headers

## 📋 Usage

### During Deployment

1. **Automatic (Docker)**: The Dockerfile runs `php artisan app:deploy --force` during build
2. **Manual**: Run `php artisan app:deploy` before deploying
3. **Enhanced Script**: Use `./deploy-with-cache-busting.sh` for comprehensive testing

### For Developers

The system works automatically, but you can:

```javascript
// Check current version
console.log(cacheBuster.getCurrentVersion());

// Get cache-busted URL
const url = cacheBuster.getCacheBustedUrl('/api/data');

// Force refresh
cacheBuster.forceRefresh();

// Check for updates
cacheBuster.checkForUpdates();
```

## 🔧 Configuration

### Environment Variables

No additional configuration required - the system works out of the box.

### Customization

You can customize version generation in `DeploymentVersionService.php`:

```php
private function generateVersionNumber(): string
{
    // Custom version logic here
    return "1.0.0-{$timestamp}";
}
```

## 📊 How It Works

### 1. Deployment Process

```
1. Run deployment command
   ↓
2. Generate new version number
   ↓
3. Clear application caches
   ↓
4. Create deployment marker
   ↓
5. Build assets with new hashes
   ↓
6. Deploy with cache headers
```

### 2. Client-Side Detection

```
1. Load page with version in meta tags
   ↓
2. Periodically check deployment marker
   ↓
3. Compare versions
   ↓
4. Show update notification if different
   ↓
5. User chooses to reload
```

### 3. Cache Control Strategy

- **Static Assets (CSS/JS/Images)**: Cached for 1 year with immutable flag
- **HTML Pages**: Never cached (always fresh CSRF tokens)
- **API Responses**: Short cache with revalidation
- **Deployment Marker**: Never cached (always check for updates)

## 🎯 Benefits

### For Users
- ✅ Always get latest version after deployment
- ✅ Fresh CSRF tokens on every page load
- ✅ Automatic update notifications
- ✅ No manual cache clearing needed

### For Developers
- ✅ Automatic cache busting on deployment
- ✅ Version tracking and debugging
- ✅ Easy deployment process
- ✅ Comprehensive testing tools

### For Performance
- ✅ Static assets cached aggressively
- ✅ HTML pages always fresh
- ✅ Optimal caching strategy
- ✅ Reduced server load

## 🧪 Testing

### Test Deployment Locally

```bash
./deploy-with-cache-busting.sh
```

This script tests:
- Docker image build
- Application response
- Deployment version headers
- Cache control headers
- CSRF token availability
- Deployment marker accessibility

### Verify Headers

```bash
curl -I http://localhost:8080
```

Look for:
- `X-Deployment-Version: 1.0.0-abc123`
- `Cache-Control: no-cache, no-store, must-revalidate`
- `X-Deployment-Timestamp: 2025-09-21T17:34:12Z`

## 🚨 Troubleshooting

### Common Issues

1. **CSRF Token Mismatch**: Ensure HTML pages are not cached
2. **Old Assets**: Check that Vite is generating new hashes
3. **No Update Detection**: Verify deployment marker is accessible
4. **Cache Headers Not Working**: Check Apache mod_headers is enabled

### Debug Commands

```bash
# Check deployment info
cat public/deployment-marker.txt

# Check version headers
curl -I http://your-domain.com

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## 🔄 Migration from Old System

If you had existing cache issues:

1. Deploy with new system
2. Clear browser caches once
3. Users will automatically get updates going forward

## 📈 Monitoring

The system provides several ways to monitor deployments:

- Version headers in HTTP responses
- Deployment marker file
- Console logs showing current version
- Update notifications for users

## 🎉 Result

With this implementation, you now have:

- **Zero cache-related deployment issues**
- **Fresh CSRF tokens on every deployment**
- **Automatic user notifications of updates**
- **Optimal performance with proper caching**
- **Easy deployment process**
- **Comprehensive testing and monitoring**

The terrible user experience from caching issues is now completely resolved! 🚀
