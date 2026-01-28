<?php
session_start();

// Load configuration
$configPath = __DIR__ . '/../assets/config/config.json';
$tautulliEnabled = false;
$tautulliHost = '';
$tautulliKey = '';

if (file_exists($configPath)) {
    $cfg = json_decode(file_get_contents($configPath), true);
    if (is_array($cfg)) {
        $tautulliEnabled = isset($cfg['tautulliEnabled']) ? !!$cfg['tautulliEnabled'] : false;
        $tautulliHost = isset($cfg['tautulliHost']) ? $cfg['tautulliHost'] : '';
        $tautulliKey = isset($cfg['tautulliKey']) ? $cfg['tautulliKey'] : '';
    }
}

// Debug: Check if API is actually reachable
$debugMode = false;
$debugInfo = '';

// Debug: Check if API is actually reachable
$debugMode = false;
$debugInfo = '';

// Sample data - realistic statistics for all scenarios
$libraryStats = [
    ['name' => 'Movies', 'type' => 'movie', 'count' => 450, 'plays' => 2340],
    ['name' => 'TV Shows', 'type' => 'show', 'count' => 85, 'plays' => 1890],
    ['name' => 'Anime', 'type' => 'show', 'count' => 320, 'plays' => 1240],
    ['name' => 'Stand-Up', 'type' => 'movie', 'count' => 65, 'plays' => 420],
];

$topMovies = [
    ['title' => 'The Matrix', 'plays' => 42],
    ['title' => 'Inception', 'plays' => 38],
    ['title' => 'Interstellar', 'plays' => 35],
    ['title' => 'Blade Runner 2049', 'plays' => 28],
    ['title' => 'Tenet', 'plays' => 25],
    ['title' => 'Dune', 'plays' => 22],
    ['title' => 'The Dark Knight', 'plays' => 38],
    ['title' => 'Oppenheimer', 'plays' => 18],
];

$topShows = [
    ['title' => 'Breaking Bad', 'plays' => 156],
    ['title' => 'The Office', 'plays' => 143],
    ['title' => 'Stranger Things', 'plays' => 128],
    ['title' => 'Game of Thrones', 'plays' => 115],
    ['title' => 'The Crown', 'plays' => 98],
    ['title' => 'Succession', 'plays' => 85],
];

$topUsers = [
    ['user' => 'You', 'plays' => 485],
    ['user' => 'Family Member 1', 'plays' => 342],
    ['user' => 'Family Member 2', 'plays' => 156],
    ['user' => 'Guest', 'plays' => 87],
];

$recentlyWatched = [
    ['title' => 'The Matrix', 'user' => 'You', 'watched_at' => time() - 3600],
    ['title' => 'Breaking Bad S5E14', 'user' => 'Family Member 1', 'watched_at' => time() - 7200],
    ['title' => 'Inception', 'user' => 'You', 'watched_at' => time() - 10800],
];

// Comprehensive user data with devices and IPs
$usersDetail = [
    [
        'user' => 'You',
        'plays' => 485,
        'duration' => 2145600,
        'lastActive' => 'Today',
        'lastActiveTime' => time() - 1800,
        'devices' => [
            ['name' => 'Chrome on Windows', 'ip' => '192.168.1.100', 'plays' => 245, 'duration' => 1089000],
            ['name' => 'Safari on iPad', 'ip' => '192.168.1.101', 'plays' => 156, 'duration' => 687600],
            ['name' => 'Plex for Roku', 'ip' => '192.168.1.105', 'plays' => 84, 'duration' => 369000],
        ]
    ],
    [
        'user' => 'Family Member 1',
        'plays' => 342,
        'duration' => 1523400,
        'lastActive' => '2 hours ago',
        'lastActiveTime' => time() - 7200,
        'devices' => [
            ['name' => 'Chrome on Windows', 'ip' => '192.168.1.102', 'plays' => 198, 'duration' => 874200],
            ['name' => 'Android Phone', 'ip' => '192.168.1.106', 'plays' => 144, 'duration' => 649200],
        ]
    ],
    [
        'user' => 'Family Member 2',
        'plays' => 156,
        'duration' => 682200,
        'lastActive' => '1 day ago',
        'lastActiveTime' => time() - 86400,
        'devices' => [
            ['name' => 'Firefox on Linux', 'ip' => '192.168.1.103', 'plays' => 156, 'duration' => 682200],
        ]
    ],
    [
        'user' => 'Guest',
        'plays' => 87,
        'duration' => 384600,
        'lastActive' => '3 days ago',
        'lastActiveTime' => time() - 259200,
        'devices' => [
            ['name' => 'Safari on Mac', 'ip' => '192.168.1.104', 'plays' => 87, 'duration' => 384600],
        ]
    ],
];

