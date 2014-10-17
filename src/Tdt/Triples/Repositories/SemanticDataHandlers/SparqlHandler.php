<?php

namespace Tdt\Triples\Repositories\SemanticDataHandlers;

use Tdt\Triples\Repositories\Interfaces\SparqlSourceRepositoryInterface;
use Tdt\Triples\Repositories\SparqlQueryBuilder;
use Tdt\Core\Cache\Cache;

class SparqlHandler implements SemanticHandlerInterface
{

    private $triples_read;

    private $sparql_repo;

    private $query_builder;

    public function __construct(SparqlSourceRepositoryInterface $sparql_repo)
    {
        $this->triples_read = 0;

        $this->sparql_repo = $sparql_repo;

        $this->query_builder = new SparqlQueryBuilder();
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
     * @param string  $base_uri The base_uri of the query
     * @param integer $depth    The depth the queries should have, handlers should not override this if given
     *
     * @return int
     */
    public function getCount($base_uri, $depth = null)
    {
        $triples_amount = 0;

        foreach ($this->sparql_repo->getAll() as $sparql_source) {

            if (is_null($depth)) {
                $depth = $sparql_source['depth'];
            }

            $count_query = $this->query_builder->createCountQuery(
                $base_uri,
                \Request::root(),
                $sparql_source['named_graph'],
                $depth
            );

            $endpoint = $sparql_source['endpoint'];
            $pw = $sparql_source['endpoint_password'];
            $user = $sparql_source['endpoint_user'];

            // Fetch the SPARQL endpoint
            $endpoint = rtrim($endpoint, '/');

            // Check for caching
            $cache_string = $this->buildCacheString($sparql_source['id'], $count_query);

            if (Cache::has($cache_string)) {
                $result = Cache::get($cache_string);
            } else {

                $query = urlencode($count_query);
                $query = str_replace("+", "%20", $query);

                $query_uri = $endpoint . '?query=' . $query . '&format=' . urlencode("application/sparql-results+json");

                // Make a request with the count query to the SPARQL endpoint
                $result = $this->executeUri($query_uri, array(), $user, $pw);

                Cache::put($cache_string, $result, 5);
            }

            $response = json_decode($result);

            if (!empty($response)) {

                $count = $response->results->bindings[0]->count->value;

                $triples_amount += $count;
            }
        }

        return $triples_amount;
    }

    /**
     * Add triples to the graph and return it based on limit, offset and the SPARQL query
     *
     * @param string        $base_uri
     * @param EasyRdf_Graph $graph
     * @param int           $limit
     * @param int           $offset
     * @param integer       $depth     The depth the queries should have, handlers should not override this if given
     *
     * @return EasyRdf_Graph
     */
    public function addTriples($base_uri, $graph, $limit, $offset, $depth = null)
    {
        $total_triples = $graph->countTriples();

        // Iterate the sparql endpoints
        foreach ($this->sparql_repo->getAll() as $sparql_source) {

            $endpoint = $sparql_source['endpoint'];
            $pw = $sparql_source['endpoint_password'];
            $user = $sparql_source['endpoint_user'];

            $endpoint = rtrim($endpoint, '/');

            if (is_null($depth)) {
                $depth = $sparql_source['depth'];
            }

            $count_query = $this->query_builder->createCountQuery(
                $base_uri,
                \Request::root(),
                $sparql_source['named_graph'],
                $depth
            );

            \Log::info("sparql : " . $count_query . " base uri : " . $base_uri);

            // Check if the response has been cached yet
            $cache_string = $this->buildCacheString($sparql_source['id'], $count_query);

            if (Cache::has($cache_string)) {
                $result = Cache::get($cache_string);
            } else {

                $count_query = urlencode($count_query);
                $count_query = str_replace("+", "%20", $count_query);

                $query_uri = $endpoint . '?query=' . $count_query . '&format=' . urlencode("application/sparql-results+json");

                // Make a request with the count query to the SPARQL endpoint
                $result = $this->executeUri($query_uri, array(), $user, $pw);

                Cache::put($cache_string, $result, 5);
            }

            $response = json_decode($result);

            if (!empty($response)) {

                $count = $response->results->bindings[0]->count->value;

                // If the amount of matching triples is higher than the offset
                // add them and update the offset, if not higher, then only update the offset

                if ($count > $offset) {

                    // Read the triples from the sparql endpoint
                    $query_limit = $limit - $total_triples;

                    $query = $this->query_builder->createFetchQuery(
                        $base_uri,
                        \Request::root(),
                        $sparql_source['named_graph'],
                        $query_limit,
                        $offset,
                        $depth
                    );

                    $query = urlencode($query);

                    $query = str_replace("+", "%20", $query);

                    $query_uri = $endpoint . '?query=' . $query . '&format=' . urlencode("application/rdf+xml");

                    // Check for caching
                    $cache_string = $this->buildCacheString($sparql_source['id'], $query_uri);

                    if (Cache::has($cache_string)) {
                        $result = Cache::get($cache_string);
                    } else {

                        $result = $this->executeUri($query_uri, array(), $user, $pw);
                    }

                    if (!empty($result) && $result[0] == '<') {

                        // Parse the triple response and retrieve the triples from them
                        $result_graph = new \EasyRdf_Graph();

                        $parser = new \EasyRdf_Parser_RdfXml();

                        $parser->parse($result_graph, $result, 'rdfxml', null);

                        $graph = $this->mergeGraph($graph, $result_graph);

                        $total_triples += $count - $offset;

                    } else {

                        $sparql_id = $sparql_source['id'];

                        \Log::error("Something went wrong while fetching the triples from the sparql source with id $sparql_id. The error was " . $result . ". The query was : " . $query_uri);
                    }

                } else {
                    // Update the offset
                    $offset -= $count;
                }

                if ($offset < 0) {
                    $offset = 0;
                }
            }
        }

        return $graph;
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
        $rdfxml_string = $input_graph->serialise('rdfxml');

        $graph->parse($rdfxml_string, 'rdfxml');

        return $graph;
    }

    /**
     * Return a string used for caching based on the id of the Sparql source and the query
     *
     * @param int    $id
     * @param string $query
     *
     * @return string
     */
    private function buildCacheString($id, $query)
    {
        return sha1('sparql_' . $id . '_' . $query);
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

            \Log::error("The error we got was: $curl_err");
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
