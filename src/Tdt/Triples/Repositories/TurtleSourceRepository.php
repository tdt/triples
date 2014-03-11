<?php

namespace Tdt\Triples\Repositories;

use Tdt\Triples\Repositories\Interfaces\TurtleSourceRepositoryInterface;
use Tdt\Triples\Repositories\BaseSourceRepository;

class TurtleSourceRepository extends BaseSourceRepository implements TurtleSourceRepositoryInterface
{

    protected $rules = array(
        'uri' => 'required|uri',
    );

    public function __construct(\TurtleSource $model)
    {
        $this->model = $model;
    }

    /**
     * Update a turtle source configuration
     *
     * @param array $input
     * @return array Model
     */
    public function update(array $input)
    {

    }

    /**
     * Return an array of create parameters with info attached
     * e.g. array( 'create_parameter' => array(
     *              'required' => true,
     *              'description' => '...',
     *              'type' => 'string',
     *              'name' => 'pretty name'
     *       ), ...)
     *
     * @return array
     */
    public function getCreateParameters(){
        return array(
            'uri' => array(
                'required' => true,
                'name' => 'URI',
                'description' => 'The location of the turtle file, either a URL or a local file location.',
                'type' => 'string',
            ),
        );
    }
}
