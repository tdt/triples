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
     *
     * @return EasyRdf_Graph
     */
    public function getAll($base_uri, $limit = PHP_INT_MAX, $offset = 0)
    {
        // Fetch all configured semantic sources
        $semantic_sources = $this->semantic_sources->getAllConfigurations();

        $graph = new \EasyRdf_Graph();

        // Iterate every source and retrieve only the triples with a subject matching the base uri
        foreach ($semantic_sources as $semantic_source) {

            $source_type = $semantic_source['type'];

            $input_graph;

            switch ($source_type) {
                case 'Turtle':

                    $rdf_reader = \App::make('\Tdt\Core\DataControllers\RDFController');

                    $configuration = array(
                        'uri' => $semantic_source['uri'],
                        'format' => 'turtle',
                    );

                    $data = $rdf_reader->readData($configuration, array());
                    $input_graph = $data->data;

                    // Add the correct triples to our resulting graph
                    $graph = $this->addTriplesToGraph($base_uri, $input_graph, $graph);

                    break;
                case 'Rdf':

                    $rdf_reader = \App::make('\Tdt\Core\DataControllers\RDFController');

                    $configuration = array(
                        'uri' => $semantic_source['uri'],
                        'format' => 'xml',
                    );

                    $data = $rdf_reader->readData($configuration, array());
                    $input_graph = $data->data;

                    // Add the correct triples to our resulting graph
                    $graph = $this->addTriplesToGraph($base_uri, $input_graph, $graph);

                    break;
                case 'Sparql':

                    $sparql_reader = \App::make('\Tdt\Core\DataControllers\SparqlController');

                    $configuration = array(
                        'query' => $this->createSparqlQuery($base_uri, @$semantic_source['depth']),
                        'endpoint' => $semantic_source['endpoint'],
                        'endpoint_user' => @$semantic_source['endpoint_user'],
                        'endpoint_password' => @$semantic_source['endpoint_password'],
                    );

                    $data = $sparql_reader->readData($configuration, array());
                    $input_graph = $data->data;

                    // We know that the result will contain all triples that need to be added
                    // so merge the two graphs
                    $graph = $this->mergeGraph($graph, $input_graph);

                    break;
                default:
                    // TODO: change this to a log warning
                    \App::abort(
                        400,
                        "The source type, $source_type, was configured, but no reader has been found
                        to extract semantic data from it."
                    );
                    break;
            }
        }

        // Apply paging (TODO)


        // Return the resulting graph
        return $graph;
    }

    /**
     * Add triples from the input graph,
     * with a subject matching the base uri
     *
     * @param string $base_uri
     * @param EasyRdf_Graph $input
     * @param EasyRdf_Graph $graph
     *
     * @return EasyRdf_Graph
     */
    private function addTriplesToGraph($base_uri, $input, $graph)
    {
        $properties = $input->properties($base_uri);

        foreach ($properties as $property) {

            $result = $input->get($base_uri, $property);

            $predicate = $property;

            $info = $result->toRdfPhp($property);

            // Add the triple, with subject = base uri to our resulting graph
            if ($info['type'] == 'literal') {
                $graph->addLiteral($base_uri, $predicate, $info['value']);
            } else {
                $graph->addResource($base_uri, $predicate, $info['value']);
            }
        }

        return $graph;
    }

    /**
     * Merge two graphs and return the result
     *
     * @param EasyRdf_Graph $graph
     * @param EasyRdf_Graph $input_graph
     *
     * @return EasyRdf_Graph
     */
    private function mergeGraph($graph, $input_graph)
    {
        $turtle_graph = $input_graph->serialise('turtle');

        $graph->parse($turtle_graph, 'turtle');

        return $graph;
    }

    /**
     * Creates a query that fetches all of the triples
     * of which the subject matches the base uri
     *
     * @param string $base_uri
     *
     * @return string
     */
    private function createSparqlQuery($base_uri, $depth = 3)
    {
        $vars = '<'. $base_uri .'> ?p ?o1.';

        $last_object = '?o1';

        for ($i = 2; $i <= $depth; $i++) {
            $vars .= $last_object . ' ?p' . $i . ' ?o' . $i . '. ';

            $last_object = '?o' . $i;
        }

        $construct_statement = 'construct {' . $vars . '}';
        $filter_statement = '{'. $vars . '}';

        return $construct_statement . $filter_statement;
    }

    /**
     * Return the total amount of triples that
     * have a subject that matches base_uri
     *
     * @param $base_uri
     *
     * @return integer
     */
    public function getCount($base_uri)
    {

    }
}
