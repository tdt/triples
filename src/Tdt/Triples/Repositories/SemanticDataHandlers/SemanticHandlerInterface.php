<?php

namespace Tdt\Triples\Repositories\SemanticDataHandlers;

interface SemanticHandlerInterface
{
    /**
     * Return the amount of read triples (skipped and fetched) of the semantic data handler
     *
     * @return int
     */
    public function getAmountOfReadTriples();

    /**
     * Return the amount of triples according to the count query
     *
     * @param string $query The count SPARQL qeury
     *
     * @return int
     */
    public function getCount($query);

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
    public function addTriples($base_uri, $graph, $limit, $offset);
}
