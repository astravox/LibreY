<?php
require_once "misc/cooldowns.php";

abstract class EngineRequest {
    protected $url, $query, $page, $opts, $mh, $ch;
    protected $DO_CACHING = true;

    public function __construct($opts, $mh) {
        $this->query = $opts->query;
        $this->page = $opts->page;
        $this->mh = $mh;
        $this->opts = $opts;

        $this->url = $this->get_request_url();
        if (!$this->url || has_cached_results($this->url)) return;

        $this->ch = curl_init($this->url);

        // Use optimized cURL options for faster processing
        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,  // Limit timeout for faster failure detection
            CURLOPT_CONNECTTIMEOUT => 5,  // Reduce connection timeout
            CURLOPT_FOLLOWLOCATION => true,  // Follow redirects
            CURLOPT_FORBID_REUSE => false,  // Reuse connections when possible
            CURLOPT_FRESH_CONNECT => false  // Avoid creating new connections unnecessarily
        ] + ($opts->curl_settings ?? []));

        if ($mh) curl_multi_add_handle($mh, $this->ch);
    }

    public function get_request_url() {
        return "";
    }

    public function successful() {
        return (isset($this->ch) && curl_getinfo($this->ch, CURLINFO_HTTP_CODE) == 200) || has_cached_results($this->url);
    }

    abstract function parse_results($response);

    public function get_results() {
        if (!isset($this->url)) return $this->parse_results(null);
        if ($this->DO_CACHING && has_cached_results($this->url)) return $this->filter_duplicate_domains(fetch_cached_results($this->url));

        $response = $this->mh ? curl_multi_getcontent($this->ch) : curl_exec($this->ch);
        $results = $this->parse_results($response) ?? [];

        if ($this->DO_CACHING && !empty($results)) {
            store_cached_results($this->url, $results, $this->opts->cache_time * 60);
        }

        return $this->filter_duplicate_domains($results);
    }

    // Optimize domain filtering by using an associative array
    private function filter_duplicate_domains($results) {
        $seen_domains = [];
        $filtered_results = [];
    
        foreach ($results as $result) {
            // Ensure 'url' key exists and is not empty
            if (!isset($result['url']) || empty($result['url'])) {
                continue;  // Skip this result if no URL is available
            }
    
            $domain = parse_url($result['url'], PHP_URL_HOST);
            if ($domain && !isset($seen_domains[$domain])) {
                $seen_domains[$domain] = true;
                $filtered_results[] = $result;
            }
        }
    
        return $filtered_results;
    }
    

    public static function print_results($results, $opts) {}
}

function load_opts() {
    $opts = $GLOBALS['opts'] ?? require_once "config.php";

    $opts->request_cooldown ??= 25;
    $opts->cache_time ??= 25;
    $opts->query = trim($_REQUEST["q"] ?? "");
    $opts->type = (int) ($_REQUEST["t"] ?? 0);
    $opts->page = (int) ($_REQUEST["p"] ?? 0);
    $opts->theme = $_REQUEST["theme"] ?? trim(htmlspecialchars($_COOKIE["theme"] ?? $opts->default_theme ?? "dark"));
    $opts->safe_search = (int) ($_REQUEST["safe"] ?? 0) == 1 || isset($_COOKIE["safe_search"]);
    $opts->disable_special = (int) ($_REQUEST["ns"] ?? 0) == 1 || isset($_COOKIE["disable_special"]);
    $opts->disable_frontends = (int) ($_REQUEST["nf"] ?? 0) == 1 || isset($_COOKIE["disable_frontends"]);
    $opts->language = $_REQUEST["lang"] ?? trim(htmlspecialchars($_COOKIE["language"] ?? $opts->language ?? "en"));
    $opts->do_fallback = (int) ($_REQUEST["nfb"] ?? 0) == 0 && $opts->instance_fallback;
    $opts->curl_settings[CURLOPT_FOLLOWLOCATION] ??= true;
    $opts->engine = $_REQUEST["engine"] ?? $_COOKIE["engine"] ?? $opts->preferred_engines["text"] ?? "auto";

    foreach (array_keys($opts->frontends ?? []) as $frontend) {
        $opts->frontends[$frontend]["instance_url"] = $_COOKIE[$frontend] ?? $opts->frontends[$frontend]["instance_url"];
    }

    return $opts;
}

function opts_to_params($opts) {
    return http_build_query([
        'p' => $opts->page,
        'q' => urlencode($opts->query),
        't' => $opts->type,
        'safe' => $opts->safe_search ? 1 : 0,
        'nf' => $opts->disable_frontends ? 1 : 0,
        'ns' => $opts->disable_special ? 1 : 0,
        'engine' => $opts->engine ?? 'auto'
    ]);
}

function init_search($opts, $mh) {
    switch ($opts->type) {
        case 1:
            require_once "engines/qwant/image.php";
            return new QwantImageSearch($opts, $mh);
        case 2:
            require_once "engines/invidious/video.php";
            return new VideoSearch($opts, $mh);
        case 3:
            if ($opts->disable_bittorrent_search) {
                echo "<p class='text-result-container'>" . TEXTS["feature_disabled"] . "</p>";
                return;
            }
            require_once "engines/bittorrent/merge.php";
            return new TorrentSearch($opts, $mh);
        case 4:
            if ($opts->disable_hidden_service_search) {
                echo "<p class='text-result-container'>" . TEXTS["feature_disabled"] . "</p>";
                return;
            }
            require_once "engines/ahmia/hidden_service.php";
            return new TorSearch($opts, $mh);
        case 5:
            require_once "engines/maps/openstreetmap.php";
            return new OSMRequest($opts, $mh);
        default:
            require_once "engines/text/text.php";
            return new TextSearch($opts, $mh);
    }
}

function fetch_search_results($opts, $do_print) {
    $opts->cooldowns = load_cooldowns();
    $mh = curl_multi_init();
    $search_category = init_search($opts, $mh);
    $running = null;

    // Optimize cURL multi-execution for less CPU load
    do {
        curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh);  // Reduce polling frequency and prevent CPU overload
    } while ($running);

    $results = $search_category->get_results();

    if (empty($results)) {
        require_once "engines/librex/fallback.php";
        $results = get_librex_results($opts);
    }

    if (!$do_print || empty($results)) return $results;

    print_elapsed_time(microtime(true), $results, $opts);
    $search_category->print_results($results, $opts);

    return $results;
}
?>
