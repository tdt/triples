<?php

namespace Tdt\Triples\Repositories\ARC2;

use Tdt\Triples\Repositories\Interfaces\TripleRepositoryInterface;
use Tdt\Triples\Repositories\Interfaces\SemanticSourceRepositoryInterface;
use Tdt\Triples\Repositories\SparqlQueryBuilder;
use Tdt\Core\Cache\Cache;

class TripleRepository implements TripleRepositoryInterface
{

    protected $semantic_sources;

    protected $query_builder;

    protected $parameters;

    private static $graph_name = "http://cachedtriples.foo/";

    public function __construct(SemanticSourceRepositoryInterface $semantic_sources)
    {
        $this->semantic_sources = $semantic_sources;

        $this->query_builder = new SparqlQueryBuilder();

        $this->local_store = \App::make('Tdt\Triples\Repositories\SemanticDataHandlers\LocalStoreHandler');

        $this->sparql_handler = \App::make('Tdt\Triples\Repositories\SemanticDataHandlers\SparqlHandler');

        $this->ldf_handler = \App::make('Tdt\Triples\Repositories\SemanticDataHandlers\LDFHandler');
    }

    /**
     * Return all triples with a subject that equals the base uri
     *
     * @param string  $base_uri
     * @param integer $limit
     * @param integer $offset
     * @param boolean $deference If dereferenced, use the depth of the configured semantic source, if not use 1 as depth
     *
     * @return EasyRdf_Graph
     */
    public function getTriples($base_uri, $limit = 100, $offset = 0, $dereference = false)
    {
        // Check if hash variants should be used in the grah patterns to fetch matching triples
        $this->checkHashVariants();

        $depth = null;

        if (!$dereference) {
            $depth = 1;
        }

        $original_limit = $limit;

        $original_offset = $offset;

        // Fetch the total amount of matching triples, over all of the configured semantic sources
        $total_triples_count = $this->getCount($base_uri, $depth);

        // Fetch the local amount of matching triples
        $count_local_triples = $this->local_store->getCount($base_uri, $depth);

        // Create the resulting graph
        $graph = new \EasyRdf_Graph();

        if ($count_local_triples < $offset) {
            $offset -= $count_local_triples;
        } else {

            // Add triples from the local store
            $start_amount = $graph->countTriples();

            $graph = $this->local_store->addTriples($base_uri, $graph, $limit, $offset, $depth);

            $offset -= $graph->countTriples() - $start_amount;
        }

        if ($offset < 0) {
            $offset = 0;
        }

        $sparql_triples_count = $this->sparql_handler->getCount($base_uri, $depth);

        // If there's more room for triples, and the sparql can provide sufficient triples
        // according to the paging parameters, add triples from the sparql
        if ($graph->countTriples() < $limit && $offset < $sparql_triples_count) {

            // For every semantic source, count the triples we'll get out of them (sparql and ldf for the moment)
            $graph = $this->sparql_handler->addTriples($base_uri, $graph, $limit, $offset, $depth);
        }

        $ldf_triples_count = $this->ldf_handler->getCount($base_uri, $depth);

        if ($graph->countTriples() < $limit && $offset < $ldf_triples_count) {

            $graph = $this->ldf_handler->addTriples($base_uri, $graph, $limit, $offset, $depth);
        }

        // If the graph doesn't contain any triples, then the resource can't be resolved
        if ($graph->countTriples() == 0) {
            \App::abort(404, 'The resource could not be found.');
        }

        // Add the void and hydra meta-data triples to the resulting graph
        $graph = $this->addMetaTriples($graph, $original_limit, $original_offset, $total_triples_count);

        return $graph;
    }

