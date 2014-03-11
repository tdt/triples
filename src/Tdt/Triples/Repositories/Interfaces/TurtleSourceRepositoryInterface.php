<?php

namespace Tdt\Triples\Repositories\Interfaces;

interface TurtleSourceRepositoryInterface
{

    /**
     *  Return validator based on the input
     *
     * @param array $input
     * return Illuminate\Support\Facades\Validator
     */
    public function getValidator(array $input);

    /**
     * Store a new turtle source
     *
     * @param array $input
     * @return array Model
     */
    public function store(array $input);

    /**
     * Delete a turtle source
     *
     * @param integer $id
     * @return bool|null
     */
    public function delete($id);

    /**
     * Update a turtle source configuration
     *
     * @param array $input
     * @return array Model
     */
    public function update(array $input);

    /**
     * Get all the turtle sources
     *
     * @param integer $limit
     * @param integer $offset
     * @return array
     */
    public function getAll($limit, $offset);

    /**
     * Get all of the properties that are
     * necessary to store an object
     *
     * @return array
     */
    public function getCreateParameters();
}
