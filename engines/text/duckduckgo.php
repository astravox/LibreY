<?php
    class DuckDuckGoRequest extends EngineRequest {
        public function get_request_url() {
            $query_encoded = str_replace("%22", "\"", urlencode($this->query));

            $domain = 'com';
            $results_language = $this->opts->language;
            $number_of_results = $this->opts->number_of_results;

            $url = "https://html.duckduckgo.$domain/html/?q=$query_encoded&kd=-1&s=" . 3 * $this->page;

            if (strlen($results_language) > 0 && strlen($results_language) < 3)
                $url .= "&lr=lang_$results_language";

            if (strlen($number_of_results) > 0 && strlen($number_of_results) < 3)
                $url .= "&num=$number_of_results";

            if (isset($_COOKIE["safe_search"]))
                $url .= "&safe=medium";

            return $url;
        }

        public function parse_results($response) {
            $results = array();
            $xpath = get_xpath($response);

            if (!$xpath) {
                error_log("Failed to parse DuckDuckGo response: Invalid XPath.");
                return $results;
            }

            $result_elements = $xpath->query("/html/body/div[1]/div[". count($xpath->query('/html/body/div[1]/div')) ."]/div/div/div[contains(@class, 'web-result')]/div");
            
            if (!$result_elements || $result_elements->length == 0) {
                error_log("No results found in DuckDuckGo response.");
                return $results;
            }

            foreach ($result_elements as $result) {
                $url_node = $xpath->evaluate(".//h2[@class='result__title']//a/@href", $result)[0];

                if ($url_node === null) continue;

                $url = $url_node->textContent;

                // Check for duplicate URLs
                if (!empty($results)) {
                    if (end($results)["url"] == $url) continue;
                }

                $title_node = $xpath->evaluate(".//h2[@class='result__title']", $result)[0];
                $description_node = $xpath->evaluate(".//a[@class='result__snippet']", $result)[0];

                $title = $title_node !== null ? htmlspecialchars($title_node->textContent) : TEXTS["result_no_title"];
                $description = $description_node !== null ? htmlspecialchars($description_node->textContent) : TEXTS["result_no_description"];

                array_push($results, array(
                    "title" => $title,
                    "url" => htmlspecialchars($url),
                    "base_url" => htmlspecialchars(get_base_url($url)),
                    "description" => $description
                ));
            }

            return $results;
        }
    }
?>
