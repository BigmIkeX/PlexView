<?php
session_start();

// Configuration defaults
$name = "Plex Library Viewer";
$useSSL = true;
$host = "";
$port = 32400;
$token = "";
$selectedLibraries = [];

// Overseerr integration
$overseerrEnabled = false;
$overseerrHost = "";
$overseerrToken = "";

// Tautulli integration
$tautulliEnabled = false;
$tautulliHost = "";
$tautulliKey = "";

// TMDB integration (for Overseerr media ID lookup)
$tmdbApiKey = "";

$configPath = __DIR__ . '/assets/config/config.json';
if (file_exists($configPath)) {
    $cfgContent = file_get_contents($configPath);
    $cfg = json_decode($cfgContent, true);
    if (is_array($cfg)) {
        $name = isset($cfg['name']) ? $cfg['name'] : $name;
        $useSSL = isset($cfg['useSSL']) ? !!$cfg['useSSL'] : $useSSL;
        $host = isset($cfg['host']) ? $cfg['host'] : $host;
        $token = isset($cfg['token']) ? $cfg['token'] : $token;
        $selectedLibraries = isset($cfg['selectedLibraries']) && is_array($cfg['selectedLibraries']) ? $cfg['selectedLibraries'] : [];
        $overseerrEnabled = isset($cfg['overseerrEnabled']) ? !!$cfg['overseerrEnabled'] : $overseerrEnabled;
        $overseerrHost = isset($cfg['overseerrHost']) ? $cfg['overseerrHost'] : $overseerrHost;
        $overseerrToken = isset($cfg['overseerrToken']) ? $cfg['overseerrToken'] : $overseerrToken;
        $tautulliEnabled = isset($cfg['tautulliEnabled']) ? !!$cfg['tautulliEnabled'] : $tautulliEnabled;
        $tautulliHost = isset($cfg['tautulliHost']) ? $cfg['tautulliHost'] : $tautulliHost;
        $tautulliKey = isset($cfg['tautulliKey']) ? $cfg['tautulliKey'] : $tautulliKey;
        $tmdbApiKey = isset($cfg['tmdbApiKey']) ? $cfg['tmdbApiKey'] : $tmdbApiKey;
    }
}

// Handle settings save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings_submit'])) {
    $name = trim($_POST['name'] ?? $name);
    $useSSL = isset($_POST['useSSL']) && $_POST['useSSL'] === '1';
    $host = trim($_POST['host'] ?? $host);
    $token = trim($_POST['token'] ?? $token);
    $selectedLibraries = isset($_POST['selectedLibraries']) && is_array($_POST['selectedLibraries']) ? $_POST['selectedLibraries'] : [];
    
    // Overseerr settings
    $overseerrEnabled = isset($_POST['overseerrEnabled']) && $_POST['overseerrEnabled'] === '1';
    $overseerrHost = trim($_POST['overseerrHost'] ?? $overseerrHost);
    $overseerrToken = trim($_POST['overseerrToken'] ?? $overseerrToken);
    
    // Tautulli settings
    $tautulliEnabled = isset($_POST['tautulliEnabled']) && $_POST['tautulliEnabled'] === '1';
    $tautulliHost = trim($_POST['tautulliHost'] ?? $tautulliHost);
    $tautulliKey = trim($_POST['tautulliKey'] ?? $tautulliKey);
    
    // TMDB settings
    $tmdbApiKey = trim($_POST['tmdbApiKey'] ?? $tmdbApiKey);
    
    $httpTmp = $useSSL ? 'https' : 'http';
    $probeOk = false;
    if (!empty($host) && !empty($token)) {
        $probeUrl = "$httpTmp://$host/library/sections?X-Plex-Token=$token";
        $context = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        $probeData = @file_get_contents($probeUrl, false, $context);
        if ($probeData !== false) {
            $probeXml = @simplexml_load_string($probeData);
            if ($probeXml !== false) { $probeOk = true; }
        }
    }
    
    if ($probeOk) {
        $save = [
            'name' => $name,
            'useSSL' => $useSSL,
            'host' => $host,
            'token' => $token,
            'selectedLibraries' => $selectedLibraries,
            'overseerrEnabled' => $overseerrEnabled,
            'overseerrHost' => $overseerrHost,
            'overseerrToken' => $overseerrToken,
            'tautulliEnabled' => $tautulliEnabled,
            'tautulliHost' => $tautulliHost,
            'tautulliKey' => $tautulliKey,
            'tmdbApiKey' => $tmdbApiKey
        ];
        @mkdir(__DIR__ . '/assets/config', 0755, true);
        @file_put_contents($configPath, json_encode($save, JSON_PRETTY_PRINT));
        $_SESSION['settings_status'] = 'success';
        $_SESSION['settings_message'] = 'Settings saved!';
        // Redirect to prevent form resubmission and clear message after display
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $_SESSION['settings_status'] = 'error';
        $_SESSION['settings_message'] = 'Could not connect to Plex. Check your settings.';
    }
}

// Helper function: Fetch Tautulli watch stats
function getTautulliStats($key, $tautulliHost, $tautulliKey) {
    if (empty($tautulliHost) || empty($tautulliKey)) return [];
    
    try {
        // Get library media info
        $url = "http://$tautulliHost/api/v2?apikey=$tautulliKey&cmd=get_library_media_info&section_id=$key";
        $context = stream_context_create(['ssl' => ['verify_peer' => false]]);
        $data = @file_get_contents($url, false, $context);
        if ($data) {
            $json = json_decode($data, true);
            if ($json && isset($json['response']['data'])) {
                $libData = $json['response']['data'];
                return [
                    'watchCount' => $libData['count'] ?? 0,
                    'plays' => $libData['plays'] ?? 0,
                    'duration' => $libData['duration'] ?? 0,
                    'userCount' => $libData['users'] ?? 0,
                    'lastWatched' => $libData['last_accessed'] ?? 0,
                    'totalDuration' => formatDuration($libData['duration'] ?? 0)
                ];
            }
        }
    } catch (Exception $e) {}
    return [];
}

// Helper function: Format duration in seconds to readable format
function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm';
    }
    return $minutes . 'm';
}

// Helper function: Fetch Overseerr media status with detailed info
function getOverseerrStatus($tmdbId, $mediaType, $overseerrHost, $overseerrToken) {
    if (empty($overseerrHost) || empty($overseerrToken) || empty($tmdbId)) return [];
    
    try {
        $type = ($mediaType === 'show') ? 'tv' : 'movie';
        $url = "http://$overseerrHost/api/v1/media?tmdbId=$tmdbId&mediaType=$type";
        $headers = ["X-Api-Key: $overseerrToken"];
        $context = stream_context_create([
            'http' => ['header' => $headers],
            'ssl' => ['verify_peer' => false]
        ]);
        $data = @file_get_contents($url, false, $context);
        if ($data) {
            $json = json_decode($data, true);
            if ($json && isset($json['status'])) {
                $statusMap = [1 => 'available', 2 => 'pending', 3 => 'processing'];
                $status = $statusMap[$json['status']] ?? 'unknown';
                
                $requestCount = 0;
                $lastRequester = 'Unknown';
                $requestDate = '';
                
                if (isset($json['requests']) && is_array($json['requests']) && count($json['requests']) > 0) {
                    $requestCount = count($json['requests']);
                    $lastReq = end($json['requests']);
                    if (isset($lastReq['requestedBy']['displayName'])) {
                        $lastRequester = $lastReq['requestedBy']['displayName'];
                    }
                    if (isset($lastReq['createdAt'])) {
                        $requestDate = date('M d, Y', strtotime($lastReq['createdAt']));
                    }
                }
                
                return [
                    'status' => $status,
                    'available' => $status === 'available',
                    'requested' => $requestCount > 0,
                    'requestCount' => $requestCount,
                    'lastRequester' => $lastRequester,
                    'requestDate' => $requestDate,
                    'voteScore' => isset($json['voteAverage']) ? number_format($json['voteAverage'] * 10) . '%' : 'N/A',
                    'popularity' => isset($json['popularity']) ? number_format($json['popularity'], 1) : 'N/A'
                ];
            }
        }
    } catch (Exception $e) {}
    return [];
}

