<?php

namespace Tdt\Triples\Repositories\Interfaces;

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
     * @param array $config The configuration needed to extract the triples
     */
    public function cacheTriples(array $config);
}
