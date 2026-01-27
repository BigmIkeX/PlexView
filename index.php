<?php
session_start();
#CHANGE THESE HERE (defaults if no settings saved)
$name = "Cause FX"; //your name here :)
$useSSL = true; //Use SSL?
$host = ""; //Plex server host:port (no protocol)
$token = ""; //Plex Token
$movies = "1"; //Library Section for Movies
$tv = "2"; //Library Section for TV Shows

// Load saved settings from config.json if available
$configPath = __DIR__ . '/assets/config/config.json';
if (file_exists($configPath)) {
    $cfg = json_decode(@file_get_contents($configPath), true);
    if (is_array($cfg)) {
        $name = isset($cfg['name']) ? $cfg['name'] : $name;
        $useSSL = isset($cfg['useSSL']) ? !!$cfg['useSSL'] : $useSSL;
        $host = isset($cfg['host']) ? $cfg['host'] : $host;
        $token = isset($cfg['token']) ? $cfg['token'] : $token;
        $movies = isset($cfg['movies']) ? (string)$cfg['movies'] : $movies;
        $tv = isset($cfg['tv']) ? (string)$cfg['tv'] : $tv;
    }
}

// Handle settings save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings_submit'])) {
    $name = trim($_POST['name'] ?? $name);
    $useSSL = isset($_POST['useSSL']) && $_POST['useSSL'] === '1';
    $host = trim($_POST['host'] ?? $host);
    $token = trim($_POST['token'] ?? $token);
    $movies = trim($_POST['movies'] ?? $movies);
    $tv = trim($_POST['tv'] ?? $tv);
    // Basic connectivity check before saving
    $httpTmp = $useSSL ? 'https' : 'http';
    $probeOk = false;
    if (!empty($host) && !empty($token)) {
        $probeUrl = "$httpTmp://$host/library/sections?X-Plex-Token=$token";
        $probeXml = @simplexml_load_file($probeUrl);
        if ($probeXml !== false) { $probeOk = true; }
    }
    if ($probeOk) {
        $save = [
            'name' => $name,
            'useSSL' => $useSSL,
            'host' => $host,
            'token' => $token,
            'movies' => $movies,
            'tv' => $tv
        ];
        @mkdir(__DIR__ . '/assets/config', 0777, true);
        @file_put_contents($configPath, json_encode($save, JSON_PRETTY_PRINT));
        $_SESSION['settings_status'] = 'success';
        $_SESSION['settings_message'] = 'Settings saved. Connection to Plex verified.';
    } else {
        $_SESSION['settings_status'] = 'error';
        $_SESSION['settings_message'] = 'Could not verify connection to Plex. Check host and token. Settings were not saved.';
    }
}

//DONT CHANGE THESE PARAMETERS
ini_set('display_errors',1);  error_reporting(E_ALL);
if($useSSL == true){ $http = "https"; }else{ $http = "http"; }
$act = isset($_GET['act']) ? $_GET['act'] : 'recentlyAdded';
$type = isset($_GET['type']) ? $_GET['type'] : 'movie';
$section = ($type == "movie") ? $movies : $tv;
$typeselect = ($type == "movie") ? "Movies" : "TV Shows";
$parent = ($act == "all" && $type == "tv") ? "Directory" : "Video";
// Build URLs only if configured
$achxml = false;
if (!empty($host) && !empty($token)) {
    $url = "$http://$host/library/sections/$section/$act?X-Plex-Token=$token";
    $imgurl = "$http://$host/photo/:/transcode?url=";
    $imgurlend = "&width=100&height=100&X-Plex-Token=$token";
    $imgurlendhq = "&width=300&height=300&X-Plex-Token=$token";
    $achxml = @simplexml_load_file($url);
} else {
    $imgurl = '';
    $imgurlend = '';
    $imgurlendhq = '';
}

$actarray = array
(
    array("newest","all","recentlyAdded", "recentlyViewed"),
    array("Newest Released $typeselect","All $typeselect","Recently Added $typeselect", "Recently Viewed $typeselect")
);