    /**
     * Store triples into our local store
     *
     * @param integer $id     The id of the configured semantic source
     * @param array   $config The configuration needed to extract the triples
     */
    public function cacheTriples($id, array $config)
    {
        // Fetch the ARC2 triplestore
        $store = $this->setUpArc2Store();

        // Fetch the data extractor for the given type
        $type = $config['type'];

        $source_type = strtolower($type);

        $graph = '';

        $caching_necessary = true;

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

                // Do nothing, the sparql endpoint is already optimized for read operations
                $caching_necessary = false;

                break;

            case 'ldf':

                // Do nothing the ldf endpoint is a queryable endpoint itself
                $caching_necessary = false;
                break;
            default:
                \App::abort(
                    400,
                    "The source type, $source_type, was configured, but no reader has been found
                    to extract semantic data from it."
                );

                break;
        }

        // If the semantic source needs caching in our local store@
        if ($caching_necessary) {

            // Make the graph name to cache the triples into
            $graph_name = self::$graph_name . $id;

            // Serialise the triples into a turtle string
            $ttl = $graph->serialise('turtle');

            // Parse the turtle into an ARC graph
            $arc_parser = \ARC2::getTurtleParser();

            $ser = \ARC2::getNTriplesSerializer();

            // Parse the turtle string
            $arc_parser->parse('', $ttl);

            // Serialize the triples again, this is because an EasyRdf_Graph has
            // troubles with serializing some unicode characters. The underlying bytes are
            // not properly converted to utf8
            // A dump shows that all unicode encodings through serialization are the same (in easyrdf and arc)
            // however when we convert the string (binary) into a utf8, only the arc2 serialization
            // comes out correctly, hence something beneath the encoding (byte sequences?) must hold some wrongs in the EasyRdf library.
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

                \Log::info("Caching " . count($triples_to_cache) . " triples into the store.");

                $this->addTriples($graph_name, $triples_to_cache, $store);

                $triples_buffer = array_slice($triples_buffer, $buffer_size);
            }

            // Insert the last triples in the buffer
            \Log::info("Caching " . count($triples_buffer) . " triples into the store.");

            $this->addTriples($graph_name, $triples_buffer, $store);

