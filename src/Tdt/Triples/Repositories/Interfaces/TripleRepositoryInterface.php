<?php

namespace Tdt\Triples\Repositories\Interfaces;

/**
 * Implementations of this interface should manage the
 * semantic source that are stored in this package.
 * This means caching, fetching, deleting, etc. triples stored
 * in the various semantic sources stored in the package.
 */
interface TripleRepositoryInterface
{
    /**
     * Return all triples with a subject that equals the base uri
     *
     * @param string  $base_uri
     * @param integer $limit
     * @param integer $offset
     * @return EasyRdf_Graph
     */
    public function getTriples($base_uri, $limit, $offset);

    /**
     * Add void and hydra meta-data to an existing graph
     *
     * @param EasyRdf_Graph $graph    The graph to which meta data has to be added
     * @param integer       $count    The total amount of triples that match the URI
     *
     * @return EasyRdf_Graph $graph
     */
    public function addMetaTriples($graph, $limit, $offset, $count);

    /**
     * Return the total amount of triples that
     * have a subject that matches base_uri
     *
     * @param string $base_uri
     * @return integer
     */
    public function getCount($base_uri);

    /**
     * Store (=cache) triples into a triplestore (or equivalents) for optimization
     *
     * @param string $graph_name The name of the graph in which the triples will be stored
     * @param array  $config     The configuration needed to extract the triples
     */
    public function cacheTriples($graph_name, array $config);

    /**
     * Remove the cached triples coming from a certain semantic source
     *
     * @param integer $id The id of the semantic source configuration
     */
    public function removeTriples($id);
}
