<?php

namespace Tdt\Triples\Repositories;

use Tdt\Triples\Repositories\Interfaces\TripleRepositoryInterface;
use Tdt\Triples\Repositories\Interfaces\SemanticSourceRepositoryInterface;

class TripleRepository implements TripleRepositoryInterface
{

    protected $semantic_sources;

    public function __construct(SemanticSourceRepositoryInterface $semantic_sources)
    {
        $this->semantic_sources = $semantic_sources;
    }

    /**
     * Return all triples with a subject that equals the base uri
     *
     * @param string $base_uri
     * @param integer $limit
     * @param integer $offset
     * @return EasyRdf_Graph
     */
    public function getAll($base_uri, $limit = PHP_INT_MAX, $offset = 0)
    {
        // Fetch all configured semantic sources
        $semantic_sources = $this->semantic_sources->getAllConfigurations();

        $graph = new \EasyRdf_Graph();

        // Iterate every source and retrieve only the triples with a subject matching the base uri
        foreach($semantic_sources as $semantic_source){

            $source_type = $semantic_source['source_type'];

            switch ($source_type) {
                case 'Turtle':

                    $rdf_reader = new \Tdt\Core\DataControllers\RdfController();

                    $configuration = array(
                        'uri' => $semantic_source['uri'],
                        'format' => 'turtle',
                    );

                    $data = $rdf_reader->readData($configuration, array());

                    $properties = $data->data->properties($base_uri);

                    foreach ($properties as $property) {

                        $result = $data->data->get($base_uri, $property);

                        $predicate = $property;
                        $info = $result->toRdfPhp($property);

                        // Add the triple, with subject = base uri to our resulting graph
                        if ($info['type'] == 'literal') {
                            $graph->addLiteral($base_uri, $predicate, $info['value']);
                        }else {
                            $graph->addResource($base_uri, $predicate, $info['value']);
                        }
                    }

                    return $graph;
                    break;
                default:
                    \App::abort(400, "The source type, $source_type, was configured, but no reader has been found to extract semantic data from it.");
                    break;
            }
        }

        // Apply paging

        // Return the resulting graph
    }

    /**
     * Return the total amount of triples that
     * have a subject that matches base_uri
     *
     * @param $base_uri
     * @return integer
     */
    public function getCount($base_uri)
    {

    }
}
