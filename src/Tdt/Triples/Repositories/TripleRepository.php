<?php

namespace Tdt\Triples\Repositories;

use Tdt\Triples\Repositories\Interfaces\TripleRepositoryInterface;
use Tdt\Triples\Repositories\Interfaces\SemanticSourceRepositoryInterface;

class TripleRepository implements TripleRepositoryInterface
{

    protected $semantic_sources;

    private static $graph_name = "http://cachedtriples.foo/";

    public function __construct(SemanticSourceRepositoryInterface $semantic_sources)
    {
        $this->semantic_sources = $semantic_sources;
    }

    /**
     * Return all triples with a subject that equals the base uri
     *
     * @param string $base_uri
     * @param integer $limit
     * @param integer $offset
     *
     * @return EasyRdf_Graph
     */
    public function getTriples($base_uri, $limit = PHP_INT_MAX, $offset = 0)
    {
        $query = $this->createSparqlQuery($base_uri);

        $store = $this->setUpArc2Store();

        $result = $store->query($query);

        if (!$result) {
            $message = array_shift($store->getErrors());

            \App::abort(500, "Something went wrong while fetching triples from the store: ");
        }

        // The result will be in an array structure, we need to build an
        // EasyRdf_Graph out of this

        return $this->buildGraph($result['result']);
    }

    /**
     * Store (=cache) triples into a triplestore (or equivalents) for optimization
     *
     * @param integer $id   The id of the configured semantic source
     * @param array $config The configuration needed to extract the triples
     */
    public function cacheTriples($id, array $config)
    {
        // Fetch the ARC2 triplestore
        $store = $this->setUpArc2Store();

        // Fetch the data extractor for the given type
        $type = $config['type'];

        $source_type = strtolower($type);

        $graph = '';

        switch ($source_type) {
            case 'turtle':

                $rdf_reader = \App::make('\Tdt\Core\DataControllers\RDFController');

                $configuration = array(
                    'uri' => $config['uri'],
                    'format' => 'turtle',
                );

                $data = $rdf_reader->readData($configuration, array());
                $graph = $data->data;

                break;
            case 'rdf':

                $rdf_reader = \App::make('\Tdt\Core\DataControllers\RDFController');

                $configuration = array(
                    'uri' => $config['uri'],
                    'format' => 'xml',
                );

                $data = $rdf_reader->readData($configuration, array());
                $graph = $data->data;

                break;
            case 'sparql':

                $sparql_reader = \App::make('\Tdt\Core\DataControllers\SparqlController');

                $configuration = array(
                    'query' => $this->createSparqlQuery($base_uri, @$config['depth']),
                    'endpoint' => $config['endpoint'],
                    'endpoint_user' => @$config['endpoint_user'],
                    'endpoint_password' => @$config['endpoint_password'],
                );

                $data = $sparql_reader->readData($configuration, array());
                $graph = $data->data;

                break;
            default:
                \App::abort(
                    400,
                    "The source type, $source_type, was configured, but no reader has been found
                    to extract semantic data from it."
                );

                break;
        }

        // Make the graph name to cache the triples into
        $graph_name = self::$graph_name . $id;

        // Serialise the triples into turtle
        $ttl = $graph->serialise('turtle');

        // Parse the turlte into an ARC graph
        $arc_parser = \ARC2::getTurtleParser();

        $ser = \ARC2::getNTriplesSerializer();

        // Parse the turtle string
        $arc_parser->parse('', $ttl);

        // Serialize the triples again, this is because an EasyRdf_Graph has
        // troubles with serializing unicode. The underlying bytes are
        // not properly converted to utf8 characters by our serialize function
        // A dump shows that all unicode encodings through serialization are the same (in easyrdf and arc)
        // however when we convert the string (binary) into a utf8, only the arc2 serialization
        // comes out correctly, hence something beneath the encoding (byte sequences?) must hold some wrongs.
        $triples = $ser->getSerializedTriples($arc_parser->getTriples());

        preg_match_all("/(<.*\.)/", $triples, $matches);

        $triples_buffer = array();

        if ($matches[0]) {
            $triples_buffer = $matches[0];
        }

        \Log::info("--------------- CACHING TRIPLES -------------------------");
        \Log::info("Starting insertion of triples into the ARC2 RDF Store into the graph with the name " . $graph_name);

        // Insert the triples in a chunked manner (not all triples at once)
        $buffer_size = 20;

        while (count($triples_buffer) >= $buffer_size) {

            $triples_to_cache = array_slice($triples_buffer, 0, $buffer_size);

            $this->addTriples($graph_name, $triples_to_cache, $store);

            $triples_buffer = array_slice($triples_buffer, $buffer_size);
        }

        // Insert the last triples in the buffer
        $this->addTriples($graph_name, $triples_buffer, $store);

        \Log::info("--------------- DONE CACHING TRIPLES -------------------");
    }

    /**
     * Insert triples into the triple store
     *
     * @param string $graph_name The graph name of the graph to store the triples into
     * @param array  $triples    The triples that need to be stored
     * @param mixed  $store      The store in which the triples will go
     *
     * @return void
     */
    private function addTriples($graph_name, $triples, $store)
    {
        $triples_string = implode(' ', $triples);

        $serialized = $this->serialize($triples_string);

        $query = $this->createInsertQuery($graph_name, $serialized);

        // Execute the query
        $result = $store->query($query);

        // If the insert fails, insert every triple one by one
        if (!$result) {

            \Log::warning("Inserting a chunk of the triples from the buffer failed. Every triple will be inserted separately.");

            $totalTriples = count($triples);

            // Insert every triple one by one
            foreach ($triples as $triple) {

                $serialized = $this->serialize($triple);

                $query = $this->createInsertQuery($graph_name, $serialized);

                // Execute the query
                $result = $store->query($query);

                // TODO logging
                if (!$result) {
                    \Log::error("Inserting the triple (" . $triple . ") failed, please make sure that it's a valid triple.");
                } else {
                    \Log::info("Successfully insert triple: " . $triple);
                }
            }
        }
    }

