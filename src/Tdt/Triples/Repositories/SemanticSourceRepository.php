<?php

namespace Tdt\Triples\Repositories;

use Tdt\Triples\Repositories\Interfaces\SemanticSourceRepositoryInterface;

class SemanticSourceRepository implements SemanticSourceRepositoryInterface
{
    protected $model;

    public function __construct(\SemanticSource $model)
    {
        $this->model = $model;
    }

    /**
     * Store a new semantic source
     *
     * @param array $input
     * @return array Model
     */
    public function store(array $input)
    {
        $type = @$input['source_type'];

        // If no source type is given, abort the process.
        if (empty($type)) {
            \App::abort(400, "Please provide a source type.");
        }

        // Fetch the source repository and store the configuration after validation
        $source_repository = $this->getSourceRepository($type);

        // Fetch only the input that the source repository needs
        $input = array_only($input, array_keys($source_repository->getCreateParameters()));

        $validator = $source_repository->getValidator($input);

        if ($validator->fails()) {
            $message = $validator->messages()->first();

            \App::abort(400, $message);
        }

        $source = $source_repository->store($input);

        if (!empty($source)) {

            // Create the polymorph relationship with
            $semantic_source = $this->model->create(array());
            $semantic_source->source_id = $source['id'];
            $semantic_source->source_type = $source['type'] . 'Source';
            $semantic_source->save();

            return $semantic_source->toArray();
        }
    }

    /**
     * Delete a semantic source
     *
     * @param integer $id
     * @return bool|null
     */
    public function delete($id)
    {
        $object = $this->model->find($id);

        if (!empty($object)) {
            return $object->delete();
        }

        return $object;
    }

    /**
     * Update a semantic source configuration
     *
     * @param array $input
     * @return array Model
     */
    public function update(array $input)
    {

    }

    /**
     * Get all the semantic sources with their source relationship
     *
     * @param integer $limit
     * @param integer $offset
     * @return array
     */
    public function getAllConfigurations($limit = PHP_INT_MAX, $offset = 0)
    {
        $semantic_sources = $this->getAll($limit, $offset);

        $result = array();

        foreach($semantic_sources as $entry){

            $semantic_source = $this->getEloquentModel($entry['id']);
            $source = $semantic_source->source()->first();

            $source_properties = array(
                'source_type' => $source->type,
            );

            foreach($source->getFillable() as $key){
                $source_properties[$key] = $source->$key;
            }

            $result[$semantic_source['id']] = $source_properties;
        }

        return $result;
    }

    /**
     * Get all the semantic sources
     *
     * @param integer $limit
     * @param integer $offset
     * @return array
     */
    public function getAll($limit = PHP_INT_MAX, $offset = 0)
    {
        return $this->model->take($limit)->offset($offset)->get()->toArray();
    }

    /**
     * Get the eloquent semantic model
     *
     * @param integer id
     * @return Model
     */
    private function getEloquentModel($id)
    {
        return $this->model->find($id);
    }

    /**
     * Return the repository based on the type of source. Aborts on failure.
     *
     * @param string $type
     * @return mixed
     */
    private function getSourceRepository($type)
    {
        try {

            $repository = \App::make('Tdt\Triples\Repositories\Interfaces\\' . ucfirst($type) . 'SourceRepositoryInterface');

            return $repository;

        } catch(\ReflectionException $ex){
            \App::abort(400, "The type that was given, $type, is not a supported one.");
        }
    }
}
