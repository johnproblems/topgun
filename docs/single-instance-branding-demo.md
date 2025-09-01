# Single-Instance Multi-Domain Branding: How It Works

## The Core Concept

You're absolutely right to wonder - users ARE accessing the same files! The magic happens through **dynamic content generation** based on the incoming request domain. Here's exactly how it works:

## Technical Flow

```
1. User visits master.example.com
2. DNS points to same Coolify server (192.168.1.100)
3. Nginx/Apache receives request with Host header: "master.example.com"
4. Laravel application processes request
5. Middleware detects domain and loads appropriate branding
6. Same PHP files generate different HTML/CSS based on branding config
7. User sees customized interface
```

## Step-by-Step Implementation

### 1. Domain Detection in Middleware

```php
// app/Http/Middleware/DomainBrandingMiddleware.php
class DomainBrandingMiddleware
{
    public function handle($request, Closure $next)
    {
        $domain = $request->getHost(); // Gets "master.example.com"
        
        // Find white-label config for this domain
        $brandingConfig = WhiteLabelConfig::findByDomain($domain);
        
        if ($brandingConfig) {
            // Store branding in request for later use
            $request->attributes->set('branding', $brandingConfig);
            
            // Set organization context based on domain
            $organization = $brandingConfig->organization;
            $request->attributes->set('organization', $organization);
        }
        
        return $next($request);
    }
}
```

### 2. Dynamic CSS Generation

The same CSS files generate different styles:

```php
// routes/web.php
Route::get('/css/dynamic-theme.css', function (Request $request) {
    $branding = $request->attributes->get('branding');
    
    if (!$branding) {
        // Default Coolify theme
        return response(file_get_contents(public_path('css/default.css')))
            ->header('Content-Type', 'text/css');
    }
    
    // Generate custom CSS based on branding config
    $css = $branding->generateCssVariables();
    
    return response($css)->header('Content-Type', 'text/css');
});
```

**Generated CSS Example:**
```css
/* For master.example.com */
:root {
  --primary-color: #ff6b35;      /* Master brand orange */
  --secondary-color: #2c3e50;
  --platform-name: "MasterHost";
}

/* For client.example.com */
:root {
  --primary-color: #3498db;      /* Client brand blue */
  --secondary-color: #34495e;
  --platform-name: "ClientCloud";
}
```

### 3. Dynamic HTML Content

The same Blade templates generate different content:

```blade
{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>
        @if(request()->attributes->get('branding'))
            {{ request()->attributes->get('branding')->getPlatformName() }}
        @else
            Coolify
        @endif
    </title>
    
    {{-- Dynamic CSS --}}
    <link href="/css/dynamic-theme.css" rel="stylesheet">
    
    @if(request()->attributes->get('branding')?->hasCustomLogo())
        <link rel="icon" href="{{ request()->attributes->get('branding')->getLogoUrl() }}">
    @endif
</head>
<body>
    <nav class="navbar">
        @if(request()->attributes->get('branding'))
            @php $branding = request()->attributes->get('branding') @endphp
            
            {{-- Custom logo --}}
            @if($branding->hasCustomLogo())
                <img src="{{ $branding->getLogoUrl() }}" alt="{{ $branding->getPlatformName() }}">
            @endif
            
            {{-- Custom platform name --}}
            <span class="platform-name">{{ $branding->getPlatformName() }}</span>
            
            {{-- Hide Coolify branding if configured --}}
            @unless($branding->shouldHideCoolifyBranding())
                <small>Powered by Coolify</small>
            @endunless
        @else
            {{-- Default Coolify branding --}}
            <img src="/images/coolify-logo.png" alt="Coolify">
            <span class="platform-name">Coolify</span>
        @endif
    </nav>
    
    @yield('content')
</body>
</html>
```

### 4. Database-Driven Configuration

Each domain maps to different database records:

```sql
-- white_label_configs table
INSERT INTO white_label_configs (organization_id, platform_name, logo_url, theme_config, custom_domains) VALUES
('org-1', 'MasterHost', 'https://cdn.example.com/master-logo.png', 
 '{"primary_color": "#ff6b35", "secondary_color": "#2c3e50"}', 
 '["master.example.com", "*.master.example.com"]'),

('org-2', 'ClientCloud', 'https://cdn.example.com/client-logo.png',
 '{"primary_color": "#3498db", "secondary_color": "#34495e"}',
 '["client.example.com", "app.client.example.com"]');
```

## Real-World Example Implementation

Let me create a working demonstration:

```php
// app/Http/Middleware/DynamicBrandingMiddleware.php
<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\WhiteLabelConfig;
use Illuminate\Http\Request;

class DynamicBrandingMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $domain = $request->getHost();
        
        // Find branding config for this domain
        $branding = WhiteLabelConfig::findByDomain($domain);
        
        if ($branding) {
            // Set branding context for the entire request
            app()->instance('current.branding', $branding);
            
            // Set organization context
            app()->instance('current.organization', $branding->organization);
            
            // Add to view data globally
            view()->share('branding', $branding);
            view()->share('platformName', $branding->getPlatformName());
        }
        
        return $next($request);
    }
}
```

