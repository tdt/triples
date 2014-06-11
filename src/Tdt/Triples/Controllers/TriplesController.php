<?php

namespace Tdt\Triples\Controllers;

use Tdt\Triples\Repositories\Interfaces\SemanticSourceRepositoryInterface;
use Tdt\Triples\Repositories\Interfaces\TripleRepositoryInterface;
use Tdt\Core\ContentNegotiator;
use Tdt\Core\Datasets\Data;
use Tdt\Core\Auth\Auth;

class TriplesController extends \Controller
{

    protected $semantic_source;
    protected $triple_store;

    public function __construct(SemanticSourceRepositoryInterface $semantic_source, TripleRepositoryInterface $triple_store)
    {
        $this->semantic_source = $semantic_source;
        $this->triple_store = $triple_store;
    }

    public function handle($id = null)
    {
        // Delegate the request based on the used http method
        $method = \Request::getMethod();

        switch($method){
            case "PUT":
                return $this->put($id);
                break;
            case "GET":
                return $this->get($id);
                break;
            case "POST":

                if (!empty($id)) {
                    // Don't allow POSTS to specific configurations (only PUT and DELETE allowed)
                    \App::abort(405, "The HTTP method POST is not allowed on this resource. POST is only allowed on api/triples.");
                }

                return $this->post();
                break;
            case "PATCH":
                return $this->patch();
                break;
            case "DELETE":
                return $this->delete($id);
                break;
            case "HEAD":
                return $this->head();
                break;
            default:
                // Method not supported
                \App::abort(405, "The HTTP method '$method' is not supported by this resource.");
                break;
        }
    }

    /**
     * Get all of the configured semantic sources
     *
     * @return \Response
     */
    public function get($id = null)
    {
        Auth::requirePermissions('definitions.view');

        // If the id isn't empty
        if (!empty($id)) {
            $data = $this->semantic_source->getSourceConfiguration($id);
        } else {
            $data = $this->semantic_source->getAllConfigurations();
        }

        $result = new Data();

        $result->data = $data;

        return ContentNegotiator::getResponse($result, 'json');
    }

    /**
     * Create a new semantic source from which triples can be retrieved
     *
     * @return \Response
     */
    public function post()
    {
        // Use the core permissions for now to allow creation
        Auth::requirePermissions('dataset.create');

        // Retrieve the input from the request.
        $input = $this->fetchInput();

        // Store the new configuration
        $result = $this->semantic_source->store($input);

        if (!empty($result) && is_array($result)) {

            $response = \Response::make("", 200);

            $response->header('Location', \URL::to('api/triples'));

            return $response;
        } else {
            \App::abort(500, "An unknown error occurred, the semantic configuration could not be stored.");
        }
    }

    /**
     * Update an existing semantic source
     *
     * @param integer $id The id of the semantic source that needs to be updated
     *
     * @return \Response
     */
    public function put($id = null)
    {
        // Use the core package's authentication for now
        Auth::requirePermissions('dataset.create');

        // If id is null, abort
        if (is_null($id)) {
            \App::abort(404, "Please provide a fitting id with the request, the id we found is null.");
        }

        $input = $this->fetchInput();

        $result = $this->semantic_source->update($id, $input);

        if (!empty($result) && is_array($result)) {

            $response = \Response::make("", 200);
            $response->header('Location', \URL::to('api/triples'));

            return $response;
        } else {
            \App::abort(500, "An unknown error occurred, the semantic configuration could not be stored.");
        }

        $response = \Response::make("", 200);
        $response->header('Location', \URL::to('api/triples'));

        return $response;
    }

    public function patch()
    {
        \App::abort(405, "The HTTP method patch is not supported by this resource.");
    }

    public function head()
    {
        \App::abort(405, "The HTTP method head is not supported by this resource.");
    }

    /**
     * Delete a configured semantic source
     *
     * @param integer $id The id of the semantic source
     *
     * @return \Response
     */
    public function delete($id)
    {
        Auth::requirePermissions('dataset.delete');

        $result = $this->semantic_source->delete($id);

        // Delete the corresponding graph that cached the triples
        $this->triple_store->removeTriples($id);

        if ($result) {
            $response = \Response::make("", 200);
        } else {
            $response = \Response::make("", 404);
        }

        return $response;
    }

    /**
     * Retrieve the input of the request and lowercase all of the keys
     *
     * @return array
     */
    private function fetchInput()
    {
        // Retrieve the parameters of the PUT requests (either a JSON document or a key=value string)
        $input = \Request::getContent();

        // Is the body passed as JSON, if not try getting the request parameters from the uri
        if (!empty($input)) {
            $input = json_decode($input, true);
        } else {
            $input = \Input::all();
        }

        // If input is empty, then something went wrong
        if (empty($input)) {
            \App::abort(400, "The parameters could not be parsed from the body or request URI, make sure parameters are provided and if they are correct (e.g. correct JSON).");
        }

        // Change all of the parameters to lowercase
        $input = array_change_key_case($input);

        return $input;
    }
}