// Helper function: Search TMDB for media by title
function searchTmdbMedia($title, $year, $mediaType, $tmdbApiKey) {
    if (empty($tmdbApiKey) || empty($title)) return null;
    
    try {
        $endpoint = ($mediaType === 'show') ? 'search/tv' : 'search/movie';
        $url = "https://api.themoviedb.org/3/$endpoint?api_key=$tmdbApiKey&query=" . urlencode($title);
        if (!empty($year)) {
            $url .= "&year=$year";
        }
        
        $context = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        $data = @file_get_contents($url, false, $context);
        if ($data) {
            $json = json_decode($data, true);
            if ($json && isset($json['results']) && is_array($json['results']) && count($json['results']) > 0) {
                $result = $json['results'][0];
                return [
                    'tmdbId' => $result['id'] ?? null,
                    'tvdbId' => $result['external_ids']['tvdb_id'] ?? null,
                    'title' => $result['title'] ?? $result['name'] ?? $title
                ];
            }
        }
    } catch (Exception $e) {}
    return null;
}

// Helper function: Search Overseerr for media by title
function searchOverseerrMedia($title, $mediaType, $overseerrHost, $overseerrToken) {
    if (empty($overseerrHost) || empty($overseerrToken) || empty($title)) return null;
    
    try {
        $type = ($mediaType === 'show') ? 'tv' : 'movie';
        $url = "http://$overseerrHost/api/v1/search?query=" . urlencode($title) . "&type=$type";
        $headers = ["X-Api-Key: $overseerrToken"];
        $context = stream_context_create([
            'http' => ['header' => $headers],
            'ssl' => ['verify_peer' => false]
        ]);
        $data = @file_get_contents($url, false, $context);
        if ($data) {
            $json = json_decode($data, true);
            if ($json && isset($json['results']) && is_array($json['results']) && count($json['results']) > 0) {
                $result = $json['results'][0];
                return [
                    'tmdbId' => $result['tmdbId'] ?? $result['id'] ?? null,
                    'tvdbId' => $result['tvdbId'] ?? null,
                    'mediaId' => $result['id'] ?? null,
                    'title' => $result['title'] ?? $result['name'] ?? $title
                ];
            }
        }
    } catch (Exception $e) {}
    return null;
}

// Helper function: Fetch trending media from Overseerr
function getTrendingFromOverseerr($overseerrHost, $overseerrToken) {
    if (empty($overseerrHost) || empty($overseerrToken)) return [];
    
    try {
        $url = "http://$overseerrHost/api/v1/discover/trending";
        $headers = ["X-Api-Key: $overseerrToken"];
        $context = stream_context_create([
            'http' => ['header' => $headers],
            'ssl' => ['verify_peer' => false]
        ]);
        $data = @file_get_contents($url, false, $context);
        if ($data) {
            $json = json_decode($data, true);
            if ($json && isset($json['results']) && is_array($json['results'])) {
                $trendingItems = [];
                foreach ($json['results'] as $item) {
                    // Skip if critical data is missing
                    if (empty($item['title']) || empty($item['posterPath'])) continue;
                    
                    $trendingItems[] = [
                        'title' => $item['title'] ?? '',
                        'thumb' => !empty($item['posterPath']) ? 'https://image.tmdb.org/t/p/w342' . $item['posterPath'] : '',
                        'art' => !empty($item['backdropPath']) ? 'https://image.tmdb.org/t/p/w1280' . $item['backdropPath'] : '',
                        'ratingKey' => $item['id'] ?? 0,
                        'year' => isset($item['releaseDate']) ? substr($item['releaseDate'], 0, 4) : (isset($item['firstAirDate']) ? substr($item['firstAirDate'], 0, 4) : ''),
                        'summary' => $item['overview'] ?? '',
                        'rating' => isset($item['voteAverage']) ? number_format($item['voteAverage'], 1) : 'N/A',
                        'contentRating' => $item['contentRating'] ?? '',
                        'cast' => [],
                        'genres' => $item['genreIds'] ?? [],
                        'type' => ($item['mediaType'] ?? 'movie') === 'tv' ? 'show' : 'movie',
                        'tmdbId' => $item['id'] ?? null,
                        'popularity' => $item['popularity'] ?? 0
                    ];
                }
                return array_slice($trendingItems, 0, 30);
            }
        }
    } catch (Exception $e) {}
    return [];
}

// Fetch media data
ini_set('display_errors', 0);
error_reporting(0);

$http = $useSSL ? 'https' : 'http';
$mediaData = [];
$allLibraries = [];
$trendingMedia = [];