    /**
     * Initialize the ARC2 MySQL triplestore and return it
     * https://github.com/semsol/arc2/wiki/Using-ARC%27s-RDF-Store
     *
     * @return mixed
     */
    private function setUpArc2Store()
    {
        // Get the MySQL configuration, abort when not applicable
        $mysql_config = \Config::get('database.connections.mysql');

        if (empty($mysql_config) || $mysql_config['driver'] != 'mysql') {
            \App::abort(404, "No configuration for a MySQL connection was found. This is obligatory for the tdt/triples package.");
        }

        // Set up the configuration for the arc2 store
        $config = array(
            'db_host' => $mysql_config['host'],
            'db_name' => $mysql_config['database'],
            'db_user' => $mysql_config['username'],
            'db_pwd' => $mysql_config['password'],
            'store_name' => $mysql_config['prefix'],
        );

        $store = \ARC2::getStore($config);

        // Check if the store has been setup
        if (!$store->isSetUp()) {
            $store->setUp();
        }

        return $store;
    }

    /**
     * Create an insert SPARQL query based on the graph id
     *
     * @param string $graph_name The graph in which the triples will go
     * @param string $triples    The triples that need to be stored
     *
     * @return string
     */
    private function createInsertQuery($graph_name, $triples)
    {
        $query = "INSERT INTO <$graph_name> {";
        $query .= $triples;
        $query .= ' }';

        return $query;
    }

    /**
     * Remove the cached triples coming from a certain semantic source
     *
     * @param integer $id The id of the semantic source configuration
     */
    public function removeTriples($id)
    {
        $graph_name = self::$graph_name . $id;

        $delete_query = "DELETE {?s ?p ?o } FROM <" . $graph_name . '> { ?s ?p ?o}';

        $store = $this->setUpArc2Store();

        $result = $store->query($delete_query, 'raw');

        \Log::info("The triples from the graph " . $graph_name . " have been deleted.");

        if (!$result) {
            \Log::warning("The delete query that deletes triples from graph with id $id, encountered an error.");
        }
    }

    /**
     * Serialize triples to a format acceptable for a triplestore endpoint (utf8 chacracters)
     * @param string $triples
     *
     * @return string
     */
    private function serialize($triples)
    {
        $serialized_triples = preg_replace_callback(
            '/(?:\\\\u[0-9a-fA-Z]{4})+/',
            function ($v) {
                $v = strtr($v[0], array('\\u' => ''));
                return mb_convert_encoding(pack('H*', $v), 'UTF-8', 'UTF-16BE');
            },
            $triples
        );

        return $serialized_triples;
    }

    /**
     * Creates a query that fetches all of the triples
     * of which the subject matches the base uri
     *
     * @param string $base_uri
     *
     * @return string
     */
    private function createSparqlQuery($base_uri, $depth = 3, $limit = 5000, $offset = 0)
    {
        $vars = '<'. $base_uri .'> ?p ?o1.';

        $last_object = '?o1';
        $depth_vars = '';

        for ($i = 2; $i <= $depth; $i++) {

            $depth_vars .= $last_object . ' ?p' . $i . ' ?o' . $i . '. ';

            $last_object = '?o' . $i;
        }

        $construct_statement = 'construct {' . $vars . $depth_vars . '}';
        $filter_statement = '{'. $vars . 'OPTIONAL { ' . $depth_vars . '}}';

        return $construct_statement . $filter_statement . ' offset ' . $offset . 'limit ' . $limit;
    }

    /**
     * Create an EasyRdf_Graph out of an ARC2 query result structure
     *
     * @param array $result
     *
     * @return EasyRdf_Graph
     */
    private function buildGraph(array $result)
    {
        $graph = new \EasyRdf_Graph();

        $triples_buffer = array();

        // Build a string out of the result, we know it's always 3 levels deep
        $ttl_string = '';

        foreach ($result as $s => $p_arr) {

            foreach ($p_arr as $p => $o_arr) {

                $triple_string = '<' . $s . '> ';

                if (filter_var($p, FILTER_VALIDATE_URL) === false) {
                    $triple_string .= $p . ' ';
                } else {
                    $triple_string .= '<' . $p . '> ';
                }

                foreach ($o_arr as $key => $val) {

                    $triple = $triple_string;

                    if ($val['type'] == "uri") {
                        $triple .= '<' . $val['value'] . '> .';
                    } else { //literal
                        if (!empty($val['lang'])) {
                            $triple .= '"' . $val['value'] . '"@' . $val['lang'] . '.';
                        } else {
                            $triple .= '"' . $val['value'] . '"^^<' . $val['datatype'] . '>.';
                        }
                    }

                    array_push($triples_buffer, $triple);
                }
            }
        }

        $ttl_string = implode(' ', $triples_buffer);

        $parser = new \EasyRdf_Parser_Turtle();

        $parser->parse($graph, $ttl_string, 'turtle', '');

        return $graph;
    }

    /**
     * Return the total amount of triples that
     * have a subject that matches base_uri
     *
     * @param $base_uri
     *
     * @return integer
     */
    public function getCount($base_uri)
    {

    }
}