$title = $actarray[1][array_search($act, $actarray[0])];

if (in_array($act, $actarray[0])) {unset($actarray[0] [array_search($act,$actarray[0] )]);}

?>

<!doctype html>
<html><head>
    <meta charset="utf-8">
    <title><?=$name;?>'s Plex Library</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">

    <!-- Le styles -->
    <link href="assets/css/bootstrap.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <!-- DATA TABLE CSS -->
    <link href="assets/css/table.css" rel="stylesheet">



    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>

    <style type="text/css">
        body {
            padding-top: 60px;
        }
    </style>

    <!-- Le HTML5 shim, for IE6-8 support of HTML5 elements -->
    <!--[if lt IE 9]>
    <script src="https://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->

    <!-- Le fav and touch icons -->
    <link rel="shortcut icon" href="assets/ico/favicon.ico">
    <link rel="apple-touch-icon-precomposed" sizes="144x144" href="assets/ico/apple-touch-icon-144-precomposed.png">
    <link rel="apple-touch-icon-precomposed" sizes="114x114" href="assets/ico/apple-touch-icon-114-precomposed.png">
    <link rel="apple-touch-icon-precomposed" sizes="72x72" href="assets/ico/apple-touch-icon-72-precomposed.png">
    <link rel="apple-touch-icon-precomposed" href="assets/ico/apple-touch-icon-57-precomposed.png">

    <!-- Google Fonts call. Font Used Open Sans -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans" rel="stylesheet" type="text/css">

    <!-- DataTables Initialization -->
    <script type="text/javascript" src="assets/js/datatables/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf-8">
        $(document).ready(function() {
            $('#dt1').dataTable({
                "bAutoWidth": true,
                "aaSorting": []
            });
        } );
    </script>


</head>
<body>

<!-- NAVIGATION MENU -->

<div class="navbar-nav navbar-inverse navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                <span class="icon-bar">1</span>
                <span class="icon-bar">2</span>
                <span class="icon-bar">3</span>
            </button>
            <a class="navbar-brand" href="<?=basename(__FILE__);?>"><img src="assets/img/logo30.png" alt=""><?=$name;?>'s Plex Library</a>
            <a class="btn btnnew" style="margin-left:10px" href="#settingsModal" data-toggle="modal">Settings</a>
        </div>
    </div>
</div>

