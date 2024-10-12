<?php
    class EcosiaSearchRequest extends EngineRequest {
        public function get_request_url() {
            // Encode the query string
            $query_encoded = urlencode($this->query);
        
            // Default values for language and location
            $results_language = $this->opts->language ?? 'en';  // Default to 'en'
            $location = $this->opts->location ?? 'us';  // Default to 'us' (for example)

            // Construct the search URL for Ecosia (similar to Bing's structure)
            $url = "https://www.ecosia.org/search?method=index&q=$query_encoded&p={$this->page}";

            // Append language and location parameters if available
            if (!empty($results_language)) {
                $url .= "&language=$results_language";
            }
            
            if (!empty($location)) {
                $url .= "&region=$location";
            }

            // Log the generated URL for debugging purposes
            error_log("Generated Ecosia URL: $url");

            return $url;
        }
        

        public function parse_results($response) {
            $results = array();

            // Log the raw HTML response for debugging (only log first 1000 chars)
            error_log("Ecosia raw response: " . substr($response, 0, 1000));

            $xpath = get_xpath($response);

            // Check if XPath parsing was successful
            if (!$xpath) {
                error_log("Failed to parse Ecosia response: Invalid XPath.");
                return $results;
            }

            // Query the DOM for search results based on Ecosia's structure (adjusted from Bing)
            $result_elements = $xpath->query("//div[contains(@class, 'result')]");

            // Log if no results are found
            if (!$result_elements || $result_elements->length == 0) {
                error_log("No results found in Ecosia response.");
                return $results;
            }

            // Iterate over the result elements and extract the details
            foreach ($result_elements as $result) {
                // Extract URL
                $url_node = $xpath->evaluate(".//a[contains(@class, 'result__url')]//@href", $result)[0];
                if ($url_node === null) continue;
                $url = $url_node->textContent;

                // Avoid duplicate URLs
                if (!empty($results) && end($results)["url"] == $url) continue;

                // Extract title
                $title_node = $xpath->evaluate(".//a[contains(@class, 'result__title')]//h2", $result)[0];
                if ($title_node === null) continue;
                $title = $title_node->textContent;

                // Extract description
                $description_node = $xpath->evaluate(".//p[contains(@class, 'result__snippet')]", $result)[0];
                $description = $description_node !== null ? htmlspecialchars($description_node->textContent) : TEXTS["result_no_description"];

                // Add the result to the results array
                array_push($results, array(
                    "title" => htmlspecialchars($title),
                    "url" => htmlspecialchars($url),
                    "base_url" => htmlspecialchars(get_base_url($url)),
                    "description" => $description
                ));
            }

            return $results;
        }
    }
?>
