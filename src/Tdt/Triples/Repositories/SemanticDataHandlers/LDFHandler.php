<?php

namespace Tdt\Triples\Repositories\SemanticDataHandlers;

use Tdt\Triples\Repositories\Interfaces\LdfSourceRepositoryInterface;

class LDFHandler implements SemanticHandlerInterface
{

    private $triples_read;

    private $ldf_repo;

    public function __construct(LdfSourceRepositoryInterface $ldf_repo)
    {
        $this->triples_read = 0;

        $this->ldf_repo = $ldf_repo;
    }

    /**
     * Return the amount of read triples (skipped and fetched) of the semantic data handler
     *
     * @return int
     */
    public function getAmountOfReadTriples()
    {
        return $this->triples_read;
    }

    /**
     * Return the amount of triples according to the count query
     *
     * @param string $query The count SPARQL qeury
     *
     * @return int
     */
    public function getCount($query)
    {
        $triples_amount = 0;

        foreach ($this->ldf_repo->getAll() as $ldf_source) {

            // Fetch the LDF endpoint
            $startfragment = $ldf_source['startfragment'];

            // Build the query string (raw)
            $query_string = $_SERVER['QUERY_STRING'];

            $q_string_raw = '';

            $query_parts = explode('&', $query_string);

            // Don't let paging parameters in the re-constructed query string
            $invalid_q_string = array('page');

            foreach ($query_parts as $part) {

                if (!empty($part)) {

                    $couple = explode('=', $part);

                    if (!in_array($couple[0], $invalid_q_string)) {
                        $q_string_raw .= $couple[0] . '=' . $couple[1] . '&';
                    }
                }
            }

            $entire_fragment = $startfragment;

            if (!empty($q_string_raw)) {
                $q_string_raw = rtrim($q_string_raw, '&');
                $entire_fragment = $startfragment . '?' . $q_string_raw;
            }

            // Make the LDF query (basic GET to the endpoint, should provide us with a hydra:totalItems or void:triples entry)
            $accept = array("Accept: text/turtle,*/*;q=0.0");

            $response = $this->executeUri($entire_fragment, $accept);

            if ($response) {
                // Try decoding it into turtle, if not something is wrong with the response body
                try {
                    $graph = new \EasyRdf_Graph();

                    $parser = new \EasyRdf_Parser_Turtle();

                    $parser->parse($graph, $response, 'turtle', null);

                    // Fetch the count (hydra:totalItems or void:triples)

                    $total_items = $graph->getLiteral($entire_fragment, 'hydra:totalItems');

                    if (is_null($total_items)) {
                        $total_items = $graph->getLiteral($entire_fragment, 'void:triples');
                    }

                    if (!is_null($total_items)) {
                        $triples_amount += $total_items->getValue();
                    }
                } catch (\EasyRdf_Parser_Exception $ex) {
                    \Log::error("Failed to parse turtle content from the LDF endpoint: $endpoint");
                }
            }
        }

        return $triples_amount;
    }

    /**
     * Merge two graphs and return the result
     *
     * @param EasyRdf_Graph $graph
     * @param EasyRdf_Graph $input_graph
     *
     * @return EasyRdf_Graph
     */
    private function mergeGraph($graph, $input_graph)
    {
        $turtle_graph = $input_graph->serialise('turtle');

        $graph->parse($turtle_graph, 'turtle');

        return $graph;
    }

