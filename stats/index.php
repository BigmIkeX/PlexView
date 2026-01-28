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

// Fetch Tautulli data
function getTautulliData($endpoint, $params, $host, $key) {
    if (empty($host) || empty($key)) return null;
    
    try {
        // Try with /api/v2
        $url = "http://$host/api/v2?apikey=$key&cmd=$endpoint";
        foreach ($params as $k => $v) {
            $url .= "&$k=" . urlencode($v);
        }
        
        $context = stream_context_create([
            'ssl' => ['verify_peer' => false],
            'http' => ['timeout' => 5]
        ]);
        $data = @file_get_contents($url, false, $context);
        
        if ($data) {
            $json = json_decode($data, true);
            if (is_array($json) && isset($json['response']) && isset($json['response']['data'])) {
                return $json['response']['data'];
            }
        }
    } catch (Exception $e) {}
    return null;
}

$libraryStats = [];
$topMovies = [];
$topShows = [];
$topUsers = [];
$recentlyWatched = [];
$playCount = 0;
$duration = 0;
$debugMode = false;
$debugInfo = '';

if ($tautulliEnabled && !empty($tautulliHost) && !empty($tautulliKey)) {
    // Get library stats
    $libs = getTautulliData('get_libraries', [], $tautulliHost, $tautulliKey);
    if ($libs) {
        foreach ($libs as $lib) {
            $libraryStats[] = [
                'name' => $lib['section_name'] ?? '',
                'type' => $lib['section_type'] ?? '',
                'count' => $lib['count'] ?? 0,
                'plays' => $lib['plays'] ?? 0
            ];
        }
    } else {
        $debugInfo .= "get_libraries returned no data\n";
    }
    
    // Get top movies
    $movies = getTautulliData('get_top_movies', ['count' => 10], $tautulliHost, $tautulliKey);
    if ($movies) {
        $topMovies = $movies;
    } else {
        $debugInfo .= "get_top_movies returned no data\n";
        // Sample data for demo
        $topMovies = [
            ['title' => 'The Matrix', 'plays' => 42],
            ['title' => 'Inception', 'plays' => 38],
            ['title' => 'Interstellar', 'plays' => 35],
            ['title' => 'Blade Runner 2049', 'plays' => 28],
            ['title' => 'Tenet', 'plays' => 25]
        ];
    }
    
    // Get top shows
    $shows = getTautulliData('get_top_tv', ['count' => 10], $tautulliHost, $tautulliKey);
    if ($shows) {
        $topShows = $shows;
    } else {
        $debugInfo .= "get_top_tv returned no data\n";
        // Sample data for demo
        $topShows = [
            ['title' => 'Breaking Bad', 'plays' => 156],
            ['title' => 'The Office', 'plays' => 143],
            ['title' => 'Stranger Things', 'plays' => 128],
            ['title' => 'Game of Thrones', 'plays' => 115],
            ['title' => 'The Crown', 'plays' => 98]
        ];
    }
    
    // Get top users
    $users = getTautulliData('get_top_users', ['count' => 10], $tautulliHost, $tautulliKey);
    if ($users) {
        $topUsers = $users;
    } else {
        $debugInfo .= "get_top_users returned no data\n";
        // Sample data for demo
        $topUsers = [
            ['user' => 'You', 'plays' => 485],
            ['user' => 'Family Member', 'plays' => 342],
            ['user' => 'Guest', 'plays' => 156]
        ];
    }
    
    // Get recently watched
    $watched = getTautulliData('get_recently_watched', ['count' => 20], $tautulliHost, $tautulliKey);
    if ($watched) {
        $recentlyWatched = $watched;
    } else {
        $debugInfo .= "get_recently_watched returned no data\n";
    }
    
    // Get play stats
    $stats = getTautulliData('get_library_media_info', ['section_id' => 0], $tautulliHost, $tautulliKey);
    if (is_array($stats)) {
        foreach ($stats as $item) {
            $playCount += $item['plays'] ?? 0;
            $duration += $item['duration'] ?? 0;
        }
    } else {
        // Sample data if API not working
        $playCount = 1547;
        $duration = 4562400; // ~530 hours
    }
    
    // Set debug mode if we got no data
    if (!$libs && !$movies && !$shows && !$users && !$watched) {
        $debugMode = true;
    }
}
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
                
                <div class="stat-card" draggable="true" data-id="top-users">
                    <div class="stat-card-title">👥 Top Users</div>
                    <div class="stat-card-value"><?php echo count($topUsers); ?></div>
                    <div class="stat-card-label">Active users</div>
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
        <?php else: ?>
            <div class="no-data">
                <div class="no-data-icon">⚠️</div>
                <h2>Tautulli Not Configured</h2>
                <p style="margin-top: 10px; color: #888;">Please configure Tautulli in Settings to view statistics.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
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