<div class="container">
    <?php if (!empty($_SESSION['settings_message'])) { $cls = ($_SESSION['settings_status'] === 'success') ? 'alert-success' : 'alert-danger'; ?>
    <div class="alert <?=$cls?>" style="margin-top:10px"><?=htmlspecialchars($_SESSION['settings_message'])?></div>
    <?php unset($_SESSION['settings_message'], $_SESSION['settings_status']); } ?>

    <div class="row">
        <div class="col-sm-12 col-lg-12">
            <h4><?php if($type == "movie"){ echo "Movies | <a href='?act=$act&type=tv' class='xphunk-link'>TV Shows</a>"; }else{ echo "<a href='?act=$act&type=movie' class='xphunk-link'>Movies</a> | TV Shows"; } ?> </h4>
            <h4><strong><?=$title;?> </strong></h4>
            <?php foreach ($actarray[0] as $action) {
                $linktitle = $actarray[1][array_search($action, $actarray[0])];
                echo '<h4 class="switch-link"><strong><a href="?act='.$action.'&type='.$type.'" class="xphunk-link">Switch to '.ucfirst($linktitle).'</a></strong></h4>';
            }?>


            <table class="display" id="dt1">
                <thead>
                <tr>
                    <th>Poster</th>
                    <th><?=substr($typeselect, 0, -1);?> Name</th>
                    <th style="<?=($type == "movie") ? 'display:none;' : '';?>">Episode Name</th>
                    <th>Quality</th>
                    <th>Release Date</th>
                    <th>Rating</th>
                    <th>Content Rating</th>
                </tr>
                </thead>
                <tbody>

                <!-- CONTENT -->
                <?php if ($achxml && isset($achxml->$parent)) { foreach($achxml->$parent AS $child) {
                    // Handle different XML structures: Directory (TV all) vs Video (episodes/movies)
                    $isDirectory = ($act == "all" && $type == "tv");
                    $modalId = $isDirectory ? $child['ratingKey'] : $child->Media['id'];
                    $trueimage = $isDirectory ? $child['thumb'] : (($type == "movie") ? $child['thumb'] : $child['grandparentThumb']);
                    $showTitle = $isDirectory ? $child['title'] : (($type == "tv") ? $child['grandparentTitle'] : $child['title']);
                    $episodeTitle = ($type == "tv" && !$isDirectory) ? $child['title'] : '';
                    $videoRes = $isDirectory ? 'N/A' : strtoupper($child->Media['videoResolution']);
                    
                    echo '<tr class="gradeA">';
                    echo '<td><center><a href="#myModal'.$modalId.'" data-toggle="modal"><img src="'.$imgurl.$trueimage.$imgurlend.'"></a></center></td>';
                    echo '<td>'.$showTitle.'</td>';
                    echo '<td style="'.($type == "movie" ? 'display:none;' : '').'">'.htmlspecialchars($episodeTitle).'</td>';
                    echo '<td>'.$videoRes.'</td>';
                    echo '<td>'.$child['originallyAvailableAt'].'</td>';
                    echo '<td>'.$child['rating'].'</td>';
                    echo '<td>'.$child['contentRating'].'</td>';
                    echo '</tr>';
                } } else { echo '<tr><td colspan="7" style="text-align:center">Configure your Plex server in Settings to load data.</td></tr>'; }?>

                </tbody>
            </table><!--/END SECOND TABLE -->
            <?php if ($achxml && isset($achxml->$parent)) { foreach($achxml->$parent AS $child) {
                $isDirectory = ($act == "all" && $type == "tv");
                $modalId = $isDirectory ? $child['ratingKey'] : $child->Media['id'];
            ?>
                <div class="modal fade" id="myModal<?=$modalId;?>" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="display: none;">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">X</button>
                                <h4 class="modal-title"><?=$child['title'];?></h4>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-lg-5">
                                        <div class="cont3">
                                            <img src="<?=$imgurl.$child['thumb'].$imgurlendhq;?>">
                                        </div>
                                    </div>
                                    <div class="col-lg-7">
                                        <div class="cont3">
                                            <?php if(!$child['tagline']){ echo "<h6>".$child['summary']."</h6>";
                                            }else{ echo "<h4><strong>".$child['tagline']."</strong></h4><h6>".$child['summary']."</h6>";}?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <div class="row">
                                    <div class="col-lg-3">
                                        <div class="cont3">
                                            <?php if($child->Media['container']){ echo "<p><ok>Containter:</ok><pre><center>".$child->Media['container']."</center></pre></p>";}?>
                                            <?php if($child->Media['duration']){ echo "<p><ok>Duration:</ok><pre><center>".$child->Media['duration']."</center></pre></p>";}?>
                                            <?php if($child->Media['videoFrameRate']){ echo "<p><ok>Framerate:</ok><pre><center>".$child->Media['videoFrameRate']."</center></pre></p>";}?>
                                            <?php //if($child['addedAt']){ echo "<p><ok>Date Added:</ok><pre><center>".date('M/d/Y', $child['addedAt']/1000)."</center></pre></p>";}?>
                                            <?php if($child['viewCount']){ echo "<p><ok>Times Played:</ok><pre><center>".$child['viewCount']."</center></pre></p>";}?>


                                        </div>
                                    </div>
                                    <div class="col-lg-3">
                                        <div class="cont3">
                                            <?php if($child->Media['width']){ echo "<p><ok>Width:</ok><pre><center>".$child->Media['width']."</center></pre></p>";}?>
                                            <?php if($child->Media['height']){ echo "<p><ok>Height:</ok><pre><center>".$child->Media['height']."</center></pre></p>";}?>
                                            <?php if($child->Media['aspectRatio']){ echo "<p><ok>Aspect Ratio:</ok><pre><center>".$child->Media['aspectRatio']."</center></pre></p>";}?>
                                            <?php if($child->Media['videoCodec']){ echo "<p><ok>Video Codec:</ok><pre><center>".$child->Media['videoCodec']."</center></pre></p>";}?>

                                        </div>
                                    </div>
                                    <div class="col-lg-3">
                                        <div class="cont3">
                                            <?php if($child->Media['audioChannels']){ echo "<p><ok>Audio Channels:</ok><pre><center>".$child->Media['audioChannels']."</center></pre></p>";}?>
                                            <?php if($child->Media['audioCodec']){ echo "<p><ok>Audio Codec:</ok><pre><center>".$child->Media['audioCodec']."</center></pre></p>";}?>
                                            <?php if($child->Media['audioProfile']){ echo "<p><ok>Audio Profile:</ok><pre><center>".$child->Media['audioProfile']."</center></pre></p>";}?>

                                        </div>
                                    </div>
                                    <div class="col-lg-3">
                                        <div class="cont3">
                                            <p><ok>Genre(s):</ok><?php foreach ($child->Genre AS $genre){ echo "<pre word-break='break-word'><center>".$genre['tag']."</center></pre>";}?></p>




                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            </div>
                        </div><!-- /.modal-content -->
                    </div><!-- /.modal-dialog -->
                </div>
            <?php } } ?>

        </div><!--/span12 -->
    </div><!-- /row -->