    /**
     * Rebase the graph on triples with the start fragment to our own base URI
     *
     * @param string        $start_fragment
     * @param EasyRdf_Graph $graph
     *
     * @return EasyRdf_Graph
     */
    private function rebaseGraph($start_fragment, $graph)
    {
        // Filter out the #dataset meta-data (if present) and change the URI's to our base URI
        $collections = $graph->allOfType('hydra:Collection');

        // Fetch all of the subject URI's that bring forth hydra meta-data (and are thus irrelevant)
        $ignore_subjects = array();

        if (empty($collection)) {
            $collections = $graph->allOfType('hydra:PagedCollection');
        }

        if (!empty($collections)) {
            foreach ($collections as $collection) {
                array_push($ignore_subjects, $collection->getUri());
            }
        }

        // Fetch the bnode of the hydra mapping (property is hydra:search)
        $hydra_mapping = $graph->getResource($start_fragment . '#dataset', 'hydra:search');

        if (!empty($hydra_mapping)) {

            // Hydra mapping's will be a bnode structure
            array_push($ignore_subjects, '_:' . $hydra_mapping->getBNodeId());

            $mapping_nodes = $hydra_mapping->all('hydra:mapping');

            foreach ($mapping_nodes as $mapping_node) {
                if ($mapping_node->isBNode()) {
                    array_push($ignore_subjects, '_:' . $mapping_node->getBNodeId());
                }
            }

            $graph->deleteResource($start_fragment . '#dataset', 'hydra:search', '_:genid1');
            $graph->deleteResource('_:genid1', 'hydra:mapping', '_:genid2');

            // Delete all of the mapping related resources
            $triples = $graph->toRdfPhp();
        } else {
             // Change all of the base (startfragment) URI's to our own base URI
            $triples = $tmp_graph->toRdfPhp();
        }

        // Unset the #dataset
        unset($triples[$start_fragment . '#dataset']);

        foreach ($ignore_subjects as $ignore_subject) {
            unset($triples[$ignore_subject]);
        }

        $adjusted_graph = new \EasyRdf_Graph();

        foreach ($triples as $subject => $triple) {
            foreach ($triple as $predicate => $objects) {
                foreach ($objects as $object) {
                    $adjusted_graph->add($subject, $predicate, $object['value']);
                }
            }
        }

        return $adjusted_graph;
    }

    /**
     * Add triples to the graph and return it based on limit, offset and the SPARQL query
     *
     * @param string        $base_uri
     * @param EasyRdf_Graph $graph
     * @param int           $limit
     * @param int           $offset
     *
     * @return EasyRdf_Graph
     */
    public function addTriples($base_uri, $graph, $limit, $offset)
    {
        $total_triples = $graph->countTriples();

        // Iterate the LDF end points, not that ldf servers don't necessarily have page size's as a parameter
        // But rather have a fixed page size
        foreach ($this->ldf_repo->getAll() as $ldf_conf) {

            // Build the query string (raw)
            $query_string = $_SERVER['QUERY_STRING'];

            $q_string_raw = '';

            $query_parts = explode('&', $query_string);

            // Don't let paging parameters in the re-constructed query string
            $invalid_q_string = array('page');

            foreach ($query_parts as $part) {

                if (!empty($part)) {

                    $couple = explode('=', $part);

                    if (!in_array($couple[0], $invalid_q_string)) {
                        $q_string_raw .= $couple[0] . '=' . $couple[1] . '&';
                    }
                }
            }

            $q_string_raw = rtrim($q_string_raw, '&');

            $start_fragment = $ldf_conf['startfragment'];

            $entire_fragment = $start_fragment . '?' . $q_string_raw;
            $entire_fragment = rtrim($entire_fragment, '?');

            // Make the LDF query (basic GET to the endpoint, should provide us with a hydra:totalItems or void:triples entry)
            $accept = array("Accept: text/turtle,*/*;q=0.0");

            $response = $this->executeUri($entire_fragment, $accept);

            if ($response) {

                // Try decoding it into turtle, if not something is wrong with the response body
                try {

                    $tmp_graph = new \EasyRdf_Graph();

                    $parser = new \EasyRdf_Parser_Turtle();

                    \EasyRdf_Namespace::set('hydra', 'http://www.w3.org/ns/hydra/core#');

                    $parser->parse($tmp_graph, $response, 'turtle', null);

                            // Fetch the count (hydra:totalItems or void:triples)
                    $count = $tmp_graph->getLiteral($entire_fragment, 'hydra:totalItems');

                    $page_size = $tmp_graph->getLiteral($entire_fragment, 'hydra:itemsPerPage');

                    if (is_null($count)) {
                        $count = $tmp_graph->getLiteral($entire_fragment, 'void:triples');
                    }

                    if (is_null($count) || is_null($page_size)) {

                            // Skip, the count has not been found on this endpoint
                        $count = -1;

                        \Log::warning("An LDF endpoint's count could not be retrieved from the uri: $entire_fragment");
                    } else {
                        $count = $count->getValue();
                        $page_size = $page_size->getValue();
                    }

                        // If the amount of matching triples is higher than the offset
                        // add them and update the offset, if not higher, then only update the offset
                    if ($count > $offset) {

                            // Read the triples from the LDF
                        $query_limit = $limit - $total_triples;

                            // There's no way of giving along the page size (not that we can presume)
                            // So we have to make a numer of requests
                        $amount_of_requests = ceil($query_limit / $page_size);

                        for ($i = 0; $i < $amount_of_requests; $i++) {

                            $paged_fragment = $entire_fragment;

                            if (!empty($q_string_raw)) {
                                $paged_fragment .= '&page=' . $i;
                            } else {
                                $paged_fragment .= '?page=' . $i;
                            }

                                    // Ask for turtle
                            $accept = array('Accept: text/turtle');

                            $response = $this->executeUri($paged_fragment, $accept);

                            if ($response) {

                                    // Try decoding it into turtle, if not something is wrong with the response body
                                try {
                                    $tmp_graph = new \EasyRdf_Graph();

                                    $parser = new \EasyRdf_Parser_Turtle();

                                    $parser->parse($tmp_graph, $response, 'turtle', $start_fragment);

                                        // Fetch the count (hydra:totalItems or void:triples)
                                    $total_items = $tmp_graph->getLiteral($paged_fragment, 'hydra:totalItems');

                                    if (is_null($total_items)) {
                                        $total_items = $tmp_graph->getLiteral($paged_fragment, 'void:triples');
                                    }

                                    if (!is_null($total_items)) {

                                            // This needs to be a function of a different helper class for LDF endpoints
                                        $tmp_graph = $this->rebaseGraph($start_fragment, $tmp_graph);

                                        $graph = $this->mergeGraph($graph, $tmp_graph);

                                        $total_triples += $page_size;
                                    }
                                } catch (\EasyRdf_Parser_Exception $ex) {
                                    \Log::error("Failed to parse turtle content from the LDF endpoint: $endpoint");
                                }
                            } else {
                                \Log::error("Something went wrong while fetching the triples from a LDF source. The error was " . $response . ". The query was : " . $paged_fragment);
                            }
                        }

                    } else {
                                // Update the offset
                        $offset -= $count;
                    }

                    if ($offset < 0) {
                        $offset = 0;
                    }


                } catch (\EasyRdf_Parser_Exception $ex) {
                    \Log::error("Failed to parse turtle content from the LDF endpoint: $endpoint");
                }
            }

        }


        return $graph;
    }

