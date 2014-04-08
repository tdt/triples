<?php

namespace Tdt\Triples\Controllers;

use Tdt\Triples\Repositories\Interfaces\TripleRepositoryInterface;
use Tdt\Core\Repositories\Interfaces\DefinitionRepositoryInterface;
use Tdt\Core\ContentNegotiator;
use Tdt\Core\Datasets\Data;
use Tdt\Core\Formatters\FormatHelper;

/**
 * DataController checks if the core application can resolve
 * the uri. If not, the entire base uri forms a subject of which
 * all corresponding triples of all configured semantic sources are returned.
 *
 * @license AGPLv3
 * @author Jan Vansteenlandt <jan@okfn.be>
 */
class DataController extends \Controller
{
    protected $triples;

    public function __construct(TripleRepositoryInterface $triples, DefinitionRepositoryInterface $definition)
    {
        $this->triples = $triples;
        $this->definition = $definition;
    }

    public function resolve($identifier)
    {
        // Split the uri to check for an (optional) extension (=format)
        preg_match('/([^\.]*)(?:\.(.*))?$/', $identifier, $matches);

        // URI is always the first match
        $identifier = $matches[1];

        $data;

        // Get extension
        $extension = (!empty($matches[2]))? $matches[2]: null;

        if ($this->definition->exists($identifier)) {

            $controller = \App::make('Tdt\Core\Datasets\DatasetController');

            $data = $controller->fetchData($identifier);

            $definition = $this->definition->getByIdentifier($identifier);
            $data->definition = $definition;
            $data->source_definition = $this->definition->getDefinitionSource($definition['source_id'], $definition['source_type']);

            $format_helper = new FormatHelper();
            $data->formats = $format_helper->getAvailableFormats($data);

        } else {

            $base_uri = \URL::to($identifier);

            $result = $this->triples->getTriples($base_uri);

            // If the graph contains no triples, then the uri couldn't resolve to anything, 404 it is
            if ($result->countTriples() == 0) {
                \App::abort(404, "The resource couldn't be found, nor dereferenced.");
            }

            // Mock a tdt/core definition object that is used in the formatters
            $identifier_pieces = explode('/', $identifier);

            $resource_name = array_pop($identifier_pieces);
            $collection_uri = implode('/', $identifier_pieces);

            $definition = array(
                'resource_name' => $resource_name,
                'collection_uri' => $collection_uri,
            );

            $source_definition = array(
                'description' => 'Semantic data collected out the configuration of semantic data sources related to the given URI.',
                'type' => 'Semantic',
            );

            $data = new Data();
            $data->definition = $definition;
            $data->source_definition = $source_definition;
            $data->data = $result;
            $data->is_semantic = true;
        }

        // Return the formatted response with content negotiation
        return ContentNegotiator::getResponse($data, $extension);
    }
}
