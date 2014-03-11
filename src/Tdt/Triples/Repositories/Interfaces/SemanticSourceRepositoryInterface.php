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
     * @return array Model
     */
    public function store(array $input);

    /**
     * Delete a semantic source
     *
     * @param integer $id
     * @return bool|null
     */
    public function delete($id);

    /**
     * Update a semantic source configuration
     *
     * @param array $input
     * @return array Model
     */
    public function update(array $input);

    /**
     * Get all the semantic sources
     *
     * @param array $input
     * @return array
     */
    public function getAll();
}
