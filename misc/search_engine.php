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
            CURLOPT_TIMEOUT => 20,  // Increase the timeout
            CURLOPT_CONNECTTIMEOUT => 10,  // Increase connection timeout
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FORBID_REUSE => false,
            CURLOPT_FRESH_CONNECT => false,
            // Simulate a real browser
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Cache-Control: no-cache',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
            ],
            // Save cookies for session handling
            CURLOPT_COOKIEJAR => '/tmp/cookies.txt',
            CURLOPT_COOKIEFILE => '/tmp/cookies.txt',
        ]);
        
        

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

        // Error handling for cURL response
        if (curl_errno($this->ch)) {
            error_log('cURL error: ' . curl_error($this->ch));
            return [];  // Return empty results in case of cURL error
        }

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

    $opts->request_cooldown = $opts->request_cooldown ?? 25;
    $opts->cache_time = $opts->cache_time ?? 25;
    $opts->query = trim($_REQUEST["q"] ?? "");
    $opts->type = (int) ($_REQUEST["t"] ?? 0);
    $opts->page = (int) ($_REQUEST["p"] ?? 0);
    $opts->theme = $_REQUEST["theme"] ?? htmlspecialchars($_COOKIE["theme"] ?? $opts->default_theme ?? "dark");
    $opts->safe_search = (int) ($_REQUEST["safe"] ?? 0) == 1 || isset($_COOKIE["safe_search"]);
    $opts->disable_special = (int) ($_REQUEST["ns"] ?? 0) == 1 || isset($_COOKIE["disable_special"]);
    $opts->disable_frontends = (int) ($_REQUEST["nf"] ?? 0) == 1 || isset($_COOKIE["disable_frontends"]);
    $opts->language = $_REQUEST["lang"] ?? htmlspecialchars($_COOKIE["language"] ?? $opts->language ?? "en");
    $opts->curl_settings[CURLOPT_FOLLOWLOCATION] = $opts->curl_settings[CURLOPT_FOLLOWLOCATION] ?? true;
    $opts->engine = $_REQUEST["engine"] ?? $_COOKIE["engine"] ?? $opts->preferred_engines["text"] ?? "auto";

    foreach ($opts->frontends ?? [] as $frontend => $details) {
        $opts->frontends[$frontend]["instance_url"] = $_COOKIE[$frontend] ?? $details["instance_url"];
    }

    return $opts;
}

function opts_to_params($opts) {
    $params = [
        'p' => $opts->page,
        'q' => $opts->query ? urlencode($opts->query) : '',
        't' => $opts->type,
        'safe' => $opts->safe_search ? 1 : 0,
        'nf' => $opts->disable_frontends ? 1 : 0,
        'ns' => $opts->disable_special ? 1 : 0,
        'engine' => $opts->engine ?? 'auto'
    ];

    return http_build_query(array_filter($params, fn($v) => $v !== null && $v !== ''));
}

function init_search($opts, $mh) {
    $engines = [
        1 => 'engines/qwant/image.php',
        2 => 'engines/invidious/video.php',
        3 => 'engines/bittorrent/merge.php',
        4 => 'engines/ahmia/hidden_service.php',
        5 => 'engines/maps/openstreetmap.php',
        'default' => 'engines/text/text.php'
    ];

    $engine_class_map = [
        1 => 'QwantImageSearch',
        2 => 'VideoSearch',
        3 => 'TorrentSearch',
        4 => 'TorSearch',
        5 => 'OSMRequest',
        'default' => 'TextSearch'
    ];

    $engine_type = $opts->type;
    require_once $engines[$engine_type] ?? $engines['default'];
    $class_name = $engine_class_map[$engine_type] ?? $engine_class_map['default'];

    return new $class_name($opts, $mh);
}

function execute_curl_multi($mh, $timeout = 30) {
    $start_time = microtime(true);
    $running = null;

    do {
        curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh);  // Reduce CPU overload
        if ((microtime(true) - $start_time) > $timeout) break;  // Timeout handling
    } while ($running);
}

function fetch_search_results($opts, $do_print) {
    $opts->cooldowns = load_cooldowns();
    $mh = curl_multi_init();
    $search_category = init_search($opts, $mh);

    execute_curl_multi($mh);

    $results = $search_category->get_results();

    if (!$do_print || empty($results)) return $results;

    print_elapsed_time(microtime(true), $results, $opts);
    $search_category->print_results($results, $opts);

    return $results;
}
?>
