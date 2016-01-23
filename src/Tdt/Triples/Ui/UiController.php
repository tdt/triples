<?php

/**
 * @copyright (C) 2011, 2014 by OKFN Belgium vzw/asbl
 * @license AGPLv3
 * @author Michiel Vancoillie <michiel@okfn.be>
 */
namespace Tdt\Triples\Ui;

use Tdt\Core\Auth\Auth;

class UiController extends \Controller
{

    protected static $controller;

    /**
     * Request handeling
     */
    public static function handle($uri)
    {
        self::$controller = \App::make('Tdt\Triples\Controllers\TriplesController');

        switch ($uri) {
            case 'triples':
                // Set permission
                Auth::requirePermissions('tdt.triples.view');

                // Get list of triples
                return self::listTriples();
                break;

            case 'triples/add':
                // Set permission
                Auth::requirePermissions('tdt.triples.create');

                // Create new triple
                return self::addTriple();
                break;

            case (preg_match('/^triples\/delete/i', $uri) ? true : false):
                // Set permission
                Auth::requirePermissions('tdt.triples.delete');
                // Delete a triple
                return self::deleteTriple($uri);
                break;

        }

        return false;
    }

    /**
     * Define menu items
     */
    public static function menu()
    {
        return array(
            array(
                'title' => 'Triples',
                'slug' => 'triples',
                'permission' => 'tdt.triples.view',
                'icon' => 'fa-share-alt',
                'priority' => 40
                ),
        );
    }

    /**
     * Triples list
     */
    private static function listtriples()
    {
        // Get list of triples
        $triples = json_decode(self::$controller->get()->getContent());

        return \View::make('triples::list')
                    ->with('title', 'Triples management | The Datatank')
                    ->with('triples', $triples);
    }

    /**
     * Add a triples
     */
    private static function addTriple()
    {
        $discovery = \App::make('Tdt\Core\Definitions\DiscoveryController');
        $discovery = json_decode($discovery->get()->getcontent());

        // Get spec for media types
        $triples_spec = $discovery->resources->triples->methods->post->body;

        return \View::make('triples::add')
                    ->with('title', 'New triple | The Datatank')
                    ->with('triples_spec', $triples_spec);
    }

    /**
     * Delete a triple
     */
    private static function deleteTriple($uri)
    {
        // Get the id
        $id = str_replace('triples/delete/', '', $uri);

        if (is_numeric($id)) {
            self::$controller->delete($id);

            return \Redirect::to('api/admin/triples');
        } else {
            return false;
        }
    }
}
