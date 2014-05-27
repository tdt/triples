<?php

namespace Tdt\Triples\Repositories\Interfaces;

/**
 * Aggregates the turtle, sparql, ... repositories to 1 point of entry
 */
interface SemanticSourceRepositoryInterface
{
    /**
     * Store a new semantic source
     *
     * @param array $input
     *
     * @return array Model
     */
    public function store(array $input);

    /**
     * Update a semantic source
     *
     * @param integer $id    The id of the semantic source that needs to be updated
     * @param array   $input The properties that need to be stored
     *
     * @return array
     */
    public function update($id, array $input);

    /**
     * Delete a semantic source
     *
     * @param integer $id
     *
     * @return bool|null
     */
    public function delete($id);

    /**
     * Get all the semantic sources
     *
     * @param integer $limit
     * @param integer $offset
     *
     * @return array
     */
    public function getAll($limit, $offset);

    /**
     * Get all the semantic sources with their source relationship
     *
     * @param integer $limit
     * @param integer $offset
     *
     * @return array
     */
    public function getAllConfigurations($limit = PHP_INT_MAX, $offset = 0);

   /**
    * Get the entire configuration for a semantic source
    *
    * @param integer $id The id of the semantic source
    *
    * @return array
    */
    public function getSourceConfiguration($id);
}