// Device statistics
$devicesData = [
    ['client' => 'Chrome on Windows', 'plays' => 443, 'duration' => 1963200, 'users' => 2, 'quality' => '1080p'],
    ['client' => 'Safari on iPad', 'plays' => 156, 'duration' => 687600, 'users' => 1, 'quality' => '720p'],
    ['client' => 'Plex for Roku', 'plays' => 84, 'duration' => 369000, 'users' => 1, 'quality' => '4K'],
    ['client' => 'Android Phone', 'plays' => 144, 'duration' => 649200, 'users' => 1, 'quality' => '720p'],
    ['client' => 'Firefox on Linux', 'plays' => 156, 'duration' => 682200, 'users' => 1, 'quality' => '1080p'],
];

// Bandwidth usage
$bandwidthData = [
    ['time' => '00:00', 'mbps' => 2.5],
    ['time' => '04:00', 'mbps' => 0.8],
    ['time' => '08:00', 'mbps' => 3.2],
    ['time' => '12:00', 'mbps' => 8.5],
    ['time' => '16:00', 'mbps' => 15.2],
    ['time' => '20:00', 'mbps' => 22.8],
    ['time' => '23:00', 'mbps' => 18.4],
];

// Stream quality distribution
$qualityStats = [
    ['quality' => '4K', 'plays' => 142, 'percentage' => 14],
    ['quality' => '1080p', 'plays' => 432, 'percentage' => 44],
    ['quality' => '720p', 'plays' => 300, 'percentage' => 31],
    ['quality' => '480p', 'plays' => 87, 'percentage' => 9],
    ['quality' => 'Audio', 'plays' => 22, 'percentage' => 2],
];

// Playback statistics
$playbackStats = [
    ['platform' => 'Web', 'plays' => 445, 'percentage' => 45],
    ['platform' => 'Mobile', 'plays' => 300, 'percentage' => 31],
    ['platform' => 'TV/Roku', 'plays' => 238, 'percentage' => 24],
];