            \Log::info("--------------- DONE CACHING TRIPLES -------------------");
        }
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

        \Log::info("Inserting " . count($triples) . " triples into the triple store.");

        $query = $this->query_builder->createInsertQuery($graph_name, $serialized);

        // Execute the query
        $result = $store->query($query);

        // If the insert fails, insert every triple one by one
        if (!$result) {

            \Log::warning("Inserting a chunk of the triples from the buffer failed. Every triple will be inserted separately.");

            $totalTriples = count($triples);

            // Insert every triple one by one
            foreach ($triples as $triple) {

                $serialized = $this->serialize($triple);

                $query = $this->query_builder->createInsertQuery($graph_name, $serialized);

                // Execute the query
                $result = $store->query($query);

                if (!$result) {
                    \Log::error("Inserting the triple (" . $triple . ") failed, please make sure that it's a valid triple.");
                } else {
                    \Log::info("Successfully insert triple: " . $triple);
                }
            }
        }
    }

    /**
     * Initialize the ARC2 MySQL triplestore (if necessary) and return the instance
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
     * Remove the cached triples coming from a certain semantic source
     *
     * @param integer $id The id of the semantic source configuration
     */
    public function removeTriples($id)
    {
        $graph_name = self::$graph_name . $id;

        $delete_query = "DELETE FROM <$graph_name>";

        $store = $this->setUpArc2Store();

        $result = $store->query($delete_query, 'raw');

        if (!$result) {
            \Log::warning("The delete query that deletes triples from graph with id $id, encountered an error.");
        } else {
            \Log::info("The triples from the graph " . $graph_name . " have been deleted.");
        }
    }

    /**
     * Serialize triples to a format acceptable for a triplestore endpoint (utf8 chacracters)
     *
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
     * Add void and hydra meta-data to an existing graph
     *
     * @param EasyRdf_Graph $graph    The graph to which meta data has to be added
     * @param integer       $count    The total amount of triples that match the URI
     *
     * @return EasyRdf_Graph $graph
     */
    public function addMetaTriples($graph, $limit, $offset, $count)
    {
        // Add the void and hydra namespace to the EasyRdf framework
        \EasyRdf_Namespace::set('hydra', 'http://www.w3.org/ns/hydra/core#');
        \EasyRdf_Namespace::set('void', 'http://rdfs.org/ns/void#');
        \EasyRdf_Namespace::set('dcterms', 'http://purl.org/dc/terms/');

        // Add the meta data semantics to the graph
        $root = \Request::root();
        $root .= '/';

        $base_uri = $root . 'all';

        $identifier = str_replace($root, '', $base_uri);

        $graph->addResource($base_uri . '#dataset', 'a', 'void:Dataset');
        $graph->addResource($base_uri . '#dataset', 'a', 'hydra:Collection');

        $resource = $graph->resource($base_uri);

        $subject_temp_mapping = $graph->newBNode();
        $subject_temp_mapping->addResource('a', 'hydra:IriTemplateMapping');
        $subject_temp_mapping->addLiteral('hydra:variable', 'subject');
        $subject_temp_mapping->addResource('hydra:property', 'rdf:subject');

        $predicate_temp_mapping = $graph->newBNode();
        $predicate_temp_mapping->addResource('a', 'hydra:IriTemplateMapping');
        $predicate_temp_mapping->addLiteral('hydra:variable', 'predicate');
        $predicate_temp_mapping->addResource('hydra:property', 'rdf:predicate');

        $object_temp_mapping = $graph->newBNode();
        $object_temp_mapping->addResource('a', 'hydra:IriTemplateMapping');
        $object_temp_mapping->addLiteral('hydra:variable', 'object');
        $object_temp_mapping->addResource('hydra:property', 'rdf:object');

        $iri_template = $graph->newBNode();
        $iri_template->addResource('a', 'hydra:IriTemplate');
        $iri_template->addLiteral('hydra:template', $root . 'all' . '{?subject,predicate,object}');

        $iri_template->addResource('hydra:mapping', $subject_temp_mapping);
        $iri_template->addResource('hydra:mapping', $predicate_temp_mapping);
        $iri_template->addResource('hydra:mapping', $object_temp_mapping);

        // Add the template to the requested URI resource in the graph
        $graph->addResource($base_uri . '#dataset', 'hydra:search', $iri_template);

        $is_deferenced = false;

        if (strtolower(\Request::segment(1)) != 'all') {
            $full_url = \Request::root() . '/' . \Request::path();

            $is_deferenced = true;
        } else {
            $full_url = $base_uri . '?';
        }

        $template_url = $full_url;
        $templates = array('subject', 'predicate', 'object');
        $has_param = false;

        $query_string = $_SERVER['QUERY_STRING'];

        $query_parts = explode('&', $query_string);

        foreach ($query_parts as $part) {

            if (!empty($part)) {

                $couple = explode('=', $part);

                if (strtolower($couple[0]) ==  'subject') {
                    $template_url .= $couple[0] . '=' . $couple[1] . '&';
                    $has_param = true;
                }

                if (strtolower($couple[0]) ==  'predicate') {
                    $template_url .= $couple[0] . '=' . $couple[1] . '&';
                    $has_param = true;
                }

                if (strtolower($couple[0]) ==  'object') {
                    $template_url .= $couple[0] . '=' . $couple[1] . '&';
                    $has_param = true;
                }

                $full_url .= $couple[0] . '=' . $couple[1] . '&';
            }
        }

        $full_url = rtrim($full_url, '?');
        $full_url = rtrim($full_url, '&');

        $template_url = rtrim($template_url, '?');
        $template_url = rtrim($template_url, '&');

        $full_url = str_replace('#', '%23', $full_url);
        $template_url = str_replace('#', '%23', $template_url);

        if ($is_deferenced) {
            $full_url .= '#dataset';
        }

        // Add paging information
        $graph->addLiteral($full_url, 'hydra:totalItems', \EasyRdf_Literal::create($count, null, 'xsd:integer'));
        $graph->addLiteral($full_url, 'void:triples', \EasyRdf_Literal::create($count, null, 'xsd:integer'));
        $graph->addLiteral($full_url, 'hydra:itemsPerPage', \EasyRdf_Literal::create($limit, null, 'xsd:integer'));
        $graph->addResource($full_url, 'void:subset', \Request::root() . '/all#dataset');

        $paging_info = $this->getPagingInfo($limit, $offset, $count);

        foreach ($paging_info as $key => $info) {

            switch($key) {
                case 'next':
                    if ($has_param) {
                        $glue = '&';
                    } else {
                        $glue = '?';
                    }

                    $graph->addResource($full_url, 'hydra:nextPage', $template_url . $glue . 'limit=' . $info['limit'] . '&offset=' . $info['offset']);
                    break;
                case 'previous':
                    if ($has_param) {
                        $glue = '&';
                    } else {
                        $glue = '?';
                    }

                    $graph->addResource($full_url, 'hydra:previousPage', $template_url . $glue . 'limit=' . $info['limit'] . '&offset=' . $info['offset']);
                    break;
                case 'last':
                    if ($has_param) {
                        $glue = '&';
                    } else {
                        $glue = '?';
                    }

                    $graph->addResource($full_url, 'hydra:lastPage', $template_url . $glue . 'limit=' . $info['limit'] . '&offset=' . $info['offset']);
                    break;
                case 'first':
                    if ($has_param) {
                        $glue = '&';
                    } else {
                        $glue = '?';
                    }

                    $graph->addResource($full_url, 'hydra:firstPage', $template_url . $glue . 'limit=' . $info['limit'] . '&offset=' . $info['offset']);
                    break;
            }
        }

        // Tell the agent that it's a subset
        $graph->addResource($root . 'all#dataset', 'void:subset', $full_url);

        return $graph;
    }

    /**
     * Merge two graphs and return the resulting merged graph
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
     * Return the total amount of triples that
     * have a subject that matches base_uri
     *
     * @param string  $base_uri The base uri that represents the subject of the triple pattern
     * @param integer $depth    The depth the queries should have, handlers should not override this if given
     *
     * @return integer
     */
    public function getCount($base_uri, $depth = null)
    {
        // Count the relevant triples from our local store
        $triples_amount = $this->local_store->getCount($base_uri, $depth);

        // Count the relevant triples from sparql sources
        $triples_amount += $this->sparql_handler->getCount($base_uri, $depth);

        // Count the relevant triples from the LDF sources
        return $triples_amount += $this->ldf_handler->getCount($base_uri);
    }

    /**
     * Return paging headers
     *
     * @param integer $limit  The size of a page
     * @param integer $offset The offset of search result
     * @param integer $total  The total amount of results
     *
     * @return array
     */
    private function getPagingInfo($limit, $offset, $total)
    {
        $paging = array();

        // Add the first page
        $paging['first'] = array('limit' => $limit, 'offset' => 0);

        // Calculate the paging parameters and pass them with the data object
        if ($offset + $limit < $total) {

            $paging['next'] = array('offset' => ($limit + $offset), 'limit' => $limit);

            $last_page = ceil($total / $limit);

            $paging['last'] = array('offset' => ($last_page - 1) * $limit, 'limit' => $limit);
        }

        if ($offset > 0 && $total > 0) {
            $previous = $offset - $limit;
            if ($previous < 0) {
                $previous = 0;
            }

            $paging['previous'] = array('offset' => $previous, 'limit' => $limit);
        }

        return $paging;
    }

    /**
     * Check if hash variants should be used by the sparql query builder
     *
     */
    private function checkHashVariants()
    {
        SparqlQueryBuilder::setHashVariant(\Request::query('hash_variants', false));
    }
}