if (!empty($host) && !empty($token)) {
    $context = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    
    // Fetch all library sections
    $url = "$http://$host/library/sections?X-Plex-Token=$token";
    $xmlData = @file_get_contents($url, false, $context);
    if ($xmlData) {
        $xml = @simplexml_load_string($xmlData);
        if ($xml && isset($xml->Directory)) {
            foreach ($xml->Directory as $section) {
                $type = (string)$section['type'];
                $key = (string)$section['key'];
                $title = (string)$section['title'];
                
                // Include all media library types
                if (in_array($type, ['movie', 'show', 'artist', 'photo'])) {
                    $allLibraries[] = [
                        'key' => $key,
                        'title' => $title,
                        'type' => $type
                    ];
                }
            }
        }
        
        // Fetch trending data from Overseerr if enabled
        if (empty($trendingMedia) && $overseerrEnabled && !empty($overseerrHost) && !empty($overseerrToken)) {
            $trendingMedia = getTrendingFromOverseerr($overseerrHost, $overseerrToken);
        }
        
        // Fallback to Plex recently added if Overseerr trending is unavailable
        if (empty($trendingMedia)) {
            foreach ($allLibraries as $lib) {
                if (in_array($lib['type'], ['movie', 'show'])) {
                    $trendingUrl = "$http://$host/library/sections/" . $lib['key'] . "/recentlyAdded?limit=30&X-Plex-Token=$token";
                    $trendingData = @file_get_contents($trendingUrl, false, $context);
                    if ($trendingData) {
                        $trendingXml = @simplexml_load_string($trendingData);
                        if ($trendingXml) {
                            $trendingItems = [];
                            if ($lib['type'] === 'movie' && isset($trendingXml->Video)) {
                                foreach ($trendingXml->Video as $item) {
                                    $cast = [];
                                    if (isset($item->Role)) {
                                        foreach ($item->Role as $role) {
                                            $cast[] = (string)$role['tag'];
                                            if (count($cast) >= 5) break;
                                        }
                                    }
                                    
                                    $genres = [];
                                    if (isset($item->Genre)) {
                                        foreach ($item->Genre as $genre) {
                                            $genres[] = (string)$genre['tag'];
                                        }
                                    }
                                    
                                    $trendingItems[] = [
                                        'title' => (string)$item['title'],
                                        'thumb' => (string)$item['thumb'],
                                        'art' => (string)$item['art'],
                                        'ratingKey' => (string)$item['ratingKey'],
                                        'year' => (string)$item['year'],
                                        'summary' => (string)$item['summary'],
                                        'rating' => (string)$item['rating'],
                                        'contentRating' => (string)$item['contentRating'],
                                        'duration' => (int)$item['duration'],
                                        'cast' => $cast,
                                        'genres' => $genres,
                                        'type' => 'movie'
                                    ];
                                }
                            } elseif ($lib['type'] === 'show' && isset($trendingXml->Directory)) {
                                foreach ($trendingXml->Directory as $item) {
                                    $cast = [];
                                    if (isset($item->Role)) {
                                        foreach ($item->Role as $role) {
                                            $cast[] = (string)$role['tag'];
                                            if (count($cast) >= 5) break;
                                        }
                                    }
                                    
                                    $genres = [];
                                    if (isset($item->Genre)) {
                                        foreach ($item->Genre as $genre) {
                                            $genres[] = (string)$genre['tag'];
                                        }
                                    }
                                    
                                    $trendingItems[] = [
                                        'title' => (string)$item['title'],
                                        'thumb' => (string)$item['thumb'],
                                        'art' => (string)$item['art'],
                                        'ratingKey' => (string)$item['ratingKey'],
                                        'year' => (string)$item['year'],
                                        'summary' => (string)$item['summary'],
                                        'rating' => (string)$item['rating'],
                                        'contentRating' => (string)$item['contentRating'],
                                        'cast' => $cast,
                                        'genres' => $genres,
                                        'type' => 'show'
                                    ];
                                }
                            }
                            $trendingMedia = array_slice($trendingItems, 0, 30);
                            if (!empty($trendingMedia)) break;
                        }
                    }
                }
            }
        }
    }
    
    // If no libraries are selected, select all libraries by default
    if (empty($selectedLibraries) && !empty($allLibraries)) {
        $selectedLibraries = array_map(function($lib) { return $lib['key']; }, $allLibraries);
    }
    
    // Fetch recently added content for each selected library
    foreach ($allLibraries as $lib) {
        if (in_array($lib['key'], $selectedLibraries)) {
            $libKey = $lib['key'];
            $libTitle = $lib['title'];
            $libType = $lib['type'];
            
            // TV shows need special handling - use /all with limit instead of recentlyAdded
            if ($libType === 'show') {
                // For TV, get first 30 shows from /all endpoint
                $url = "$http://$host/library/sections/$libKey/all?X-Plex-Container-Start=0&X-Plex-Container-Size=30&X-Plex-Token=$token";
            } else {
                $url = "$http://$host/library/sections/$libKey/recentlyAdded?limit=30&X-Plex-Token=$token";
            }
            
            $xmlData = @file_get_contents($url, false, $context);
            if ($xmlData) {
                $xml = @simplexml_load_string($xmlData);
                $items = [];
                
                if ($libType === 'movie' && isset($xml->Video)) {
                    foreach ($xml->Video as $item) {
                        if ((string)$item['type'] === 'movie') {
                            // Collect cast
                            $cast = [];
                            if (isset($item->Role)) {
                                foreach ($item->Role as $role) {
                                    $cast[] = (string)$role['tag'];
                                    if (count($cast) >= 5) break;
                                }
                            }
                            
                            // Collect genres
                            $genres = [];
                            if (isset($item->Genre)) {
                                foreach ($item->Genre as $genre) {
                                    $genres[] = (string)$genre['tag'];
                                }
                            }
                            
                            $items[] = [
                                'title' => (string)$item['title'],
                                'thumb' => (string)$item['thumb'],
                                'art' => (string)$item['art'],
                                'ratingKey' => (string)$item['ratingKey'],
                                'year' => (string)$item['year'],
                                'summary' => (string)$item['summary'],
                                'rating' => (string)$item['rating'],
                                'contentRating' => (string)$item['contentRating'],
                                'duration' => (string)$item['duration'],
                                'studio' => (string)$item['studio'],
                                'tagline' => (string)$item['tagline'],
                                'cast' => $cast,
                                'genres' => $genres,
                                'type' => 'movie'
                            ];
                        }
                    }
                } elseif ($libType === 'show' && isset($xml->Directory)) {
                    foreach ($xml->Directory as $item) {
                        if ((string)$item['type'] === 'show') {
                            // Collect cast
                            $cast = [];
                            if (isset($item->Role)) {
                                foreach ($item->Role as $role) {
                                    $cast[] = (string)$role['tag'];
                                    if (count($cast) >= 5) break;
                                }
                            }
                            
                            // Collect genres
                            $genres = [];
                            if (isset($item->Genre)) {
                                foreach ($item->Genre as $genre) {
                                    $genres[] = (string)$genre['tag'];
                                }
                            }
                            
                            $items[] = [
                                'title' => (string)$item['title'],
                                'thumb' => (string)$item['thumb'],
                                'art' => (string)$item['art'],
                                'ratingKey' => (string)$item['ratingKey'],
                                'year' => (string)$item['year'],
                                'summary' => (string)$item['summary'],
                                'rating' => (string)$item['rating'],
                                'contentRating' => (string)$item['contentRating'],
                                'studio' => (string)$item['studio'],
                                'cast' => $cast,
                                'genres' => $genres,
                                'type' => 'show'
                            ];
                        }
                    }
                } elseif ($libType === 'artist' && isset($xml->Directory)) {
                    foreach ($xml->Directory as $item) {
                        $itemType = (string)$item['type'];
                        // Music libraries return 'album' in recentlyAdded, not 'artist'
                        if ($itemType === 'artist' || $itemType === 'album') {
                            $genres = [];
                            if (isset($item->Genre)) {
                                foreach ($item->Genre as $genre) {
                                    $genres[] = (string)$genre['tag'];
                                }
                            }
                            
                            $items[] = [
                                'title' => (string)$item['title'],
                                'thumb' => (string)$item['thumb'],
                                'art' => (string)$item['art'],
                                'ratingKey' => (string)$item['ratingKey'],
                                'summary' => (string)$item['summary'],
                                'genres' => $genres,
                                'type' => 'artist'
                            ];
                        }
                    }
                } elseif ($libType === 'photo' && isset($xml->Photo)) {
                    foreach ($xml->Photo as $item) {
                        $items[] = [
                            'title' => (string)$item['title'],
                            'thumb' => (string)$item['thumb'],
                            'ratingKey' => (string)$item['ratingKey'],
                            'year' => (string)$item['year'],
                            'type' => 'photo'
                        ];
                    }
                }
                
                if (!empty($items)) {
                    $mediaData[$libKey] = [
                        'title' => $libTitle,
                        'type' => $libType,
                        'items' => array_slice($items, 0, 30)
                    ];
                }
            }
        }
    }
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'getTautulliStats' && isset($_GET['key'])) {
        $key = $_GET['key'];
        $stats = getTautulliStats($key, $tautulliHost, $tautulliKey);
        echo json_encode($stats ?: ['plays' => 0, 'watchCount' => 0, 'duration' => 0, 'userCount' => 0, 'lastWatched' => 0, 'totalDuration' => '0m']);
        exit;
    }
    
    if ($_GET['action'] === 'getOverseerrStatus' && isset($_GET['key']) && isset($_GET['type'])) {
        $key = $_GET['key'];
        $mediaType = $_GET['type'];
        // Try to get TMDB ID from Plex data if available
        $tmdbId = isset($_GET['tmdbId']) ? $_GET['tmdbId'] : '';
        if (!empty($tmdbId)) {
            $status = getOverseerrStatus($tmdbId, $mediaType, $overseerrHost, $overseerrToken);
            echo json_encode($status ?: ['available' => false, 'requested' => false, 'status' => 'unknown', 'requestCount' => 0, 'lastRequester' => 'N/A', 'requestDate' => 'N/A', 'voteScore' => 'N/A', 'popularity' => 'N/A']);
        } else {
            echo json_encode(['available' => false, 'requested' => false, 'status' => 'unknown', 'requestCount' => 0, 'lastRequester' => 'N/A', 'requestDate' => 'N/A', 'voteScore' => 'N/A', 'popularity' => 'N/A']);
        }
        exit;
    }
    
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// Handle POST requests
if (isset($_GET['action']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'requestMedia' && $overseerrEnabled) {
        $input = json_decode(file_get_contents('php://input'), true);
        $title = $input['title'] ?? '';
        $mediaType = $input['type'] ?? 'movie';
        $year = $input['year'] ?? '';
        
        if (!empty($title) && !empty($overseerrHost) && !empty($overseerrToken)) {
            // Search for media ID using TMDB first, fallback to Overseerr search
            $mediaId = null;
            $tvdbId = null;
            
            // Try TMDB first if API key available
            if (!empty($tmdbApiKey)) {
                $tmdbResult = searchTmdbMedia($title, $year, $mediaType, $tmdbApiKey);
                if ($tmdbResult) {
                    $mediaId = $tmdbResult['tmdbId'];
                    $tvdbId = $tmdbResult['tvdbId'];
                }
            }
            
            // Fallback to Overseerr search if TMDB didn't work
            if (empty($mediaId)) {
                $overseerrResult = searchOverseerrMedia($title, $mediaType, $overseerrHost, $overseerrToken);
                if ($overseerrResult) {
                    $mediaId = $overseerrResult['tmdbId'] ?? $overseerrResult['mediaId'];
                    $tvdbId = $overseerrResult['tvdbId'];
                }
            }
            
            if (!empty($mediaId)) {
                $type = ($mediaType === 'show') ? 'tv' : 'movie';
                $requestUrl = "http://$overseerrHost/api/v1/request";
                
                $postData = json_encode([
                    'mediaType' => $type,
                    'mediaId' => $mediaId,
                    'tvdbId' => $tvdbId ?? null,
                    'userId' => null,
                    'is4k' => false
                ]);
                
                $context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "X-Api-Key: $overseerrToken\r\nContent-Type: application/json",
                        'content' => $postData
                    ],
                    'ssl' => ['verify_peer' => false]
                ]);
                
                $result = @file_get_contents($requestUrl, false, $context);
                if ($result !== false) {
                    echo json_encode(['success' => true, 'message' => 'Request submitted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to submit request to Overseerr']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Could not find media. Check TMDB API key or Overseerr connection.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Missing required information']);
        }
        exit;
    }
    
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// Debug: Log library counts
error_log('PlexView Debug - Total libraries: ' . count($mediaData));
foreach ($mediaData as $key => $lib) {
    error_log('  Library ' . $key . ': ' . $lib['title'] . ' (' . $lib['type'] . ') - ' . count($lib['items']) . ' items');
}

// Collect all recent items for hero rotation (up to 20)
$heroItems = [];
foreach ($mediaData as $lib) {
    foreach ($lib['items'] as $item) {
        $heroItems[] = $item;
        if (count($heroItems) >= 20) break 2;
    }
}

// Organize media by type for different views
$movieLibraries = [];
$tvLibraries = [];
$musicLibraries = [];
$photoLibraries = [];
$allMovies = [];
$allTV = [];
$top20Recent = [];

foreach ($mediaData as $libKey => $lib) {
    if ($lib['type'] === 'movie') {
        $movieLibraries[$libKey] = $lib;
        $allMovies = array_merge($allMovies, $lib['items']);
    } elseif ($lib['type'] === 'show') {
        $tvLibraries[$libKey] = $lib;
        $allTV = array_merge($allTV, $lib['items']);
    } elseif ($lib['type'] === 'artist') {
        $musicLibraries[$libKey] = $lib;
    } elseif ($lib['type'] === 'photo') {
        $photoLibraries[$libKey] = $lib;
    }
}

// Create Top 20 recently added from all selected sections
foreach ($mediaData as $lib) {
    foreach ($lib['items'] as $item) {
        $top20Recent[] = $item;
        if (count($top20Recent) >= 20) break 2;
    }
}

function getImageUrl($thumb) {
    global $http, $host, $token;
    if (empty($thumb) || empty($host)) return '/images/placeholder.png';
    return "$http://$host/photo/:/transcode?url=$thumb&width=400&height=600&X-Plex-Token=$token";
}

function getHeroImageUrl($thumb) {
    global $http, $host, $token;
    if (empty($thumb) || empty($host)) return '/images/placeholder.png';
    return "$http://$host/photo/:/transcode?url=$thumb&width=1920&height=1080&X-Plex-Token=$token";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($name); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #0f0f0f;
            color: #e5e5e5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            overflow-x: hidden;
        }

        .navbar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(15, 15, 15, 0.95);
            border-top: 1px solid rgba(255, 140, 0, 0.2);
            padding: 12px 0;
            z-index: 1000;
            display: flex;
            justify-content: space-around;
            align-items: center;
            height: 60px;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
            cursor: pointer;
            transition: all 0.3s;
            color: #888;
            text-decoration: none;
            font-size: 12px;
            gap: 4px;
        }

        .nav-item:hover {
            color: #ff8c00;
        }

        .nav-item.active {
            color: #ff8c00;
            border-top: 2px solid #ff8c00;
        }

        .nav-icon {
            font-size: 24px;
        }

        .nav-icon svg {
            display: block;
        }

        .content {
            padding-bottom: 70px;
            padding-top: 0;
            max-width: 100%;
        }

        .hero {
            position: relative;
            height: 60vh;
            background: linear-gradient(135deg, #1a1a1a 0%, #0f0f0f 100%);
            overflow: hidden;
            margin-bottom: 40px;
        }

        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            opacity: 0.3;
            z-index: 1;
        }

        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, rgba(15,15,15,0.8) 0%, transparent 60%, rgba(15,15,15,0.8) 100%);
            z-index: 2;
        }

        .hero-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 40px;
            z-index: 3;
            background: linear-gradient(180deg, transparent 0%, rgba(15,15,15,0.9) 100%);
        }

        .hero-title {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #fff;
        }

        .hero-meta {
            font-size: 14px;
            color: #ff8c00;
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
        }

        .hero-description {
            font-size: 16px;
            color: #ccc;
            max-width: 600px;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .hero-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #ff8c00;
            color: #000;
        }

        .btn-primary:hover {
            background: #ffa500;
            box-shadow: 0 0 20px rgba(255, 140, 0, 0.5);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .section {
            margin-bottom: 40px;
            padding: 0 40px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #fff;
        }

        .carousel {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            scroll-behavior: smooth;
            padding-bottom: 10px;
        }

        .carousel::-webkit-scrollbar {
            height: 8px;
        }

        .carousel::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }

        .carousel::-webkit-scrollbar-thumb {
            background: #ff8c00;
            border-radius: 4px;
        }

        .media-card {
            flex: 0 0 160px;
            height: 240px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            background: rgba(255, 255, 255, 0.05);
        }

        .media-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(255, 140, 0, 0.2);
        }

        .media-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .media-card-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.8) 100%);
            display: flex;
            align-items: flex-end;
            padding: 12px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .media-card:hover .media-card-overlay {
            opacity: 1;
        }

        .media-card-title {
            font-size: 13px;
            font-weight: 600;
            color: #fff;
        }

        .settings-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .settings-modal.show {
            display: flex;
        }

        .settings-content {
            background: #1a1a1a;
            border-radius: 12px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid rgba(255, 140, 0, 0.2);
        }

        .settings-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 30px;
            color: #ff8c00;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #e5e5e5;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 140, 0, 0.2);
            border-radius: 6px;
            color: #fff;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #ff8c00;
            box-shadow: 0 0 10px rgba(255, 140, 0, 0.2);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #ff8c00;
        }

        .settings-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .settings-buttons button {
            flex: 1;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(0, 200, 100, 0.1);
            border: 1px solid rgba(0, 200, 100, 0.3);
            color: #00c864;
        }

        .alert-error {
            background: rgba(255, 68, 68, 0.1);
            border: 1px solid rgba(255, 68, 68, 0.3);
            color: #ff4444;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .empty-state-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        /* Media Modal Styles */
        .media-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            z-index: 3000;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
        }

        .media-modal.show {
            display: flex;
        }

        .media-modal-content {
            background: #1a1a1a;
            border-radius: 12px;
            max-width: 900px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid rgba(255, 140, 0, 0.2);
            position: relative;
        }

        .media-modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #fff;
            font-size: 24px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 10;
        }

        .media-modal-close:hover {
            background: rgba(255, 140, 0, 0.3);
        }

        .media-modal-hero {
            position: relative;
            height: 400px;
            background-size: cover;
            background-position: center;
            border-radius: 12px 12px 0 0;
        }

        .media-modal-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, transparent 0%, rgba(26, 26, 26, 0.9) 80%, #1a1a1a 100%);
        }

        .media-modal-info {
            padding: 40px;
            position: relative;
        }

        .media-modal-title {
            font-size: 36px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 10px;
        }

        .media-modal-meta {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: #ff8c00;
            margin-bottom: 20px;
        }

        .media-modal-description {
            font-size: 16px;
            line-height: 1.6;
            color: #ccc;
            margin-bottom: 30px;
        }

        .media-modal-section {
            margin-bottom: 25px;
        }

        .media-modal-section-title {
            font-size: 18px;
            font-weight: 600;
            color: #ff8c00;
            margin-bottom: 10px;
        }

        .media-modal-cast {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .media-cast-item {
            padding: 8px 16px;
            background: rgba(255, 140, 0, 0.1);
            border: 1px solid rgba(255, 140, 0, 0.3);
            border-radius: 20px;
            font-size: 14px;
            color: #e5e5e5;
        }

        .media-modal-genres {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .media-genre-item {
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            font-size: 13px;
            color: #888;
        }

        .media-modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .hero-rotation {
            transition: opacity 1s ease-in-out;
        }
    </style>
</head>
<body>
    <!-- HERO SECTION -->
    <?php if (!empty($heroItems)): ?>
    <div id="heroSection" class="hero hero-rotation">
        <div class="hero-bg"></div>
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1 class="hero-title" id="heroTitle"></h1>
            <div class="hero-meta">
                <span id="heroYear"></span>
                <span id="heroRating"></span>
            </div>
            <p class="hero-description" id="heroDescription"></p>
            <div class="hero-buttons">
                <button class="btn btn-primary">▶ Play</button>
                <button class="btn btn-secondary" onclick="showMediaDetails(currentHeroIndex)">ℹ More Info</button>
            </div>
        </div>
    </div>
    <script>
        const heroItems = <?php echo json_encode($heroItems); ?>;
        let currentHeroIndex = 0;
        
        function updateHero(index) {
            const item = heroItems[index];
            const heroSection = document.getElementById('heroSection');
            
            // Fade out
            heroSection.style.opacity = '0.5';
            
            setTimeout(() => {
                // Update background
                const bgUrl = '<?php echo $http . "://" . $host; ?>/photo/:/transcode?url=' + encodeURIComponent(item.art || item.thumb) + '&width=1920&height=1080&X-Plex-Token=<?php echo $token; ?>';
                heroSection.style.backgroundImage = 'url(' + bgUrl + ')';
                
                // Update content
                document.getElementById('heroTitle').textContent = item.title;
                document.getElementById('heroYear').textContent = item.year || '';
                document.getElementById('heroRating').textContent = item.contentRating || '';
                document.getElementById('heroDescription').textContent = (item.summary || '').substring(0, 200) + '...';
                
                // Fade in
                heroSection.style.opacity = '1';
            }, 500);
        }
        
        // Initialize first hero
        updateHero(0);
        
        // Rotate hero every 8 seconds
        setInterval(() => {
            currentHeroIndex = (currentHeroIndex + 1) % heroItems.length;
            updateHero(currentHeroIndex);
        }, 8000);
    </script>
    <?php endif; ?>

    <!-- MAIN CONTENT -->
    <div class="content">
        <!-- Alert Messages -->
        <?php if (!empty($_SESSION['settings_message'])) { 
            $cls = ($_SESSION['settings_status'] === 'success') ? 'alert-success' : 'alert-error';
        ?>
        <div id="alertMessage" style="padding: 0 40px; margin-bottom: 20px;">
            <div class="alert <?php echo $cls; ?>">
                <?php echo htmlspecialchars($_SESSION['settings_message']); ?>
                <button onclick="dismissAlert()" style="float: right; background: none; border: none; color: inherit; font-size: 20px; cursor: pointer; padding: 0 5px; opacity: 0.7;">&times;</button>
            </div>
        </div>
        <script>
            // Auto-dismiss alert after 3 seconds
            setTimeout(() => {
                const alert = document.getElementById('alertMessage');
                if (alert) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            }, 3000);
            
            function dismissAlert() {
                const alert = document.getElementById('alertMessage');
                if (alert) {
                    alert.style.transition = 'opacity 0.3s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }
            }
        </script>
        <?php unset($_SESSION['settings_message'], $_SESSION['settings_status']); } ?>

        <!-- HOME VIEW -->
        <div id="homeView" class="view-container">
            <!-- Top 20 Recently Added from All Libraries -->
            <?php if (!empty($top20Recent)) { ?>
            <div class="section">
                <h2 class="section-title">Top 20 Recently Added</h2>
                <div class="carousel">
                    <?php foreach ($top20Recent as $media) { ?>
                    <div class="media-card" onclick='showMediaModal(<?php echo json_encode($media); ?>)'>
                        <img src="<?php echo getImageUrl($media['thumb']); ?>" alt="<?php echo htmlspecialchars($media['title']); ?>">
                        <div class="media-card-overlay">
                            <div class="media-card-title"><?php echo htmlspecialchars($media['title']); ?></div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>

            <!-- All Selected Library Sections -->
            <?php if (!empty($mediaData)) { 
                foreach ($mediaData as $libKey => $lib) {
            ?>
            <div class="section">
                <h2 class="section-title">Recently Added <?php echo htmlspecialchars($lib['title']); ?></h2>
                <div class="carousel">
                    <?php foreach ($lib['items'] as $idx => $media) { ?>
                    <div class="media-card" onclick='showMediaModal(<?php echo json_encode($media); ?>)'>
                        <img src="<?php echo getImageUrl($media['thumb']); ?>" alt="<?php echo htmlspecialchars($media['title']); ?>">
                        <div class="media-card-overlay">
                            <div class="media-card-title"><?php echo htmlspecialchars($media['title']); ?></div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <?php } } ?>
        </div>

        <!-- MOVIES VIEW -->
        <div id="moviesView" class="view-container" style="display: none;">
            <?php if (!empty($movieLibraries)) { 
                foreach ($movieLibraries as $libKey => $lib) {
            ?>
            <div class="section">
                <h2 class="section-title"><?php echo htmlspecialchars($lib['title']); ?></h2>
                <div class="carousel">
                    <?php foreach ($lib['items'] as $media) { ?>
                    <div class="media-card" onclick='showMediaModal(<?php echo json_encode($media); ?>)'>
                        <img src="<?php echo getImageUrl($media['thumb']); ?>" alt="<?php echo htmlspecialchars($media['title']); ?>">
                        <div class="media-card-overlay">
                            <div class="media-card-title"><?php echo htmlspecialchars($media['title']); ?></div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <?php } } else { ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎬</div>
                <div class="empty-state-title">No Movie Libraries</div>
                <p>No movie libraries selected or available</p>
            </div>
            <?php } ?>
        </div>

        <!-- TV VIEW -->
        <div id="tvView" class="view-container" style="display: none;">
            <?php if (!empty($tvLibraries)) { 
                foreach ($tvLibraries as $libKey => $lib) {
            ?>
            <div class="section">
                <h2 class="section-title"><?php echo htmlspecialchars($lib['title']); ?></h2>
                <div class="carousel">
                    <?php foreach ($lib['items'] as $media) { ?>
                    <div class="media-card" onclick='showMediaModal(<?php echo json_encode($media); ?>)'>
                        <img src="<?php echo getImageUrl($media['thumb']); ?>" alt="<?php echo htmlspecialchars($media['title']); ?>">
                        <div class="media-card-overlay">
                            <div class="media-card-title"><?php echo htmlspecialchars($media['title']); ?></div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <?php } } else { ?>
            <div class="empty-state">
                <div class="empty-state-icon">📺</div>
                <div class="empty-state-title">No TV Libraries</div>
                <p>No TV libraries selected or available</p>
            </div>
            <?php } ?>
        </div>

        <!-- MUSIC VIEW -->
        <div id="musicView" class="view-container" style="display: none;">
            <?php if (!empty($musicLibraries)) { 
                foreach ($musicLibraries as $libKey => $lib) {
            ?>
            <div class="section">
                <h2 class="section-title"><?php echo htmlspecialchars($lib['title']); ?></h2>
                <div class="carousel">
                    <?php foreach ($lib['items'] as $media) { ?>
                    <div class="media-card" onclick='showMediaModal(<?php echo json_encode($media); ?>)'>
                        <img src="<?php echo getImageUrl($media['thumb']); ?>" alt="<?php echo htmlspecialchars($media['title']); ?>">
                        <div class="media-card-overlay">
                            <div class="media-card-title"><?php echo htmlspecialchars($media['title']); ?></div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <?php } } else { ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎵</div>
                <div class="empty-state-title">No Music Libraries</div>
                <p>No music libraries selected or available</p>
            </div>
            <?php } ?>
        </div>

        <!-- PHOTOS VIEW -->
        <div id="photosView" class="view-container" style="display: none;">
            <?php if (!empty($photoLibraries)) { 
                foreach ($photoLibraries as $libKey => $lib) {
            ?>
            <div class="section">
                <h2 class="section-title"><?php echo htmlspecialchars($lib['title']); ?></h2>
                <div class="carousel">
                    <?php foreach ($lib['items'] as $media) { ?>
                    <div class="media-card" onclick='showMediaModal(<?php echo json_encode($media); ?>)'>
                        <img src="<?php echo getImageUrl($media['thumb']); ?>" alt="<?php echo htmlspecialchars($media['title']); ?>">
                        <div class="media-card-overlay">
                            <div class="media-card-title"><?php echo htmlspecialchars($media['title']); ?></div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <?php } } else { ?>
            <div class="empty-state">
                <div class="empty-state-icon">📷</div>
                <div class="empty-state-title">No Photo Libraries</div>
                <p>No photo libraries selected or available</p>
            </div>
            <?php } ?>
        </div>

        <!-- TRENDING VIEW -->
        <div id="trendingView" class="view-container" style="display: none;">
            <?php if (!empty($trendingMedia)) { ?>
            <div class="section">
                <h2 class="section-title">Trending Now</h2>
                <div class="carousel">
                    <?php foreach ($trendingMedia as $media) { ?>
                    <div class="media-card" onclick='showMediaModal(<?php echo json_encode($media); ?>)'>
                        <img src="<?php echo getImageUrl($media['thumb']); ?>" alt="<?php echo htmlspecialchars($media['title']); ?>">
                        <div class="media-card-overlay">
                            <div class="media-card-title"><?php echo htmlspecialchars($media['title']); ?></div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <?php } else { ?>
            <div class="empty-state">
                <div class="empty-state-icon">📈</div>
                <div class="empty-state-title">No Trending Data</div>
                <p>Trending content will appear here</p>
            </div>
            <?php } ?>
        </div>

        <!-- Empty State (if no media at all) -->
        <?php if (empty($mediaData)) { ?>
        <div class="empty-state">
            <div class="empty-state-icon">🎬</div>
            <div class="empty-state-title">Configure Plex Server</div>
            <p>Click the Settings icon to add your Plex server and select libraries</p>
        </div>
        <?php } ?>
    </div>

    <!-- BOTTOM NAVIGATION -->
    <div class="navbar">
        <a class="nav-item active" onclick="switchView('home')" data-view="home">
            <span class="nav-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
            </span>
            <span>Home</span>
        </a>
        <a class="nav-item" onclick="switchView('movies')" data-view="movies">
            <span class="nav-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect>
                    <polyline points="17 2 12 7 7 2"></polyline>
                </svg>
            </span>
            <span>Movies</span>
        </a>
        <a class="nav-item" onclick="switchView('tv')" data-view="tv">
            <span class="nav-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect>
                    <polyline points="17 2 12 7 7 2"></polyline>
                </svg>
            </span>
            <span>TV</span>
        </a>
        <a class="nav-item" onclick="switchView('trending')" data-view="trending">
            <span class="nav-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                    <polyline points="17 6 23 6 23 12"></polyline>
                </svg>
            </span>
            <span>Trending</span>
        </a>
        <a class="nav-item" onclick="switchView('music')" data-view="music">
            <span class="nav-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 18V5l12-2v13"></path>
                    <circle cx="6" cy="18" r="3"></circle>
                    <circle cx="18" cy="16" r="3"></circle>
                </svg>
            </span>
            <span>Music</span>
        </a>
        <a class="nav-item" onclick="switchView('photos')" data-view="photos">
            <span class="nav-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                    <polyline points="21 15 16 10 5 21"></polyline>
                </svg>
            </span>
            <span>Photos</span>
        </a>
        <a class="nav-item" onclick="openSettings()">
            <span class="nav-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M12 1v6m0 6v6m4.22-13.78l-4.24 4.24m-3.96 3.96l-4.24 4.24M23 12h-6m-6 0H1m17.78 4.22l-4.24-4.24m-3.96-3.96l-4.24-4.24"></path>
                </svg>
            </span>
            <span>Settings</span>
        </a>
    </div>

    <!-- SETTINGS MODAL -->
    <div id="settingsModal" class="settings-modal">
        <div class="settings-content">
            <h2 class="settings-title">Settings</h2>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Plex Server Host</label>
                    <input type="text" class="form-input" name="host" placeholder="192.168.1.100:32400" value="<?php echo htmlspecialchars($host); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Plex Token</label>
                    <input type="text" class="form-input" name="token" placeholder="Your Plex token" value="<?php echo htmlspecialchars($token); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Use HTTPS</label>
                    <div class="checkbox-group">
                        <input type="checkbox" id="useSSL" name="useSSL" value="1" <?php echo $useSSL ? 'checked' : ''; ?>>
                        <label for="useSSL" style="margin: 0; cursor: pointer;">Enable SSL/HTTPS</label>
                    </div>
                </div>

                <?php if (!empty($allLibraries)) { ?>
                <div class="form-group">
                    <label class="form-label">Select Libraries to Display</label>
                    <?php foreach ($allLibraries as $lib) { ?>
                    <div class="checkbox-group" style="margin-bottom: 10px;">
                        <input type="checkbox" id="lib_<?php echo htmlspecialchars($lib['key']); ?>" name="selectedLibraries[]" value="<?php echo htmlspecialchars($lib['key']); ?>" <?php echo in_array($lib['key'], $selectedLibraries) ? 'checked' : ''; ?>>
                        <label for="lib_<?php echo htmlspecialchars($lib['key']); ?>" style="margin: 0; cursor: pointer;">
                            <?php echo htmlspecialchars($lib['title']); ?> (<?php echo htmlspecialchars($lib['type']); ?>)
                        </label>
                    </div>
                    <?php } ?>
                </div>
                <?php } else if (!empty($host) && !empty($token)) { ?>
                <div class="form-group">
                    <label class="form-label">Libraries</label>
                    <p style="color: #888; font-size: 13px;">Connect to Plex and save to select libraries</p>
                </div>
                <?php } ?>

                <!-- Overseerr Integration Section -->
                <div style="border-top: 1px solid #444; margin: 20px 0; padding-top: 20px;">
                    <h3 style="margin-top: 0; color: #ff8c00; font-size: 16px;">📋 Overseerr Integration (Optional)</h3>
                    <p style="color: #999; font-size: 12px;">Connect Overseerr to show media request status and availability</p>
                    
                    <div class="form-group">
                        <label class="form-label">Enable Overseerr</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="overseerrEnabled" name="overseerrEnabled" value="1" <?php echo $overseerrEnabled ? 'checked' : ''; ?>>
                            <label for="overseerrEnabled" style="margin: 0; cursor: pointer;">Use Overseerr</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Overseerr Host</label>
                        <input type="text" class="form-input" name="overseerrHost" placeholder="192.168.1.100:5055" value="<?php echo htmlspecialchars($overseerrHost); ?>">
                        <p style="color: #888; font-size: 12px;">IP/domain and port of your Overseerr instance</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Overseerr API Key</label>
                        <input type="text" class="form-input" name="overseerrToken" placeholder="Your Overseerr API key" value="<?php echo htmlspecialchars($overseerrToken); ?>">
                        <p style="color: #888; font-size: 12px;">Found in Overseerr Settings > API</p>
                    </div>
                </div>

                <!-- Tautulli Integration Section -->
                <div style="border-top: 1px solid #444; margin: 20px 0; padding-top: 20px;">
                    <h3 style="margin-top: 0; color: #ff8c00; font-size: 16px;">📊 Tautulli Integration (Optional)</h3>
                    <p style="color: #999; font-size: 12px;">Connect Tautulli to show watch statistics and play counts</p>
                    
                    <div class="form-group">
                        <label class="form-label">Enable Tautulli</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="tautulliEnabled" name="tautulliEnabled" value="1" <?php echo $tautulliEnabled ? 'checked' : ''; ?>>
                            <label for="tautulliEnabled" style="margin: 0; cursor: pointer;">Use Tautulli</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tautulli Host</label>
                        <input type="text" class="form-input" name="tautulliHost" placeholder="192.168.1.100:8181" value="<?php echo htmlspecialchars($tautulliHost); ?>">
                        <p style="color: #888; font-size: 12px;">IP/domain and port of your Tautulli instance</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tautulli API Key</label>
                        <input type="text" class="form-input" name="tautulliKey" placeholder="Your Tautulli API key" value="<?php echo htmlspecialchars($tautulliKey); ?>">
                        <p style="color: #888; font-size: 12px;">Found in Tautulli Settings > Web Interface > API</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">TMDB API Key</label>
                        <input type="text" class="form-input" name="tmdbApiKey" placeholder="Your TMDB API key" value="<?php echo htmlspecialchars($tmdbApiKey); ?>">
                        <p style="color: #888; font-size: 12px;">Required for Overseerr requests. Get one at <a href="https://www.themoviedb.org/settings/api" target="_blank" style="color: #b0a5ff;">themoviedb.org</a></p>
                    </div>
                </div>

                <div class="settings-buttons">
                    <button type="submit" name="settings_submit" value="1" class="btn btn-primary">Save Settings</button>
                    <button type="button" class="btn btn-secondary" onclick="closeSettings()">Close</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MEDIA DETAIL MODAL -->
    <div id="mediaModal" class="media-modal">
        <div class="media-modal-content">
            <button class="media-modal-close" onclick="closeMediaModal()">×</button>
            <div id="mediaModalHero" class="media-modal-hero"></div>
            <div class="media-modal-info">
                <h1 class="media-modal-title" id="modalTitle"></h1>
                <div class="media-modal-meta">
                    <span id="modalYear"></span>
                    <span id="modalRating"></span>
                    <span id="modalContentRating"></span>
                    <span id="modalDuration"></span>
                </div>
                <p class="media-modal-description" id="modalDescription"></p>
                
                <div class="media-modal-section" id="modalGenresSection">
                    <div class="media-modal-section-title">Genres</div>
                    <div class="media-modal-genres" id="modalGenres"></div>
                </div>
                
                <div class="media-modal-section" id="modalCastSection">
                    <div class="media-modal-section-title">Cast</div>
                    <div class="media-modal-cast" id="modalCast" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 15px; margin-top: 10px;"></div>
                </div>

                <div class="media-modal-section" id="tautulliStatsSection" style="display: none; border-top: 1px solid #444; padding-top: 15px;">
                    <div class="media-modal-section-title">📊 Watch Statistics (Tautulli)</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
                        <div style="background: #1a1a1a; padding: 10px; border-radius: 4px;">
                            <div style="color: #999; font-size: 12px;">Total Plays</div>
                            <div style="color: #ff8c00; font-size: 20px; font-weight: bold;" id="tautulliPlays">-</div>
                        </div>
                        <div style="background: #1a1a1a; padding: 10px; border-radius: 4px;">
                            <div style="color: #999; font-size: 12px;">Watch Count</div>
                            <div style="color: #ff8c00; font-size: 20px; font-weight: bold;" id="tautulliWatch">-</div>
                        </div>
                        <div style="background: #1a1a1a; padding: 10px; border-radius: 4px;">
                            <div style="color: #999; font-size: 12px;">Total Duration</div>
                            <div style="color: #ff8c00; font-size: 18px; font-weight: bold;" id="tautulliDuration">-</div>
                        </div>
                        <div style="background: #1a1a1a; padding: 10px; border-radius: 4px;">
                            <div style="color: #999; font-size: 12px;">Unique Viewers</div>
                            <div style="color: #ff8c00; font-size: 20px; font-weight: bold;" id="tautulliUsers">-</div>
                        </div>
                    </div>
                    <div id="tautulliLastWatched" style="margin-top: 12px; padding: 10px; background: #1a1a1a; border-radius: 4px; font-size: 13px; color: #ccc;">
                        Last watched: <span style="color: #ff8c00;">-</span>
                    </div>
                </div>

                <div class="media-modal-section" id="overseerrStatusSection" style="display: none; border-top: 1px solid #444; padding-top: 15px;">
                    <div class="media-modal-section-title">📋 Request Status (Overseerr)</div>
                    <div style="margin-top: 10px;">
                        <div style="background: #1a1a1a; padding: 12px; border-radius: 4px; margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="color: #999; font-size: 12px;">Status</div>
                                    <div id="overseerrStatusText" style="color: #ff8c00; font-weight: bold; font-size: 16px; margin-top: 4px;">-</div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="color: #999; font-size: 12px;">Requests</div>
                                    <div id="overseerrRequestCount" style="color: #ff8c00; font-weight: bold; font-size: 20px; margin-top: 4px;">0</div>
                                </div>
                            </div>
                        </div>
                        <div id="overseerrRequestDetails" style="display: none;">
                            <div style="background: #1a1a1a; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                                <div style="color: #999; font-size: 12px;">Last Requested By</div>
                                <div id="overseerrLastRequester" style="color: #e5e5e5; margin-top: 4px;">-</div>
                            </div>
                            <div style="background: #1a1a1a; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                                <div style="color: #999; font-size: 12px;">Request Date</div>
                                <div id="overseerrRequestDate" style="color: #e5e5e5; margin-top: 4px;">-</div>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                            <div style="background: #1a1a1a; padding: 10px; border-radius: 4px;">
                                <div style="color: #999; font-size: 12px;">Vote Score</div>
                                <div id="overseerrVote" style="color: #ff8c00; font-weight: bold; margin-top: 4px;">-</div>
                            </div>
                            <div style="background: #1a1a1a; padding: 10px; border-radius: 4px;">
                                <div style="color: #999; font-size: 12px;">Popularity</div>
                                <div id="overseerrPopularity" style="color: #ff8c00; font-weight: bold; margin-top: 4px;">-</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="media-modal-buttons">
                    <button class="btn btn-primary" id="playBtn">▶ Play</button>
                    <button class="btn btn-secondary" id="trailerBtn">🎬 Trailer</button>
                    <button class="btn btn-secondary" id="requestBtn" style="display: none;">📋 Request</button>
                    <div id="availableBadge" style="display: none; padding: 8px 16px; background: #28a745; color: white; border-radius: 4px; font-weight: bold; text-align: center;">✅ Available</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showMediaModal(media) {
            const modal = document.getElementById('mediaModal');
            
            // Update hero background
            const bgUrl = '<?php echo $http . "://" . $host; ?>/photo/:/transcode?url=' + encodeURIComponent(media.art || media.thumb) + '&width=1920&height=1080&X-Plex-Token=<?php echo $token; ?>';
            document.getElementById('mediaModalHero').style.backgroundImage = 'url(' + bgUrl + ')';
            
            // Update content
            document.getElementById('modalTitle').textContent = media.title;
            document.getElementById('modalYear').textContent = media.year || '';
            document.getElementById('modalRating').textContent = media.rating ? '⭐ ' + parseFloat(media.rating).toFixed(1) : '';
            document.getElementById('modalContentRating').textContent = media.contentRating || '';
            
            // Format duration
            if (media.duration) {
                const minutes = Math.floor(media.duration / 60000);
                const hours = Math.floor(minutes / 60);
                const mins = minutes % 60;
                document.getElementById('modalDuration').textContent = hours > 0 ? hours + 'h ' + mins + 'm' : mins + 'm';
            } else {
                document.getElementById('modalDuration').textContent = '';
            }
            
            document.getElementById('modalDescription').textContent = media.summary || 'No description available.';
            
            // Update genres
            const genresDiv = document.getElementById('modalGenres');
            genresDiv.innerHTML = '';
            if (media.genres && media.genres.length > 0) {
                media.genres.forEach(genre => {
                    const span = document.createElement('span');
                    span.className = 'media-genre-item';
                    span.textContent = genre;
                    genresDiv.appendChild(span);
                });
                document.getElementById('modalGenresSection').style.display = 'block';
            } else {
                document.getElementById('modalGenresSection').style.display = 'none';
            }
            
            // Update cast
            const castDiv = document.getElementById('modalCast');
            castDiv.innerHTML = '';
            if (media.cast && media.cast.length > 0) {
                media.cast.forEach((actor, idx) => {
                    const div = document.createElement('div');
                    div.style.cssText = 'text-align: center; cursor: pointer; transition: transform 0.2s;';
                    div.onmouseover = () => div.style.transform = 'scale(1.05)';
                    div.onmouseout = () => div.style.transform = 'scale(1)';
                    
                    // Create a placeholder image with actor initial
                    const initial = actor.charAt(0).toUpperCase();
                    const colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8'];
                    const bgColor = colors[idx % colors.length];
                    
                    const img = document.createElement('div');
                    img.style.cssText = `width: 100%; aspect-ratio: 3/4; background: ${bgColor}; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold; color: white; margin-bottom: 8px;`;
                    img.textContent = initial;
                    
                    const name = document.createElement('div');
                    name.style.cssText = 'font-size: 12px; color: #00bfff; word-break: break-word; overflow: hidden; text-overflow: ellipsis; cursor: pointer; text-decoration: underline;';
                    name.textContent = actor;
                    
                    // Click to search for actor
                    div.onclick = () => {
                        const searchQuery = encodeURIComponent(actor + ' filmography');
                        window.open('https://www.imdb.com/find?q=' + searchQuery, '_blank');
                    };
                    
                    div.appendChild(img);
                    div.appendChild(name);
                    castDiv.appendChild(div);
                });
                document.getElementById('modalCastSection').style.display = 'block';
            } else {
                document.getElementById('modalCastSection').style.display = 'none';
            }
            
            // Play button (redirect to Plex)
            const playBtn = document.getElementById('playBtn');
            playBtn.onclick = () => {
                if (media.ratingKey) {
                    const plexUrl = '<?php echo $http . "://" . $host; ?>/web/index.html#!/server/<?php echo $host; ?>/details?key=/library/metadata/' + media.ratingKey;
                    window.location.href = plexUrl;
                }
            };
            
            // Trailer button (search YouTube)
            const trailerBtn = document.getElementById('trailerBtn');
            trailerBtn.onclick = () => {
                const searchQuery = encodeURIComponent(media.title + ' ' + (media.year || '') + ' trailer');
                window.open('https://www.youtube.com/results?search_query=' + searchQuery, '_blank');
            };
            
            // Request button (Overseerr)
            const overseerrEnabledForRequest = <?php echo $overseerrEnabled ? 'true' : 'false'; ?>;
            const requestBtn = document.getElementById('requestBtn');
            const availableBadge = document.getElementById('availableBadge');
            if (overseerrEnabledForRequest) {
                requestBtn.style.display = 'block';
                availableBadge.style.display = 'none';
                requestBtn.onclick = () => {
                    requestBtn.disabled = true;
                    requestBtn.textContent = '⏳ Requesting...';
                    
                    fetch('?action=requestMedia', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            title: media.title,
                            type: media.type,
                            year: media.year,
                            tmdbId: media.tmdbId || ''
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            requestBtn.textContent = '✅ Requested!';
                            requestBtn.style.backgroundColor = '#28a745';
                        } else {
                            requestBtn.textContent = '❌ Request Failed';
                            requestBtn.style.backgroundColor = '#dc3545';
                            setTimeout(() => {
                                requestBtn.textContent = '📋 Request';
                                requestBtn.style.backgroundColor = '';
                                requestBtn.disabled = false;
                            }, 3000);
                        }
                    })
                    .catch(e => {
                        console.log('Request failed:', e);
                        requestBtn.textContent = '📋 Request';
                        requestBtn.disabled = false;
                    });
                };
            }
            
            // Fetch Overseerr status
            const overseerrEnabled = <?php echo $overseerrEnabled ? 'true' : 'false'; ?>;
            if (overseerrEnabled && media.key) {
                fetch('?action=getOverseerrStatus&key=' + media.key + '&type=' + media.type + '&tmdbId=' + (media.tmdbId || ''))
                    .then(r => r.json())
                    .then(data => {
                        if (data.status && data.status !== 'unknown') {
                            document.getElementById('overseerrStatusSection').style.display = 'block';
                            
                            // Status text with emoji
                            const statusEmoji = {
                                'available': '✅',
                                'pending': '⏳',
                                'processing': '⚙️'
                            };
                            const statusText = (statusEmoji[data.status] || '❓') + ' ' + 
                                data.status.charAt(0).toUpperCase() + data.status.slice(1);
                            document.getElementById('overseerrStatusText').textContent = statusText;
                            
                            // Disable request button and show available badge if media is available
                            if (data.status === 'available') {
                                requestBtn.style.display = 'none';
                                availableBadge.style.display = 'block';
                            }
                            
                            // Request details
                            document.getElementById('overseerrRequestCount').textContent = data.requestCount || 0;
                            if ((data.requestCount || 0) > 0) {
                                document.getElementById('overseerrRequestDetails').style.display = 'block';
                                document.getElementById('overseerrLastRequester').textContent = data.lastRequester || 'Unknown';
                                document.getElementById('overseerrRequestDate').textContent = data.requestDate || 'Unknown';
                            } else {
                                document.getElementById('overseerrRequestDetails').style.display = 'none';
                            }
                            
                            // Vote and popularity
                            document.getElementById('overseerrVote').textContent = data.voteScore || 'N/A';
                            document.getElementById('overseerrPopularity').textContent = data.popularity || 'N/A';
                        }
                    })
                    .catch(e => console.log('Overseerr fetch failed'));
            } else {
                document.getElementById('overseerrStatusSection').style.display = 'none';
            }
            
            // Fetch Tautulli stats
            const tautulliEnabled = <?php echo $tautulliEnabled ? 'true' : 'false'; ?>;
            if (tautulliEnabled && media.key) {
                fetch('?action=getTautulliStats&key=' + media.key)
                    .then(r => r.json())
                    .then(data => {
                        if (data.plays !== undefined) {
                            document.getElementById('tautulliStatsSection').style.display = 'block';
                            document.getElementById('tautulliPlays').textContent = data.plays || '0';
                            document.getElementById('tautulliWatch').textContent = data.watchCount || '0';
                            document.getElementById('tautulliDuration').textContent = data.totalDuration || '0m';
                            document.getElementById('tautulliUsers').textContent = data.userCount || '0';
                            
                            // Last watched date
                            if (data.lastWatched && data.lastWatched > 0) {
                                const lastWatchedDate = new Date(data.lastWatched * 1000);
                                const dateStr = lastWatchedDate.toLocaleDateString('en-US', { 
                                    month: 'short', 
                                    day: 'numeric', 
                                    year: 'numeric'
                                });
                                document.getElementById('tautulliLastWatched').innerHTML = 
                                    'Last watched: <span style="color: #ff8c00;">' + dateStr + '</span>';
                            }
                        }
                    })
                    .catch(e => console.log('Tautulli fetch failed'));
            } else {
                document.getElementById('tautulliStatsSection').style.display = 'none';
            }
            
            modal.classList.add('show');
        }
        
        function closeMediaModal() {
            document.getElementById('mediaModal').classList.remove('show');
        }
        
        function showMediaDetails(heroIndex) {
            if (heroItems && heroItems[heroIndex]) {
                showMediaModal(heroItems[heroIndex]);
            }
        }
        
        // Close modal when clicking outside
        document.getElementById('mediaModal').addEventListener('click', (e) => {
            if (e.target.id === 'mediaModal') closeMediaModal();
        });

        function openSettings() {
            document.getElementById('settingsModal').classList.add('show');
        }

        function closeSettings() {
            document.getElementById('settingsModal').classList.remove('show');
        }

        function switchView(viewName) {
            // Hide all views
            const views = ['homeView', 'moviesView', 'tvView', 'trendingView', 'musicView', 'photosView'];
            views.forEach(v => {
                document.getElementById(v).style.display = 'none';
            });
            
            // Show selected view
            document.getElementById(viewName + 'View').style.display = 'block';
            
            // Update active nav item
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector('.nav-item[data-view="' + viewName + '"]').classList.add('active');
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        document.getElementById('settingsModal').addEventListener('click', (e) => {
            if (e.target.id === 'settingsModal') closeSettings();
        });

        // Smooth scrolling for nav items
        document.querySelectorAll('.nav-item[href^="#"]').forEach(link => {
            link.addEventListener('click', (e) => {
                const target = document.querySelector(e.target.closest('.nav-item').getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>
</html>
