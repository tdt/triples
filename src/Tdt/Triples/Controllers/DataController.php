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

            $cache_string = sha1($this->getRawRequestURI(\Request::url()));

            // Check cache
            if (Cache::has($cache_string)) {
                $data = Cache::get($cache_string);
            } else {

                $base_uri = \URL::to($identifier);

                if (empty($base_uri)) {
                    $base_uri = \Request::root();
                }

                list($limit, $offset) = Pager::calculateLimitAndOffset();

                $result = $this->triples->getTriples($base_uri, 100, $offset);

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

        // Add the hydra namespace, it's not present in the easy rdf namespaces by default
        \EasyRdf_Namespace::set('hydra', 'http://www.w3.org/ns/hydra/core#');

        // Return the formatted response with content negotiation
        return ContentNegotiator::getResponse($data, $extension);
    }

    public function solveQuery($format = null)
    {
        $data;

        if (!empty($format)) {
            $format = ltrim($format, '.');
        }

        // Ignore the rest of the uri after /all
        $cache_string = sha1($this->getRawRequestURI(\Request::root()));

        // Check cache
        if (Cache::has($cache_string)) {
            $data = Cache::get($cache_string);
        } else {

            list($s, $p, $o) = $this->getTemplateParameters();

            SparqlQueryBuilder::setParameters(array($s, $p, $o));

            $base_uri = null;

            if ($s == '?s' && $p == '?p' && $o == '?o') {
                $base_uri = \Request::root();
            }

            $result = $this->triples->getTriples(
                $base_uri,
                \Request::get('limit', 100),
                \Request::get('offset', 0)
            );

            // If the graph contains no triples, then the uri couldn't resolve to anything, 404 it is
            if ($result->countTriples() == 0) {
                \App::abort(404, "The resource couldn't be found, nor dereferenced.");
            }

            $definition = array(
                'resource_name' => "all",
                'collection_uri' => "",
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

        // Add the hydra namespace, it's not present in the easy rdf namespaces by default
        \EasyRdf_Namespace::set('hydra', 'http://www.w3.org/ns/hydra/core#');

        \Log::info("The full url that triples received was: " . \Request::fullUrl() . " and the format passed to the negotiator was " . $format . ".");

        // Return the formatted response with content negotiation
        return ContentNegotiator::getResponse($data, $format);
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
