<?php

namespace Tdt\Triples\Repositories;

use Tdt\Triples\Repositories\Interfaces\LdfSourceRepositoryInterface;
use Tdt\Triples\Repositories\BaseSourceRepository;

class LdfSourceRepository extends BaseSourceRepository implements LdfSourceRepositoryInterface
{
    protected $rules = array(
        'startfragment' => 'required',
    );

    public function __construct(\LdfSource $model)
    {
        $this->model = $model;
    }

    /**
     * Update a turtle source configuration
     *
     * @param array $input
     *
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
    public function getCreateParameters()
    {
        return array(
            'startfragment' => array(
                'required' => true,
                'name' => 'Startfragment',
                'description' => 'The LDF startfragment of the LDF server.',
                'type' => 'string',
            ),
        );
    }
}
