# PlexView

A modern, streaming-style web interface for your Plex Media Server with hero rotation, media details, and multi-view organization.

## Features

✨ **Modern Streaming UI**
- Clean, responsive design inspired by Plex
- Color scheme: Dark background with orange accents
- Bottom navigation with SVG icons

🎬 **Content Organization**
- Auto-detect libraries from your Plex server
- Organize by type: Movies, TV Shows, Music, Photos
- Dedicated views for each content type
- Home page with Top 20 recently added items

🎞️ **Hero Rotation**
- Rotating banner displays up to 20 recently added items
- Auto-rotates every 8 seconds with smooth fade transition
- Click "More Info" to view media details

📊 **Media Details Modal**
- Full metadata display (title, rating, year, duration)
- Cast information (up to 5 actors)
- Genre tags
- Trailer links (YouTube search)
- Description/plot summary

⚙️ **Optional Integrations**
- **Overseerr**: Show media request status and availability
- **Tautulli**: Display watch statistics, play counts, and user activity

🔗 **Configuration**
- Settings panel to connect to your Plex server
- Optional Overseerr and Tautulli integration setup
- Select which libraries to display
- Save preferences locally in JSON config

## Requirements

- **Web Server**: Apache/Nginx with PHP 8.0+
- **Plex Server**: Active Plex Media Server with libraries
- **PHP Extensions**: cURL (for Plex API calls)
- **Modern Browser**: Chrome, Firefox, Safari, Edge

## Installation

### 1. Download/Clone Repository
```bash
git clone https://github.com/BigmIkeX/PlexView.git
cd PlexView
```

### 2. Deploy to Web Server
Copy the files to your web server directory:
```bash
cp -r . /var/www/html/plexview/
```

Or use your preferred deployment method (FTP, rsync, etc.)

### 3. Set Permissions
Ensure the web server can write to the config directory:
```bash
mkdir -p assets/config
chmod 755 assets/config
chmod 644 assets/config/config.json (once created)
```

### 4. Access the Application
Open your browser and navigate to:
```
http://your-server/plexview/
```

## Configuration

### First Time Setup

1. Click the **⚙️ Settings** icon (bottom right)
2. Enter your Plex Server details:
   - **Host**: Your Plex server IP/domain and port (e.g., `192.168.1.100:32400`)
   - **Token**: Your Plex API token (see below how to get this)
   - **SSL**: Toggle if your Plex server uses HTTPS
3. Click **Test Connection** to verify
4. Select which libraries you want to display
5. Click **Save Settings**

### Getting Your Plex Token

1. Go to your Plex server: `http://YOUR_PLEX_IP:32400/web`
2. Click your profile icon (top right)
3. Select **Settings** → **Remote Access**
4. Scroll down - your token appears in the URL or in **Advanced** settings
5. Copy the token and paste it in PlexView settings

### Configuration File

Settings are stored in `assets/config/config.json`:
```json
{
  "name": "My Plex Server",
  "useSSL": false,
  "host": "192.168.1.100:32400",
  "token": "YOUR_PLEX_TOKEN",
  "selectedLibraries": [1, 2, 5, 7]
}
```

**Note**: This file is NOT tracked in version control for security.

## Optional Integrations

### Overseerr Integration