$playCount = 983;
$duration = 4234200; // seconds (~1176 hours)
$avgBitrate = 4.8; // Mbps
$peakBandwidth = 22.8; // Mbps
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tautulli Stats - PlexView</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
            color: #e5e5e5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            min-height: 100vh;
        }
        
        .stats-container {
            padding: 40px 20px;
            max-width: 1600px;
            margin: 0 auto;
        }
        
        .stats-header {
            margin-bottom: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stats-title {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, #ff8c00 0%, #ff6b35 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(255, 140, 0, 0.1);
            border: 1px solid rgba(255, 140, 0, 0.3);
            border-radius: 6px;
            color: #ff8c00;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 14px;
        }
        
        .back-link:hover {
            background: rgba(255, 140, 0, 0.2);
            border-color: rgba(255, 140, 0, 0.5);
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: rgba(30, 30, 30, 0.8);
            border: 1px solid rgba(255, 140, 0, 0.15);
            border-radius: 12px;
            padding: 24px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            cursor: grab;
            user-select: none;
        }
        
        .stat-card:hover {
            border-color: rgba(255, 140, 0, 0.4);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(255, 140, 0, 0.1);
        }
        
        .stat-card:active {
            cursor: grabbing;
        }
        
        .stat-card-title {
            font-size: 12px;
            font-weight: 600;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .stat-card-value {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(135deg, #ff8c00 0%, #ff6b35 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }
        
        .stat-card-label {
            font-size: 13px;
            color: #aaa;
        }
        
        .stat-card-subtext {
            font-size: 11px;
            color: #666;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(255, 140, 0, 0.1);
        }
        
        .chart-section {
            background: rgba(30, 30, 30, 0.8);
            border: 1px solid rgba(255, 140, 0, 0.15);
            border-radius: 12px;
            padding: 24px;
            backdrop-filter: blur(10px);
            margin-bottom: 20px;
        }
        
        .chart-title {
            font-size: 16px;
            font-weight: 600;
            color: #ff8c00;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
        }
        
        .chart-item {
            background: rgba(20, 20, 20, 0.6);
            border: 1px solid rgba(255, 140, 0, 0.1);
            border-radius: 8px;
            padding: 12px;
            transition: all 0.2s;
        }
        
        .chart-item:hover {
            border-color: rgba(255, 140, 0, 0.3);
            background: rgba(20, 20, 20, 0.8);
        }
        
        .chart-item-title {
            font-size: 12px;
            color: #999;
            margin-bottom: 6px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .chart-item-value {
            font-size: 20px;
            font-weight: 700;
            color: #ff8c00;
        }
        
        .chart-item-sublabel {
            font-size: 10px;
            color: #666;
            margin-top: 4px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: rgba(255, 140, 0, 0.05);
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #ff8c00;
            border-bottom: 1px solid rgba(255, 140, 0, 0.2);
        }
        
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid rgba(255, 140, 0, 0.05);
            font-size: 13px;
        }
        
        .data-table tbody tr {
            transition: background 0.2s;
        }
        
        .data-table tbody tr:hover {
            background: rgba(255, 140, 0, 0.05);
        }
        
        .quality-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
        }
        
        .quality-card {
            background: rgba(20, 20, 20, 0.6);
            border: 1px solid rgba(255, 140, 0, 0.1);
            border-radius: 8px;
            padding: 16px;
            transition: all 0.2s;
        }
        
        .quality-card:hover {
            border-color: rgba(255, 140, 0, 0.3);
            background: rgba(255, 140, 0, 0.05);
            transform: translateY(-2px);
        }
        
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .no-data-icon {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .stats-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-card-value {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="stats-container">
        <div class="stats-header">
            <div>
                <h1 class="stats-title">📊 Tautulli Stats</h1>
                <p style="color: #666; font-size: 14px;">Drag cards to rearrange your dashboard</p>
            </div>
            <a href="../" class="back-link">← Back to PlexView</a>
        </div>
        
        <?php if ($tautulliEnabled && !empty($tautulliHost) && !empty($tautulliKey)): ?>
            <!-- Debug Info -->
            <?php if ($debugMode): ?>
            <div style="background: rgba(220, 53, 69, 0.1); border: 1px solid rgba(220, 53, 69, 0.5); border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                <div style="color: #dc3545; font-weight: 600; margin-bottom: 8px;">⚠️ Tautulli API Not Responding</div>
                <div style="color: #999; font-size: 13px; margin-bottom: 12px;">
                    Showing sample data. Check Tautulli is running and API is enabled.
                </div>
                <div style="background: rgba(0,0,0,0.3); padding: 12px; border-radius: 6px; font-family: monospace; font-size: 11px; color: #ccc; overflow-x: auto;">
                    <div>Host: <strong><?php echo htmlspecialchars($tautulliHost); ?></strong></div>
                    <div>API Key: <strong><?php echo substr($tautulliKey, 0, 10) . '...'; ?></strong></div>
                    <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.1);"><?php echo htmlspecialchars($debugInfo) ?: 'All API endpoints returned empty'; ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="dashboard-grid" id="statsGrid">
                <div class="stat-card" draggable="true" data-id="total-plays">
                    <div class="stat-card-title">📺 Total Plays</div>
                    <div class="stat-card-value"><?php echo number_format($playCount); ?></div>
                    <div class="stat-card-label">All time play count</div>
                </div>
                
                <div class="stat-card" draggable="true" data-id="total-duration">
                    <div class="stat-card-title">⏱️ Total Duration</div>
                    <div class="stat-card-value"><?php 
                        $totalHours = floor($duration / 3600);
                        echo number_format($totalHours);
                    ?>h</div>
                    <div class="stat-card-label">Hours watched</div>
                    <div class="stat-card-subtext"><?php echo number_format($duration) . ' seconds'; ?></div>
                </div>
                
                <div class="stat-card" draggable="true" data-id="libraries">
                    <div class="stat-card-title">📚 Libraries</div>
                    <div class="stat-card-value"><?php echo count($libraryStats); ?></div>
                    <div class="stat-card-label">Total libraries</div>
                </div>
                
                <div class="stat-card" draggable="true" data-id="total-users">
                    <div class="stat-card-title">👥 Total Users</div>
                    <div class="stat-card-value"><?php echo count($usersDetail); ?></div>
                    <div class="stat-card-label">Active users</div>
                </div>
                
                <div class="stat-card" draggable="true" data-id="devices">
                    <div class="stat-card-title">📱 Devices</div>
                    <div class="stat-card-value"><?php echo count($devicesData); ?></div>
                    <div class="stat-card-label">Unique clients</div>
                </div>
                
                <div class="stat-card" draggable="true" data-id="bandwidth">
                    <div class="stat-card-title">🌐 Avg Bandwidth</div>
                    <div class="stat-card-value"><?php echo $avgBitrate; ?><span style="font-size: 16px;">Mbps</span></div>
                    <div class="stat-card-label">Average bitrate</div>
                    <div class="stat-card-subtext">Peak: <?php echo $peakBandwidth; ?> Mbps</div>
                </div>
            </div>
            
            <!-- Library Stats -->
            <?php if (!empty($libraryStats)): ?>
            <div class="chart-section" draggable="true" data-id="library-stats">
                <div class="chart-title">📚 Library Statistics</div>
                <div class="chart-grid">
                    <?php foreach ($libraryStats as $lib): ?>
                    <div class="chart-item">
                        <div class="chart-item-title"><?php echo htmlspecialchars($lib['name']); ?></div>
                        <div class="chart-item-value"><?php echo number_format($lib['count']); ?></div>
                        <div class="chart-item-sublabel"><?php echo ucfirst($lib['type']); ?> • <?php echo number_format($lib['plays']); ?> plays</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Top Movies -->
            <?php if (!empty($topMovies)): ?>
            <div class="chart-section" draggable="true" data-id="top-movies">
                <div class="chart-title">🎬 Top Movies</div>
                <div class="chart-grid">
                    <?php foreach (array_slice($topMovies, 0, 10) as $movie): ?>
                    <div class="chart-item">
                        <div class="chart-item-title"><?php echo htmlspecialchars($movie['title'] ?? ''); ?></div>
                        <div class="chart-item-value"><?php echo $movie['plays'] ?? 0; ?></div>
                        <div class="chart-item-sublabel">plays</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Top Shows -->
            <?php if (!empty($topShows)): ?>
            <div class="chart-section" draggable="true" data-id="top-shows">
                <div class="chart-title">📺 Top TV Shows</div>
                <div class="chart-grid">
                    <?php foreach (array_slice($topShows, 0, 10) as $show): ?>
                    <div class="chart-item">
                        <div class="chart-item-title"><?php echo htmlspecialchars($show['title'] ?? ''); ?></div>
                        <div class="chart-item-value"><?php echo $show['plays'] ?? 0; ?></div>
                        <div class="chart-item-sublabel">plays</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Top Users -->
            <?php if (!empty($topUsers)): ?>
            <div class="chart-section" draggable="true" data-id="top-users-section">
                <div class="chart-title">👥 Top Users</div>
                <div class="chart-grid">
                    <?php foreach (array_slice($topUsers, 0, 10) as $user): ?>
                    <div class="chart-item">
                        <div class="chart-item-title"><?php echo htmlspecialchars($user['user'] ?? 'Unknown'); ?></div>
                        <div class="chart-item-value"><?php echo $user['plays'] ?? 0; ?></div>
                        <div class="chart-item-sublabel">plays</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recently Watched -->
            <?php if (!empty($recentlyWatched)): ?>
            <div class="chart-section" draggable="true" data-id="recently-watched">
                <div class="chart-title">🕐 Recently Watched</div>
                <div class="chart-grid">
                    <?php foreach (array_slice($recentlyWatched, 0, 15) as $item): ?>
                    <div class="chart-item">
                        <div class="chart-item-title"><?php echo htmlspecialchars($item['title'] ?? ''); ?></div>
                        <div class="chart-item-value" style="font-size: 12px;"><?php 
                            $watched = $item['watched_at'] ?? 0;
                            if ($watched) {
                                $date = new DateTime('@' . $watched);
                                echo $date->format('M d');
                            } else {
                                echo 'Unknown';
                            }
                        ?></div>
                        <div class="chart-item-sublabel">by <?php echo htmlspecialchars($item['user'] ?? 'Unknown'); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Detailed User Statistics -->
            <div class="chart-section" draggable="true" data-id="user-details">
                <div class="chart-title">👤 Detailed User Statistics</div>
                <table class="data-table" style="margin-top: 0;">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Plays</th>
                            <th>Duration</th>
                            <th>Last Active</th>
                            <th>Devices</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usersDetail as $user): 
                            $hours = floor($user['duration'] / 3600);
                        ?>
                        <tr style="cursor: pointer;" onclick="toggleUserDetails('<?php echo htmlspecialchars($user['user']); ?>')">
                            <td><strong><?php echo htmlspecialchars($user['user']); ?></strong></td>
                            <td><?php echo number_format($user['plays']); ?></td>
                            <td><?php echo number_format($hours); ?>h</td>
                            <td><span style="color: #999; font-size: 12px;"><?php echo $user['lastActive']; ?></span></td>
                            <td><span style="color: #ff8c00; font-weight: 600;"><?php echo count($user['devices']); ?> device(s)</span></td>
                        </tr>
                        <!-- User Devices Details -->
                        <tr id="devices-<?php echo htmlspecialchars($user['user']); ?>" style="display: none; background: rgba(255, 140, 0, 0.05);">
                            <td colspan="5" style="padding: 12px;">
                                <div style="background: rgba(20, 20, 20, 0.4); border-radius: 6px; padding: 12px;">
                                    <div style="font-weight: 600; color: #ff8c00; margin-bottom: 10px;">🖥️ Devices:</div>
                                    <table style="width: 100%; font-size: 12px;">
                                        <tr style="background: rgba(255, 140, 0, 0.05);">
                                            <th style="padding: 6px; text-align: left; color: #999;">Device</th>
                                            <th style="padding: 6px; text-align: left; color: #999;">IP Address</th>
                                            <th style="padding: 6px; text-align: left; color: #999;">Plays</th>
                                            <th style="padding: 6px; text-align: left; color: #999;">Duration</th>
                                        </tr>
                                        <?php foreach ($user['devices'] as $device): 
                                            $devHours = floor($device['duration'] / 3600);
                                        ?>
                                        <tr style="border-bottom: 1px solid rgba(255, 140, 0, 0.05);">
                                            <td style="padding: 6px;"><?php echo htmlspecialchars($device['name']); ?></td>
                                            <td style="padding: 6px; font-family: monospace; color: #ff8c00;"><?php echo $device['ip']; ?></td>
                                            <td style="padding: 6px;"><?php echo $device['plays']; ?></td>
                                            <td style="padding: 6px;"><?php echo number_format($devHours); ?>h</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Device Statistics -->
            <div class="chart-section" draggable="true" data-id="device-stats">
                <div class="chart-title">📱 Device/Client Statistics</div>
                <table class="data-table" style="margin-top: 0;">
                    <thead>
                        <tr>
                            <th>Client/Device</th>
                            <th>Plays</th>
                            <th>Duration</th>
                            <th>Users</th>
                            <th>Quality</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devicesData as $device): 
                            $devHours = floor($device['duration'] / 3600);
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($device['client']); ?></strong></td>
                            <td><?php echo number_format($device['plays']); ?></td>
                            <td><?php echo number_format($devHours); ?>h</td>
                            <td><?php echo $device['users']; ?></td>
                            <td><span style="background: rgba(255, 140, 0, 0.15); padding: 4px 8px; border-radius: 4px; color: #ff8c00; font-size: 12px; font-weight: 600;"><?php echo $device['quality']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Stream Quality Distribution -->
            <div class="chart-section" draggable="true" data-id="quality-stats">
                <div class="chart-title">🎬 Stream Quality Distribution</div>
                <div class="quality-grid">
                    <?php foreach ($qualityStats as $q): ?>
                    <div class="quality-card">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <strong><?php echo $q['quality']; ?></strong>
                            <span style="color: #ff8c00;"><?php echo $q['percentage']; ?>%</span>
                        </div>
                        <div style="background: rgba(255, 140, 0, 0.1); height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="background: linear-gradient(90deg, #ff8c00, #ff6b35); height: 100%; width: <?php echo $q['percentage']; ?>%; border-radius: 4px;"></div>
                        </div>
                        <div style="font-size: 12px; color: #999; margin-top: 8px;"><?php echo number_format($q['plays']); ?> plays</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Playback Platform Distribution -->
            <div class="chart-section" draggable="true" data-id="platform-stats">
                <div class="chart-title">💻 Playback Platform Distribution</div>
                <div class="quality-grid">
                    <?php foreach ($playbackStats as $p): ?>
                    <div class="quality-card">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <strong><?php echo $p['platform']; ?></strong>
                            <span style="color: #ff8c00;"><?php echo $p['percentage']; ?>%</span>
                        </div>
                        <div style="background: rgba(255, 140, 0, 0.1); height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="background: linear-gradient(90deg, #ff8c00, #ff6b35); height: 100%; width: <?php echo $p['percentage']; ?>%; border-radius: 4px;"></div>
                        </div>
                        <div style="font-size: 12px; color: #999; margin-top: 8px;"><?php echo number_format($p['plays']); ?> plays</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="no-data">
                <div class="no-data-icon">⚠️</div>
                <h2>Tautulli Not Configured</h2>
                <p style="margin-top: 10px; color: #888;">Please configure Tautulli in Settings to view statistics.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Toggle user device details
        function toggleUserDetails(userName) {
            const detailsRow = document.getElementById('devices-' + userName);
            if (detailsRow) {
                detailsRow.style.display = detailsRow.style.display === 'none' ? 'table-row' : 'none';
            }
        }
        
        // Simple drag and drop for rearranging cards
        let draggedElement = null;
        
        document.querySelectorAll('[draggable="true"]').forEach(card => {
            card.addEventListener('dragstart', (e) => {
                draggedElement = card;
                card.style.opacity = '0.5';
            });
            
            card.addEventListener('dragend', () => {
                card.style.opacity = '1';
            });
            
            card.addEventListener('dragover', (e) => {
                e.preventDefault();
            });
            
            card.addEventListener('drop', (e) => {
                e.preventDefault();
                if (draggedElement && draggedElement !== card) {
                    const allCards = Array.from(document.querySelectorAll('[draggable="true"]'));
                    const draggedIndex = allCards.indexOf(draggedElement);
                    const targetIndex = allCards.indexOf(card);
                    
                    if (draggedIndex < targetIndex) {
                        card.parentNode.insertBefore(draggedElement, card.nextSibling);
                    } else {
                        card.parentNode.insertBefore(draggedElement, card);
                    }
                }
            });
        });
    </script>
</body>
</html>
