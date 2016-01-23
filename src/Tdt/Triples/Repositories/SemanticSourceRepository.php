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
     *
     * @return array Model
     */
    public function store(array $input)
    {
        $type = $this->validateSourceType($input);

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
     *
     * @return bool|null
     */
    public function delete($id)
    {
        $object = $this->model->find($id);

        if (!empty($object)) {
            $object->delete();

            return true;
        }

        return false;
    }

    /**
     * Update a semantic source configuration
     *
     * @param integer $id    The id of the semantic source that needs to be updated
     * @param array   $input The properties that need to be stored
     *
     * @return array Model
     */
    public function update($id, array $input)
    {
        // Check if the id matches an existing semantic source
        $semantic_source = $this->getEloquentModel($id);

        if (empty($semantic_source)) {
            \App::abort(404, "The given id, $id, does not represent a semantic source.");
        }

        // Validate the provided source type
        $type = $this->validateSourceType($input);

        // Fetch the old source type
        $old_source_type = $semantic_source->source_type;
        $old_source_type = str_replace('Source', '', $old_source_type);

        $old_source_repository = $this->getSourceRepository($old_source_type);

        // Fetch the source repository and store the configuration after validation
        $source_repository = $this->getSourceRepository($type);

        // Fetch only the input that the source repository needs
        $input = array_only($input, array_keys($source_repository->getCreateParameters()));

        $validator = $source_repository->getValidator($input);

        if ($validator->fails()) {
            $message = $validator->messages()->first();

            \App::abort(400, $message);
        }

        // Everything is in place to update the semantic source

        // Store the new source
        $source = $source_repository->store($input);

        if (!empty($source)) {

            // Delete the previous linked semantic source
            $result = $old_source_repository->delete($semantic_source->source_id);

            $semantic_source->source_id = $source['id'];
            $semantic_source->source_type = $source['type'] . 'Source';
            $semantic_source->save();
        }

        return $semantic_source->toArray();
    }

    /**
     * Get all the semantic sources with their source relationship
     *
     * @param integer $limit
     * @param integer $offset
     *
     * @return array
     */
    public function getAllConfigurations($limit = PHP_INT_MAX, $offset = 0)
    {
        $semantic_sources = $this->getAll($limit, $offset);

        $result = array();

        foreach ($semantic_sources as $entry) {

            $id = $entry['id'];
            $configuration = $this->getSourceConfiguration($id);

            $result[$id] = $configuration;
        }

        return $result;
    }

    /**
     * Get the entire configruation for a semantic source
     *
     * @param integer $id The id of the semantic source
     *
     * @return array
     */
    public function getSourceConfiguration($id)
    {
        $semantic_source = $this->getEloquentModel($id);

        if (empty($semantic_source)) {
            \App::abort(404, "The semantic source with id, $id, could not be found");
        }

        $source = $semantic_source->source()->first();

        if (empty($source)) {
            \App::abort(404, "The semantic source with id, $id can be retrieved, however the source it links with, can't.");
        }

        $source_properties = array(
            'id' => $id,
            'type' => $source->type,
        );

        foreach ($source->getFillable() as $key) {
            $source_properties[$key] = $source->$key;
        }

        return $source_properties;
    }

    /**
     * Get all the semantic sources
     *
     * @param integer $limit
     * @param integer $offset
     *
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
     *
     * @return Model
     */
    private function getEloquentModel($id)
    {
        return $this->model->find($id);
    }

    /**
     * Validate an input array for a valid source type
     *
     * @param array $input The properties that came with the request
     *
     * @return string|false
     */
    private function validateSourceType(array $input)
    {
        $type = @$input['type'];

        // If no source type is given, abort the process.
        if (empty($type)) {
            \App::abort(400, "Please provide a source type, support source types are: ldf, rdf, sparql, turtle.");
        }

        return $type;
    }

    /**
     * Return the repository based on the type of source. Aborts on failure.
     *
     * @param string $type
     *
     * @return mixed
     */
    private function getSourceRepository($type)
    {
        try {

            $repository = \App::make('Tdt\Triples\Repositories\Interfaces\\' . ucfirst($type) . 'SourceRepositoryInterface');

            return $repository;

        } catch (\ReflectionException $ex) {
            \App::abort(400, "The type that was given, $type, is not a supported one.");
        }
    }
}
