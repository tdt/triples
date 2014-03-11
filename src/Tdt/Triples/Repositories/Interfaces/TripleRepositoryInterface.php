<?php

namespace Tdt\Triples\Repositories\Interfaces;

interface TripleRepositoryInterface
{
    /**
     * Return all triples with a subject that equals the base uri
     *
     * @param string $base_uri
     * @param integer $limit
     * @param integer $offset
     * @return EasyRdf_Graph
     */
    public function getAll($base_uri, $limit, $offset);

    /**
     * Return the total amount of triples that
     * have a subject that matches base_uri
     *
     * @param $base_uri
     * @return integer
     */
    public function getCount($base_uri);
}