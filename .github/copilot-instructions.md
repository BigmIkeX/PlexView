# Copilot Instructions for Plex-Library-Viewer-PHP

## Project Overview
A single-page PHP application that displays Plex Media Server library contents (movies/TV shows) in a Bootstrap-based web interface with DataTables for sorting/filtering.

## Architecture

### Single-File PHP Application
- **[index.php](../index.php)** - Contains ALL application logic: configuration, Plex API integration, HTML/CSS/JS output
- No MVC pattern, routing, or frameworks - direct procedural PHP
- Configuration variables at top of file (lines 2-8), runtime logic follows

### Data Flow
1. PHP fetches XML from Plex API using `simplexml_load_file($url)`
2. URL parameters `act` (action) and `type` (movie/tv) control which data view
3. XML data iterated directly into HTML table rows and Bootstrap modals

### Key URL Parameters
```
?act={newest|all|recentlyAdded|recentlyViewed}&type={movie|tv}
```

## Configuration (Lines 2-8 of index.php)
```php
$name = "Cause FX";           // Display name
$useSSL = true;               // HTTP vs HTTPS
$host = "hostname:port";      // Plex server (no protocol prefix)
$token = "xxxxxxxxxxxxx";     // Plex authentication token
$movies = "1";                // Library section ID for movies
$tv = "2";                    // Library section ID for TV shows
```

## ⚠️ Security Considerations
- **Plex token exposure**: The `$token` is embedded in image URLs sent to browsers. Anyone viewing page source can see it.
- **No authentication**: This app has no login system - your library is publicly visible to anyone who can reach the URL.
- **Recommendations**:
  - Deploy behind a reverse proxy with HTTP Basic Auth (nginx/Apache)
  - Use firewall rules to restrict access to trusted IPs
  - Consider this app for LAN-only use, not public internet
  - Never commit real tokens to version control

## Frontend Stack
- **Bootstrap 3** - Grid layout, modals, navbar (via `assets/css/bootstrap.css`)
- **jQuery DataTables** - Table sorting/filtering (`assets/js/datatables/`)
- **Dark theme** - Custom CSS in [assets/css/main.css](../assets/css/main.css) (background: `#1f1f1f`)

## Coding Patterns

### Mixed PHP/HTML Output
```php
<?php foreach($achxml->$parent AS $child) { ?>
    <tr class="gradeA">
        <td><?=$child['title'];?></td>
    </tr>
<?php } ?>
```

### Plex Image Proxy Pattern
Images proxied through Plex's transcode endpoint for thumbnails:
```php
$imgurl = "$http://$host/photo/:/transcode?url=";
$imgurlend = "&width=100&height=100&X-Plex-Token=$token";
```

### Conditional Rendering (Movies vs TV)
TV shows have additional columns and different XML structure:
```php
$parent = ($act == "all" && $type == "tv") ? "Directory" : "Video";
if($type == "tv") { echo '<td>'.$child['grandparentTitle'].'</td>'; }
```

## Development Notes
- **No build process** - Edit files directly, refresh browser
- **No database** - All data from Plex API
- **No authentication** - Application exposes library publicly (see Security section)
- Requires PHP with `simplexml` extension enabled

## Deployment Options
```bash
# Local development - PHP built-in server
php -S localhost:8000

# Apache - place in DocumentRoot or virtual host directory
# Ensure mod_php or php-fpm is configured

# nginx - configure fastcgi_pass to PHP-FPM
# Docker - use php:apache or php:fpm base image
```
**Typical deployments**: Shared hosting, home server (Apache/nginx), Docker container, NAS devices with PHP support

## CSS Customization
Color palette defined in [assets/css/main.css](../assets/css/main.css) (lines 15-22):
- Background: `#1f1f1f`
- Sections: `#3d3d3d`
- Footer: `#262626`
- Accent red: `#fa1d2d`
- Accent green: `#b2c831`
