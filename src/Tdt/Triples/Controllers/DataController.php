<?php

namespace Tdt\Triples\Controllers;

use Tdt\Triples\Repositories\Interfaces\TripleRepositoryInterface;
use Tdt\Core\Repositories\Interfaces\DefinitionRepositoryInterface;
use Tdt\Core\ContentNegotiator;
use Tdt\Core\Datasets\Data;
use Tdt\Core\Formatters\FormatHelper;
use Tdt\Core\Cache\Cache;

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

        if ($this->isCoreDataset($identifier)) {

            $controller = \App::make('Tdt\Core\Datasets\DatasetController');

            $data = $controller->fetchData($identifier);

            $definition = $this->definition->getByIdentifier($identifier);
            $data->definition = $definition;
            $data->source_definition = $this->definition->getDefinitionSource($definition['source_id'], $definition['source_type']);

            $format_helper = new FormatHelper();
            $data->formats = $format_helper->getAvailableFormats($data);

        } else if ($this->isCoreResource($identifier)) {

            $controller = \App::make('Tdt\Core\BaseController');

            return $controller->handleRequest($identifier);

        } else {

            $cache_string = sha1(\Request::fullUrl());

            // Check cache
            if (Cache::has($cache_string)) {
                $data = Cache::get($cache_string);
            } else {

                $base_uri = \URL::to($identifier);

                $result = $this->triples->getTriples($base_uri, $this->getTemplateParameters());

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

                // Add the available, supported formats to the object
                $format_helper = new FormatHelper();
                $data->formats = $format_helper->getAvailableFormats($data);

                // Store in cache for a default of 5 minutes
                Cache::put($cache_string, $data, 5);
            }
        }

        // Return the formatted response with content negotiation
        return ContentNegotiator::getResponse($data, $extension);
    }

    /**
     * Get the template parameters from the request (predicate, object)
     * predicate defaults to ?p and object to ?o
     *
     * @return array
     */
    private function getTemplateParameters()
    {
        list($s, $p, $o) = array(
                                \Request::query('subject', '?s'),
                                \Request::query('predicate', '?p'),
                                \Request::query('object', '?o')
                            );

        if (substr($s, 0, 4) == "http") {
            $s = '<' . $s . '>';
        }

        // TODO expand prefixes
        if (substr($p, 0, 4) == "http") {
            $p = '<' . $p . '>';
        }

        if (substr($o, 0, 4) == "http") {
            $o = '<' . $o . '>';
        } else if ($o != '?o' && substr($o, 0, 5) != '<http') {
            // If the object isn't URI, enquote it, unless it's meant as a sparql variable
            $o = '"' . $o . '"';
        }

        return array($s, $p, $o);
    }

    /**
     * Check if the identifier that has been passed is resolvable as a core resource
     *
     * @param string $identifier The URI identifier of the resource to resolve
     *
     * @return boolean
     */
    private function isCoreResource($identifier)
    {
        // Get the discovery controller
        $discovery = \App::make('Tdt\Core\Definitions\DiscoveryController');

        $discovery_document = $discovery->createDiscoveryDocument();

        // Check if the first part of the identifier is part of the core resources
        $core_resources = array_keys(get_object_vars($discovery_document->resources));

        $parts = explode('/', $identifier);
        $prefix = array_shift($parts);

        if (in_array($prefix, $core_resources)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the identifier is a core published dataset
     *
     * @param string $identifier
     *
     * @return boolean
     */
    private function isCoreDataset($identifier)
    {
        return $this->definition->exists($identifier);
    }
}
