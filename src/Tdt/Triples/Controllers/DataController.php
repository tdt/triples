<?php

namespace Tdt\Triples\Controllers;

use Tdt\Triples\Repositories\Interfaces\TripleRepositoryInterface;
use Tdt\Triples\Repositories\SparqlQueryBuilder;
use Tdt\Core\Repositories\Interfaces\DefinitionRepositoryInterface;
use Tdt\Core\ContentNegotiator;
use Tdt\Core\Datasets\Data;
use Tdt\Core\Formatters\FormatHelper;
use Tdt\Core\Cache\Cache;
use Tdt\Core\Pager;

/**
 * The DataController class checks if the core application can resolve
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

    /**
     * Dereferences a URI only when the core application has no resource attributed to it
     *
     * @string $identifier The path of the URL
     *
     * @return \Response
     */
    public function resolve($identifier)
    {
        // Split the identifier from it's format (URI, json)
        // Caveat: there is a small chance that e.g. URI.json is the full URI that
        // needs to be dereferenced. (https://github.com/tdt/triples/issues/48)
        list($identifier, $extension) = $this->processURI($identifier);

        $data;

        // If the identifier represents a dataset in core, ask core to deliver a response
        if ($this->isCoreDataset($identifier)) {

            $controller = \App::make('Tdt\Core\Datasets\DatasetController');

            $data = $controller->fetchData($identifier);

            $definition = $this->definition->getByIdentifier($identifier);
            $data->definition = $definition;
            $data->source_definition = $this->definition->getDefinitionSource($definition['source_id'], $definition['source_type']);

            $format_helper = new FormatHelper();
            $data->formats = $format_helper->getAvailableFormats($data);

        // The identifier can be a core non-dataset resource (e.g. discovery)
        } else if ($this->isCoreResource($identifier)) {

            $controller = \App::make('Tdt\Core\BaseController');

            return $controller->handleRequest($identifier);

        // Could be a collection
        } else if ($this->isCoreCollection($identifier)) {

            // Coulnd't find a definition, but it might be a collection
            $resources = $this->definition->getByCollection($identifier);

            $data = new Data();
            $data->data = new \stdClass();
            $data->data->datasets = array();
            $data->data->collections = array();

            if (count($resources) > 0) {

                foreach ($resources as $res) {

                    // Check if it's a subcollection or a dataset
                    $collection_uri = rtrim($res['collection_uri'], '/');
                    if ($collection_uri == $identifier) {
                        array_push($data->data->datasets, \URL::to($collection_uri . '/' . $res['resource_name']));
                    } else {
                        // Push the subcollection if it's not already in the array
                        if (!in_array(\URL::to($collection_uri), $data->data->collections)) {
                            array_push($data->data->collections, \URL::to($collection_uri));
                        }
                    }
                }
            }

            // Fake a definition
            $data->definition = new \Definition();
            $uri_array = explode('/', $identifier);
            $last_chunk = array_pop($uri_array);

            $data->definition->collection_uri = join('/', $uri_array);
            $data->definition->resource_name = $last_chunk;

        // Nothing works out, try to dereference the URI
        } else {

            // Rebuild the URI as is, the Symfony Request components url-decode everything
            // Dereferencing however needs to deal with the exact request URI's
            $cache_string = sha1($this->getRawRequestURI(\Request::url()));

            // Check if the cache already contains the dereferencing info
            if (Cache::has($cache_string)) {
                $data = Cache::get($cache_string);
            } else {

                $base_uri = \URL::to($identifier);

                if (empty($base_uri)) {
                    $base_uri = \Request::root();
                }

                // Calculate the limit and offset parameters
                list($limit, $offset) = Pager::calculateLimitAndOffset();

                // Fetch the triples that can be used to dereference the URI
                $result = $this->triples->getTriples($base_uri, 100, $offset, true);

                // If the graph contains no triples, then the URI couldn't resolve to anything, 404 it is
                if ($result->countTriples() == 0) {
                    \App::abort(404, "The resource could not be dereferenced. No matching triples were found.");
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
                    'description' => 'Semantic data collected retrieved from the configured semantic sources.',
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

        // Add the hydra namespace, it's not present in the easy rdf namespaces by default
        \EasyRdf_Namespace::set('hydra', 'http://www.w3.org/ns/hydra/core#');

        // Return the formatted response with content negotiation
        $response = ContentNegotiator::getResponse($data, $extension);

        $response->header('Vary', 'Accept');

        // Allow CORS
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }

    /**
     * Resolve a graph pattern query (/all route)
     *
     * @param string $format The format of the request
     *
     * @return \Response
     */
    public function solveQuery($format = null)
    {
        $data;

        if (!empty($format)) {
            $format = ltrim($format, '.');
        }

        // Ignore the rest of the uri after /all and work with the request parameters as they were given
        $cache_string = sha1($this->getRawRequestURI(\Request::root()));

        // Check if the response to the query has been cached already
        if (Cache::has($cache_string)) {
            $data = Cache::get($cache_string);
        } else {

            // Get the graph pattern query string parameters from the request
            list($s, $p, $o) = $this->getTemplateParameters();

            // Pass them to our sparql query builder
            SparqlQueryBuilder::setParameters(array($s, $p, $o));

            $base_uri = null;

            // If no parameter has been filled in, the URI we have to match triples with is the root of our application
            if ($s == '?s' && $p == '?p' && $o == '?o') {
                $base_uri = \Request::root();
            }

            // Fetch matching triples
            $result = $this->triples->getTriples(
                $base_uri,
                \Request::get('limit', 100),
                \Request::get('offset', 0)
            );

            // If the graph contains no triples, then the graph pattern couldn't resolve to anything, 404 it is
            if ($result->countTriples() == 0) {
                \App::abort(404, "The resource couldn't be found, nor dereferenced.");
            }

            $definition = array(
                'resource_name' => "all",
                'collection_uri' => "",
            );

            $source_definition = array(
                'description' => 'Semantic data collected out the configured semantic data sources.',
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

        // Add the hydra namespace, it's not present in the easy rdf namespaces by default
        \EasyRdf_Namespace::set('hydra', 'http://www.w3.org/ns/hydra/core#');

        // Return the formatted response with content negotiation
        $response = ContentNegotiator::getResponse($data, $format);

        // Pass a Vary header so that browsers know they have to take the accept header
        // into consideration when they apply caching client side
        $response->header('Vary', 'Accept');

        // Allow CORS
        $response->header('Access-Control-Allow-Origin', '*');

        return $response;
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

        if (substr($p, 0, 4) == "http") {
            $p = '<' . $p . '>';
        }

        if (substr($o, 0, 4) == "http") {
            $o = '<' . $o . '>';
        } else if ($o != '?o' && substr($o, 0, 5) != '<http') {
            // If the object isn't URI, enquote it, unless it's meant as a sparql variable or an enclosed URI

            // Make sure it isn't already enquoted
            $o = rtrim($o, '"');
            $o = ltrim($o, '"');

            $o = '"' . $o . '"';
        }

        return array($s, $p, $o);
    }

    /**
     * Return the original request URI, root stays the same, query string parameters are
     * return as they were in the correct order, without encoding/decoding changes
     *
     * @param string $url The request URI without any query string parameters
     *
     * @return string
     */
    public function getRawRequestURI($url)
    {
        $query_string = $_SERVER['QUERY_STRING'];

        $query_parts = explode('&', $query_string);

        $raw_query_string = '';

        foreach ($query_parts as $part) {

            if (!empty($part)) {

                $couple = explode('=', $part);

                $raw_query_string .= $couple[0] . '=' . $couple[1] . '&';
            }
        }

        if (!empty($raw_query_string)) {
            $url .= '?' . $raw_query_string;

            $url = rtrim($url, '?');

            $url = rtrim($url, '&');
        }

        $url = str_replace('#', '%23', $url);

        return $url;
    }

    /**
     * Process the URI and return the extension (=format) and the resource identifier URI
     *
     * @param string $uri The URI that has been passed
     * @return array
     */
    private function processURI($uri)
    {
        $dot_position = strrpos($uri, '.');

        if (!$dot_position) {
            return array($uri, null);
        }

        // If a dot has been found, do a couple
        // of checks to find out if it introduces a formatter
        $uri_parts = explode('.', $uri);

        $possible_extension = array_pop($uri_parts);

        $possible_ext_class = strtoupper($possible_extension);

        $uri = implode('.', $uri_parts);

        $formatter_class = 'Tdt\\Core\\Formatters\\' . $possible_ext_class . 'Formatter';

        if (!class_exists($formatter_class)) {

            // Re-attach the dot with the latter part of the uri
            $uri .= '.' . $possible_extension;

            return array($uri, null);
        }

        return array($uri, $possible_extension);
    }

    /**
     * Check if the identifier that has been passed is resolvable as a core resource
     *
     * @param string $identifier The URI identifier that is possibly a core source in the tdt/core (e.g. discovery)
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

    /**
     * Check if the identifier is part of a collection name
     *
     * @param string $collection
     *
     * @return boolean
     */
    private function isCoreCollection($collection)
    {
        $result = $this->definition->getByCollection($collection);

        return !empty($result);
    }
}
