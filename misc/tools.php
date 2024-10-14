<?php
    function get_base_url($url) {
        $parsed = parse_url($url);
        if (isset($parsed["scheme"], $parsed["host"]) && !empty($parsed["scheme"]) && !empty($parsed["host"])) {
            return "{$parsed["scheme"]}://{$parsed["host"]}/";
        }
        logStackTrace();
        return "";
    } 
    }


    function get_root_domain($url) {
        return parse_url($url, PHP_URL_HOST);
    }

    function try_replace_with_frontend($url, $frontend, $original, $opts) {
        $frontends = $opts->frontends;
        if (!array_key_exists($frontend, $frontends)) {
            return $url;
        }
        $frontend_url = trim($frontends[$frontend]["instance_url"]);
        if (empty($frontend_url)) {
            return $url;
        }

        switch (true) {
            case strpos($url, "wikipedia.org") !== false:
                $lang = strtok(parse_url($url, PHP_URL_HOST), '.');
                return "$frontend_url" . str_replace($original, '', parse_url($url, PHP_URL_PATH)) . "?lang=$lang";

            case strpos($url, "fandom.com") !== false:
                $wiki_name = strtok(parse_url($url, PHP_URL_HOST), '.');
                return "$frontend_url/$wiki_name" . str_replace($original, '', parse_url($url, PHP_URL_PATH));

            case strpos($url, "gist.github.com") !== false:
                return "$frontend_url/gist" . str_replace('gist.github.com', '', parse_url($url, PHP_URL_PATH));

            case strpos($url, "stackexchange.com") !== false:
                $se_domain = strtok(parse_url($url, PHP_URL_HOST), '.');
                return "$frontend_url/exchange/$se_domain" . str_replace('stackexchange.com', '', parse_url($url, PHP_URL_PATH));

            default:
                return "$frontend_url" . str_replace($original, '', parse_url($url, PHP_URL_PATH));
        }
    }
            
             else if (strpos($url, "stackexchange.com") !== false) {
                $se_domain = explode(".", explode("://", $url)[1])[0];
                $se_path = explode("stackexchange.com", $url)[1];
                $url = $frontend . "/exchange" . "/" . $se_domain . $se_path;
            } else {
                $url =  $frontend . explode($original, $url)[1];
            }


            return $url;
        }

        return $url;
    }

    function check_for_privacy_frontend($url, $opts) {
        if ($opts->disable_frontends)
            return $url;

        foreach($opts->frontends as $frontend => $data) {
            $original = $data["original_url"];

            if (strpos($url, $original)) {
                $url = try_replace_with_frontend($url, $frontend, $original, $opts);
                break;
            
        }

        return $url;
    }

    function get_xpath($response) {
        if (!$response)
            return null;

        $htmlDom = new DOMDocument;
        @$htmlDom->loadHTML($response);
        $xpath = new DOMXPath($htmlDom);

        return $xpath;
    }

    function request($url, $conf) {
        $ch = curl_init($url);
        curl_setopt_array($ch, $conf);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        copy_cookies($ch);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    function human_filesize($bytes, $dec = 2) {
        $size   = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$dec}f ", $bytes / pow(1024, $factor)) . @$size[$factor];
    }

    function remove_special($string) {
        $string = preg_replace("/[\r\n]+/", "\n", $string);
        return trim(preg_replace("/\s+/", ' ', $string));
     }

    function print_elapsed_time($start_time, $results, $opts) {
            $source = "";
            if (array_key_exists("results_source", $results)) {
                $source = " from " . $results["results_source"];
                unset($results["results_source"]);
            }

            $end_time = number_format(microtime(true) - $start_time, 2, '.', '');
            echo "<p id=\"time\">Fetched the results in $end_time seconds$source</p>";
        }

    function print_next_page_button($text, $page, $query, $type) {
        echo "<form class=\"page\" action=\"search.php\" target=\"_top\" method=\"get\" autocomplete=\"off\">";
        echo "<input type=\"hidden\" name=\"p\" value=\"" . $page . "\" />";
        echo "<input type=\"hidden\" name=\"q\" value=\"$query\" />";
        echo "<input type=\"hidden\" name=\"t\" value=\"$type\" />";
        echo "<button type=\"submit\">$text</button>";
        echo "</form>";
    }

    function copy_cookies($curl) {
        if (isset($_SERVER['HTTP_COOKIE'])) {
            curl_setopt($curl, CURLOPT_COOKIE, $_SERVER['HTTP_COOKIE']);
        }
    }

    
    function get_country_emote($code) {
        $emoji = [];
        foreach(str_split($code) as $c) {
            if(($o = ord($c)) > 64 && $o % 32 < 27) {
                $emoji[] = hex2bin("f09f87" . dechex($o % 32 + 165));
                continue;
            }
            
            $emoji[] = $c;
        }

        return join($emoji);
    }

    function logStackTrace() {
        // Get the stack trace
        $stackTrace = debug_backtrace();

        // Format the stack trace for logging
        $logMessage = "Stack Trace: ";
        foreach ($stackTrace as $index => $trace) {
            // Skip the first entry as it's the current function call
            if ($index === 0) {
                continue;
            }

            // Build the log message for each stack frame
            $logMessage .= "#{$index} ";
            if (isset($trace['file'])) {
                $logMessage .= "File: {$trace['file']} ";
            }
            if (isset($trace['line'])) {
                $logMessage .= "Line: {$trace['line']} ";
            }
            if (isset($trace['function'])) {
                $logMessage .= "Function: {$trace['function']} ";
            }
            $logMessage .= "\n";
        }

        // Log the stack trace to the error log
        error_log($logMessage);
    }

?>
