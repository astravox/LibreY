<?php
    function get_engines() {
        // Only return Google and Brave temporarily
        return array("google", "brave");
    }

    class TextSearch extends EngineRequest {
        protected $cache_key, $engine, $engines, $engine_request, $special_request;

        public function __construct($opts, $mh) {
            $this->engines = get_engines();
            shuffle($this->engines);  // Randomize engine selection

            // Ensure $opts is not null and has defaults for 'page' and 'language'
            $this->opts = $opts ?? new stdClass();
            $this->opts->page = $this->opts->page ?? 0;  // Default to page 0 if not set
            $this->opts->language = $this->opts->language ?? 'en';  // Default to 'en' if not set

            $this->query = $this->opts->query;
            $this->cache_key = "text:" . $this->query . "p" . $this->opts->page . "l" . $this->opts->language;

            $this->page = $this->opts->page;
            $this->engine = $this->opts->engine;

            // Check if results are cached
            if (has_cached_results($this->cache_key))
                return;

            // If engine is set to auto, select an engine
            if ($this->engine == "auto")
                $this->engine = $this->select_engine_randomly();  // Select randomly

            // If no engine was selected or it’s on cooldown
            if (is_null($this->engine) || has_cooldown($this->engine, $this->opts->cooldowns))
                return;

            $this->engine_request = $this->get_engine_request($this->engine, $this->opts, $mh);

            if (is_null($this->engine_request)) {
                error_log("No valid engine request found for engine: " . $this->engine);
                return;
            }
        }

        // Select engine randomly, respecting cooldowns
        private function select_engine_randomly() {
            foreach ($this->engines as $engine) {
                if (!has_cooldown($engine, $this->opts->cooldowns)) {
                    return $engine;
                }
            }
            return null; // If all engines are on cooldown, return null
        }

        // Fetch the appropriate engine request class based on the engine name
        private function get_engine_request($engine, $opts, $mh) {
            switch ($engine) {
                case "google":
                    require_once "engines/text/google.php";
                    return new GoogleRequest($opts, $mh);
                case "brave":
                    require_once "engines/text/brave.php";
                    return new BraveSearchRequest($opts, $mh);
                default:
                    return null; // Invalid engine (we are only using google and brave)
            }
        }

        public function parse_results($response) {
            // Check for cached results first
            if (has_cached_results($this->cache_key))
                return fetch_cached_results($this->cache_key);

            // Ensure the engine request exists
            if (!isset($this->engine_request))
                return array();

            // Get the results from the engine request
            $results = $this->engine_request->get_results();

            // If no results, set a cooldown to avoid hammering the same engine
            if (empty($results)) {
                error_log("No results from engine: " . $this->engine);
                set_cooldown($this->engine, ($this->opts->request_cooldown ?? 1) * 60, $this->opts->cooldowns);
            }

            // Cache and return the results if any were found
            if (!empty($results)) {
                store_cached_results($this->cache_key, $results);
            }

            return $results;
        }

        public static function print_results($results, $opts) {
            // Handle no results or errors
            if (empty($results)) {
                echo "<div class=\"text-result-container\"><p>An error occurred fetching results</p></div>";
                return;
            }

            // Output standard search results
            echo "<div class=\"text-result-container\">";
            foreach ($results as $result) {
                if (!is_array($result) || !array_key_exists("title", $result))
                    continue;

                $title = $result["title"];
                $url = check_for_privacy_frontend($result["url"], $opts);
                $base_url = get_base_url($url);
                $description = $result["description"];

                echo "<div class=\"text-result-wrapper\">";
                echo "<a rel=\"noreferer noopener\" href=\"$url\">";
                echo "$base_url";
                echo "<h2>$title</h2>";
                echo "</a>";
                echo "<span>$description</span>";
                echo "</div>";
            }
            echo "</div>";
        }
    }

    // Handle DuckDuckGo bangs (e.g., !g for Google)
    function check_ddg_bang($query, $opts) {
        $bangs_json = file_get_contents("static/misc/ddg_bang.json");
        $bangs = json_decode($bangs_json, true);

        // Get the bang (e.g., !g) from the query
        if (substr($query, 0, 1) == "!")
            $search_word = substr(explode(" ", $query)[0], 1);
        else
            $search_word = substr(end(explode(" ", $query)), 1);

        $bang_url = null;
        foreach ($bangs as $bang) {
            if ($bang["t"] == $search_word) {
                $bang_url = $bang["u"];
                break;
            }
        }

        // Redirect if a valid bang was found
        if ($bang_url) {
            $bang_query_array = explode("!" . $search_word, $query);
            $bang_query = trim(implode("", $bang_query_array));
            $request_url = str_replace("{{{s}}}", urlencode($bang_query), $bang_url);
            header("Location: " . $request_url);
            die();
        }
    }
?>
