<?php

namespace Tdt\Triples\Repositories;

use Illuminate\Support\Facades\Validator;

/**
 * BaseSourceRepository class covers the basic functionalities that a source repository
 * needs. Doesn't implement an interface, yet implements alot of common functionalities that
 * source repositories share.
 *
 * @copyright (C) 2011,2014 by OKFN Belgium vzw/asbl
 * @license AGPLv3
 * @author Jan Vansteenlandt <jan@okfn.be>
 */
class BaseSourceRepository
{
    protected $rules;

    protected $model;

    /**
     *  Return validator based on the input
     *
     * @param array $input
     * return Illuminate\Support\Facades\Validator
     */
    public function getValidator(array $input)
    {
        return Validator::make($input, $this->rules);
    }

    /**
     * Store a new model object
     *
     * @param array $input
     * @return array|null
     */
    public function store(array $input)
    {
        $source = $this->model->create($input);

        if (!empty($source)) {
            return $source->toArray();
        }

        return $source;
    }

    /**
     * Delete an object of model
     *
     * @param integer $id
     * @return bool|null
     */
    public function delete($id)
    {
        $object = $this->getById($id);

        if (empty($object)) {
            \App::abort(500, "The id, $id, could not be fetched for the current model (" . get_class($this->model) . ").");
        }

        return $this->model->delete($id);
    }

    /**
     * Return an object based on the given id
     *
     * @param integer $id
     * @return array model
     */
    public function getById($id)
    {
        return $this->model->find($id);
    }

    /**
     * Return all objects within the limit, offset boundaries
     *
     * @param integer $limit
     * @param integer $offset
     * @return array model
     */
    public function getAll($limit = PHP_INT_MAX, $offset = 0)
    {
        return $this->model->take($limit)->skip($offset)->get()->toArray();
    }
}
