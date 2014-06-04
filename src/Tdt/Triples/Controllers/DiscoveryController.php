<?php

namespace Tdt\Triples\Controllers;

/**
 * DiscoveryController
 *
 * @copyright (C) 2011, 2014 by OKFN Belgium vzw/asbl
 * @license AGPLv3
 * @author Jan Vansteenlandt <jan@okfn.be>
 */
class DiscoveryController extends \Controller
{

    public function get($uri)
    {
        // Set permission
        Auth::requirePermissions('discovery.view');

        return $discovery_document = self::createDiscoveryDocument();
    }

    /**
     * Create the discovery document
     */
    public static function createDiscoveryDocument()
    {

        // Create and return a dument that holds a self-explanatory document
        // about how to interface with the datatank
        $discovery_document = new \stdClass();
        $discovery_document->methods = new \stdClass();

        $discovery_document->methods->get = self::createGetDiscovery();
        $discovery_document->methods->post = self::createPostDiscovery();
        $discovery_document->methods->put = self::createPutDiscovery();
        $discovery_document->methods->delete = self::createDeleteDiscovery();

        return $discovery_document;
    }

    /**
     * Create the get discovery documentation.
     */
    private static function createGetDiscovery()
    {
        $get = new \stdClass();

        $get->httpMethod = "GET";
        $get->path = "/triples/{id}";
        $get->description = "Get a semantic source configuration identified by {id}, the id however is optional. If not given all configurations are returned.";

        return $get;
    }

    /**
     * Create the PUT discovery documentation
     *
     * @return \stdClass
     */
    private static function createPutDiscovery()
    {
        $put = new \stdClass();

        $put->httpMethod = "PUT";
        $put->path = "/triples/{id}";
        $put->description = "Replace an existing semantic source identified by id with a new configuration.";

        $put->body = new \stdClass();

        // Get the base properties that can be added to every definition
        $base_properties = array();

        // Fetch all the supported definition models by iterating the models directory
        if ($handle = opendir(__DIR__ . '/../../../models/sourcetypes')) {
            while (false !== ($entry = readdir($handle))) {

                if (preg_match("/(.+)Source\.php/i", $entry, $matches)) {

                    $source_repository = 'Tdt\\Triples\\Repositories\\Interfaces\\' . ucfirst(strtolower($matches[1])) . "SourceRepositoryInterface";

                    $source_repository = \App::make($source_repository);

                    $source_type = strtolower($matches[1]);

                    if (method_exists($source_repository, 'getCreateParameters')) {

                        $put->body->$source_type = new \stdClass();
                        $put->body->$source_type->description = "Create a definition that allows for publication of data inside a $matches[1] datastructure.";

                        // Add the required type parameter
                        $type = array(
                            'source_type' => array(
                                'required' => true,
                                'name' => 'Type',
                                'description' => 'The type of the data source.',
                                'type' => 'string',
                                'value' => $source_type
                            )
                        );

                        $all_properties = array_merge($type, $source_repository->getCreateParameters(), $base_properties);

                        // Fetch the Definition properties, and the SourceType properties, the latter also contains relation properties e.g. TabularColumn properties
                        $put->body->$source_type->parameters = $all_properties;
                    }
                }
            }
            closedir($handle);
        }

        return $put;
    }

    /**
     * Create the POST discovery documentation
     *
     * @return \stdClass
     */
    private static function createPostDiscovery()
    {
        $post = new \stdClass();

        $post->httpMethod = "POST";
        $post->path = "/triples";
        $post->description = "Add a new semantic source.";

        $post->body = new \stdClass();

        // Get the base properties that can be added to every definition
        $base_properties = array();

        // Fetch all the supported definition models by iterating the models directory
        if ($handle = opendir(__DIR__ . '/../../../models/sourcetypes')) {
            while (false !== ($entry = readdir($handle))) {

                if (preg_match("/(.+)Source\.php/i", $entry, $matches)) {

                    $source_repository = 'Tdt\\Triples\\Repositories\\Interfaces\\' . ucfirst(strtolower($matches[1])) . "SourceRepositoryInterface";

                    $source_repository = \App::make($source_repository);

                    $source_type = strtolower($matches[1]);

                    if (method_exists($source_repository, 'getCreateParameters')) {

                        $post->body->$source_type = new \stdClass();
                        $post->body->$source_type->description = "Create a definition that allows for publication of data inside a $matches[1] datastructure.";

                        // Add the required type parameter
                        $type = array(
                            'type' => array(
                                'required' => true,
                                'name' => 'Type',
                                'description' => 'The type of the data source.',
                                'type' => 'string',
                                'value' => $source_type
                            )
                        );

                        $all_properties = array_merge($type, $source_repository->getCreateParameters(), $base_properties);

                        // Fetch the Definition properties, and the SourceType properties, the latter also contains relation properties e.g. TabularColumn properties
                        $post->body->$source_type->parameters = $all_properties;
                    }
                }
            }
            closedir($handle);
        }

        return $post;
    }

    /**
     * Create the DELETE discovery documentation.
     *
     * @return \stdClass
     */
    private static function createDeleteDiscovery()
    {

        $delete = new \stdClass();

        $delete->httpMethod = "DELETE";
        $delete->path = "/triples/{id}";
        $delete->description = "Delete a semantic source identified by the {id} value.";

        return $delete;
    }
}