```php
// app/Http/Controllers/DynamicAssetController.php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DynamicAssetController extends Controller
{
    public function dynamicCss(Request $request): Response
    {
        $branding = app('current.branding');
        
        if (!$branding) {
            // Return default CSS
            $css = $this->getDefaultCss();
        } else {
            // Generate custom CSS
            $css = $this->generateCustomCss($branding);
        }
        
        return response($css, 200, [
            'Content-Type' => 'text/css',
            'Cache-Control' => 'public, max-age=3600', // Cache for 1 hour
        ]);
    }
    
    private function generateCustomCss($branding): string
    {
        $baseCSS = file_get_contents(resource_path('css/base.css'));
        $customCSS = $branding->generateCssVariables();
        
        return $baseCSS . "\n\n" . $customCSS;
    }
    
    private function getDefaultCss(): string
    {
        return file_get_contents(public_path('css/default.css'));
    }
}
```

## How Users See Different Content

### User A visits master.example.com:
1. **DNS Resolution**: master.example.com → 192.168.1.100
2. **HTTP Request**: `GET / HTTP/1.1\nHost: master.example.com`
3. **Middleware**: Detects domain, loads MasterHost branding config
4. **Template Rendering**: Same Blade files, different variables
5. **CSS Generation**: Custom orange theme with MasterHost logo
6. **Response**: HTML with MasterHost branding

### User B visits client.example.com:
1. **DNS Resolution**: client.example.com → 192.168.1.100 (SAME SERVER!)
2. **HTTP Request**: `GET / HTTP/1.1\nHost: client.example.com`
3. **Middleware**: Detects domain, loads ClientCloud branding config
4. **Template Rendering**: Same Blade files, different variables
5. **CSS Generation**: Custom blue theme with ClientCloud logo
6. **Response**: HTML with ClientCloud branding

## Local Testing Demo

Here's how you can test this locally:

```bash
# Add to /etc/hosts
echo "127.0.0.1 master.local" >> /etc/hosts
echo "127.0.0.1 client.local" >> /etc/hosts

# Start Coolify
./dev.sh start

# Create test branding configs in database
php artisan tinker
```

```php
// In tinker
use App\Models\Organization;
use App\Models\WhiteLabelConfig;

// Create organizations
$masterOrg = Organization::factory()->create(['name' => 'Master Organization']);
$clientOrg = Organization::factory()->create(['name' => 'Client Organization']);

// Create branding configs
WhiteLabelConfig::create([
    'organization_id' => $masterOrg->id,
    'platform_name' => 'MasterHost',
    'logo_url' => 'https://via.placeholder.com/150x50/ff6b35/ffffff?text=MasterHost',
    'theme_config' => [
        'primary_color' => '#ff6b35',
        'secondary_color' => '#2c3e50',
        'background_color' => '#fff5f2'
    ],
    'custom_domains' => ['master.local'],
    'hide_coolify_branding' => true
]);

WhiteLabelConfig::create([
    'organization_id' => $clientOrg->id,
    'platform_name' => 'ClientCloud',
    'logo_url' => 'https://via.placeholder.com/150x50/3498db/ffffff?text=ClientCloud',
    'theme_config' => [
        'primary_color' => '#3498db',
        'secondary_color' => '#34495e',
        'background_color' => '#f8fbff'
    ],
    'custom_domains' => ['client.local'],
    'hide_coolify_branding' => false
]);
```

```bash
# Test different domains
curl -H "Host: master.local" http://localhost:8000/
curl -H "Host: client.local" http://localhost:8000/
curl -H "Host: default.local" http://localhost:8000/

# Or in browser:
# http://master.local:8000 - Shows MasterHost branding
# http://client.local:8000 - Shows ClientCloud branding
```

## Key Technical Points

1. **Same Files**: All users access the same PHP/HTML/CSS files
2. **Dynamic Generation**: Content is generated differently based on request domain
3. **Database-Driven**: Branding configurations stored in database
4. **Middleware Magic**: Domain detection happens in middleware layer
5. **Template Variables**: Same templates use different variables
6. **CSS Variables**: CSS custom properties change based on domain
7. **Caching**: Generated assets can be cached per domain

## Performance Considerations

```php
// Cache generated CSS per domain
public function dynamicCss(Request $request): Response
{
    $domain = $request->getHost();
    $cacheKey = "dynamic_css:{$domain}";
    
    $css = Cache::remember($cacheKey, 3600, function() use ($domain) {
        $branding = WhiteLabelConfig::findByDomain($domain);
        return $branding ? $this->generateCustomCss($branding) : $this->getDefaultCss();
    });
    
    return response($css)->header('Content-Type', 'text/css');
}
```

This is how single-instance multi-domain branding works - same server, same files, but dynamic content generation based on the incoming request domain!