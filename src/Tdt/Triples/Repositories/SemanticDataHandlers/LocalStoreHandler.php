<?php

namespace Tdt\Triples\Repositories\SemanticDataHandlers;

use Tdt\Triples\Repositories\SparqlQueryBuilder;

class LocalStoreHandler implements SemanticHandlerInterface
{

    private $triples_read;

    private $query_builder;

    public function __construct()
    {
        $this->triples_read = 0;

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
     * @param string  $base_uri The URI of the request
     * @param integer $depth    The depth the queries should have, handlers should not override this if given (can be null)
     *
     * @return int
     */
    public function getCount($base_uri, $depth = null)
    {
        if (is_null($depth)) {
            $depth = 3;
        }

        // Create the count query
        $count_query = $this->query_builder->createCountQuery(
            $base_uri,
            \Request::root(),
            null,
            $depth
        );

        $store = $this->setUpArc2Store();

        $result = $store->query($count_query, 'raw');

        if (!empty($result['rows'])) {
            return $result['rows'][0]['count'];
        }

        return 0;
    }

    /**
     *
     */
    private function hasParameters()
    {
        $sparql_param_defaults = array('?s', '?p', '?o');

        foreach (SparqlQueryBuilder::getParameters() as $param) {
            if (!in_array($param, $sparql_param_defaults)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add triples to the graph and return it based on limit, offset and the SPARQL query
     *
     * @param string        $base_uri
     * @param EasyRdf_Graph $graph
     * @param int           $limit
     * @param int           $offset
     * @param integer       $depth    The depth the queries should have, handlers should not override this if given
     *
     * @return EasyRdf_Graph
     */
    public function addTriples($base_uri, $graph, $limit, $offset, $depth = null)
    {
        // Build the query
        if (is_null($depth)) {
            $depth = 3;
        }

        $query = $this->query_builder->createFetchQuery(
            $base_uri,
            \Request::root(),
            null,
            $limit,
            $offset,
            $depth
        );

        $store = $this->setUpArc2Store();

        $result = $store->query($query);

        if (!empty($result['result'])) {

            $result = $result['result'];

            $ttl_string = $store->toNTriples($result);

            $parser = new \EasyRdf_Parser_Turtle();

            $parser->parse($graph, $ttl_string, 'turtle', '');
        }

        if (!$result) {

            $errors = $store->getErrors();

            $message = array_shift($errors);

            \Log::error("Error occured in the triples package, while trying to retrieve triples: " . $message);
        }

        return $graph;
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
            \App::abort(404, "No configuration for a MySQL connection was found in Config class. This is obligatory for the tdt/triples package.");
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
}