[Overseerr](https://overseerr.dev/) is a request management and media discovery tool for Plex. PlexView can display media request status and availability.

**Setup:**
1. Install and configure [Overseerr](https://docs.overseerr.dev/index.html)
2. In PlexView Settings, enable **Overseerr Integration**
3. Enter your Overseerr host (e.g., `192.168.1.100:5055`)
4. Get your API key from **Overseerr Settings → API**
5. Save and refresh

**What it shows:**
- ✅ Available - Content is in your Plex library
- ⏳ Requested - Someone has requested this content
- ❌ Not Available - Content is not available/requested

### Tautulli Integration

[Tautulli](https://tautulli.com/) is a monitoring and analytics tool for Plex. PlexView can display watch statistics and usage data.

**Setup:**
1. Install and configure [Tautulli](https://docs.tautulli.com/index.html)
2. In PlexView Settings, enable **Tautulli Integration**
3. Enter your Tautulli host (e.g., `192.168.1.100:8181`)
4. Get your API key from **Tautulli Settings → Web Interface → API**
5. Save and refresh

**What it shows:**
- **Total Plays**: Number of times content has been played
- **Watch Count**: Total watch instances (per user)
- **Duration**: Total time spent watching

## Usage

### Navigation

- **Home**: Top 20 recently added + all selected libraries
- **🎬 Movies**: Movie libraries only
- **📺 TV**: TV show libraries only
- **🎵 Music**: Music/artist libraries only
- **📷 Photos**: Photo libraries only
- **⚙️ Settings**: Configure Plex connection and optional integrations

### Viewing Media

- **Click any media card** to open the details modal
- **"More Info"** from hero banner also opens details
- **View trailers** by clicking the trailer button
- **See statistics** (if Tautulli enabled) showing play counts and watch history
- **Check request status** (if Overseerr enabled) to see if available/requested
- **Close** by clicking the backdrop or × button

### Supported Library Types

| Type | Detection | Endpoint |
|------|-----------|----------|
| Movies | `type='movie'` | recentlyAdded |
| TV Shows | `type='show'` | /all (paginated) |
| Music | `type='artist'` or `type='album'` | recentlyAdded |
| Photos | `type='photo'` | /Photo elements |

## File Structure

```
plexview/
├── index.php                    # Main application
├── README.md                    # This file
├── .gitignore                   # Git ignore rules
├── assets/
│   ├── config/
│   │   └── config.json          # User settings (not tracked)
│   └── [other assets]
└── .git/                        # Version control
```

## API Integration

PlexView communicates with multiple APIs:

### Plex Media Server API

Plex API endpoints used:

- `GET /library/sections` - List all libraries
- `GET /library/sections/{key}/recentlyAdded?limit=30` - Recently added items
- `GET /library/sections/{key}/all?X-Plex-Container-Size=30` - Full library (paginated)
- `GET /photo/:/transcode?url=...` - Image transcoding

### Overseerr API

*(Optional)* Fetches media request and availability status:

- `GET /api/v1/media?tmdbId={id}&mediaType={type}` - Get media status
- Returns: `status` (1=available, 2=pending, 3=processing), `requests` (array of requests)

### Tautulli API

*(Optional)* Fetches watch statistics and analytics:

- `GET /api/v2?apikey={key}&cmd=get_library_media_info&section_id={id}` - Get library statistics
- Returns: `count`, `plays`, `duration`, and other metadata

### Documentation Links

- [Plex API Reference](https://www.plexopedia.com/plex-api/)
- [Overseerr Docs](https://docs.overseerr.dev/)
- [Tautulli Docs](https://docs.tautulli.com/)
- [Official Plex Developer Docs](https://www.plex.tv/api/)

## Troubleshooting

### Settings page shows "Connection failed"
- Verify your Plex server is running and accessible
- Check the host/port are correct (e.g., `192.168.1.100:32400`)
- Ensure your Plex token is valid
- Try toggling SSL on/off

### No libraries appear after settings
- Make sure you selected libraries in the settings modal
- Verify the libraries exist in your Plex server
- Check browser console for API errors (F12)

### Media images not loading
- Plex server must be accessible from your browser
- Images are transcoded - ensure transcoding is enabled in Plex
- Check network connectivity between browser and Plex server

### Hero banner not rotating
- Open browser console (F12) for JavaScript errors
- Ensure JavaScript is enabled
- Try refreshing the page

### Overseerr/Tautulli sections not appearing
- Verify the service is running and accessible
- Check the host/port are correct (without http://)
- Ensure API key/token is valid
- Check browser console (F12) for AJAX errors
- Try toggling the integration off/on in Settings

### Integration data not updating
- Ensure the services are running and reachable from your web server
- Check firewall/network rules allow communication
- Verify API credentials are correct in Settings
- Look at web server error logs for network timeouts

## Browser Compatibility

- ✅ Chrome/Chromium 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+

## Performance

- **Hero rotation**: Updates every 8 seconds
- **Recently added limit**: 30 items per library
- **TV pagination**: Loads all items, displays top 30
- **Image transcoding**: Handled by Plex server

## Security Notes

⚠️ **Important**:
- Your Plex token grants access to your media server
- Do NOT commit `config.json` to version control
- Use `.gitignore` to prevent accidental token exposure
- Consider using HTTPS for production deployments
- Restrict web server access if running on public internet

## Development

### Local Testing
```bash
cd /var/www/xphunk/plexview
php -S localhost:8000
# Visit http://localhost:8000 in browser
```

### Contributing
Feel free to fork, modify, and submit pull requests!

## License

This project is open source. Modify and distribute as needed.

## Credits

- **Plex**: For the amazing media server API
- **Design Inspiration**: Modern streaming service UIs
- **Built with**: PHP, HTML5, CSS3, Vanilla JavaScript

---

**Questions?** Check your Plex server settings or refer to the [Plex documentation](https://support.plex.tv/).

**Version**: 1.0.0  
**Last Updated**: January 2026
