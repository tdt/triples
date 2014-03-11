<?php

namespace Tdt\Triples\Controllers;

use Tdt\Core\BaseController;
use Tdt\Triples\Repositories\Interfaces\TripleRepositoryInterface;
use Tdt\Core\ContentNegotiator;
use Tdt\Core\Datasets\Data;

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

    public function __construct(TripleRepositoryInterface $triples)
    {
        $this->triples = $triples;
    }

    public function resolve($identifier)
    {
        // Check if the identifier resolve to a definition
        $definition_repository = \App::make('Tdt\Core\Repositories\Interfaces\DefinitionRepositoryInterface');


        if ($definition_repository->exists($identifier)) {

            $core_controller = new BaseController();
            return $core_controller->handleRequest($identifier);

        }else{

            $base_uri = \URL::to($identifier);

            $result = $this->triples->getAll($base_uri);

            $data = new Data();
            $data->data = $result;
            $data->is_semantic = true;

            // Return the formatted response with content negotiation
            return ContentNegotiator::getResponse($data, 'ttl');
        }
    }
}