</div> <!-- /container -->
<br>

<br>
<?php if (empty($host) || empty($token)) { ?>
<div class="container">
    <div class="alert alert-warning" style="margin-top:10px">Plex server is not configured. Open <a href="#settingsModal" data-toggle="modal">Settings</a> to add your server details.</div>
    </div>
<?php } ?>
<!-- FOOTER -->
<div id="footerwrap">
    <footer class="clearfix"></footer>
    <div class="container">
        <div class="row">
            <div class="col-sm-12 col-lg-12">
                <p><img src="assets/img/logo.png" alt=""></p>
                <p>Blocks Dashboard Theme - Crafted With Love - Copyright 2013</p>
            </div>

        </div><!-- /row -->
    </div><!-- /container -->
</div><!-- /footerwrap -->


<!-- Le javascript
================================================== -->
<!-- Placed at the end of the document so the pages load faster -->
<script type="text/javascript" src="assets/js/bootstrap.js"></script>
<script type="text/javascript" src="assets/js/admin.js"></script>

<!-- Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1" role="dialog" aria-labelledby="settingsLabel" aria-hidden="true" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">X</button>
                <h4 class="modal-title" id="settingsLabel">Settings</h4>
            </div>
            <form method="post">
            <div class="modal-body">
                <div class="form-group">
                    <label>Display Name</label>
                    <input type="text" name="name" class="form-control" value="<?=htmlspecialchars($name)?>" />
                </div>
                <div class="form-group">
                    <label>Use SSL (HTTPS)</label>
                    <select name="useSSL" class="form-control">
                        <option value="1" <?=($useSSL ? 'selected' : '')?>>Yes</option>
                        <option value="0" <?=(!$useSSL ? 'selected' : '')?>>No</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Plex Host (e.g. 192.168.1.10:32400)</label>
                    <input type="text" name="host" class="form-control" value="<?=htmlspecialchars($host)?>" />
                </div>
                <div class="form-group">
                    <label>Plex Token</label>
                    <input type="text" name="token" class="form-control" value="<?=htmlspecialchars($token)?>" />
                </div>
                <div class="form-group">
                    <label>Movies Section ID</label>
                    <input type="text" name="movies" class="form-control" value="<?=htmlspecialchars($movies)?>" />
                </div>
                <div class="form-group">
                    <label>TV Section ID</label>
                    <input type="text" name="tv" class="form-control" value="<?=htmlspecialchars($tv)?>" />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" name="settings_submit" value="1" class="btn btn-primary">Save Settings</button>
            </div>
            </form>
        </div>
    </div>
</div>


</body></html>