    /**
     * Execute a query using cURL and return the result.
     *
     * @param string $uri      The URI to perform a GET on
     * @param array  $headers  The headers that need to be sent along with the request
     * @param string $user     The user (optional) for basic auth
     * @param string $password The password for the user for basic auth
     *
     * @return string|false
     */
    private function executeUri($uri, $headers, $user = '', $password = '')
    {
        // Check if curl is installed on this machine
        if (!function_exists('curl_init')) {
            \App::abort(500, "cURL is not installed as an executable on this server, this is necessary to execute the SPARQL query properly.");
        }

        // Initiate the curl statement
        $ch = curl_init();

        // If credentials are given, put the HTTP auth header in the cURL request
        if (!empty($user)) {

            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_USERPWD, $user . ":" . $password);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // Set the request uri
        curl_setopt($ch, CURLOPT_URL, $uri);

        // Request for a string result instead of having the result being outputted
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute the request
        $response = curl_exec($ch);

        if (!$response) {
            $curl_err = curl_error($ch);
            \Log::error("Something went wrong while executing a count sparql query. The request we put together was: $uri.");
        }

        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // According to the SPARQL 1.1 spec, a SPARQL endpoint can only return 200,400,500 reponses
        if ($response_code == '400') {
            \Log::error("The SPARQL endpoint returned a 400 error. The error was: $response. The URI was: $uri");
        } elseif ($response_code == '500') {
            \Log::error("The SPARQL endpoint returned a 500 error. The URI was: $uri");
        }

        curl_close($ch);

        return $response;
    }
}
