<?php

/**
 * Base class for an instance of a semantic data source
 * e.g. Turtle, Sparql, ...
 *
 * @license AGPLv3
 * @author Jan Vansteenlandt <jan@okfn.be>
 */
class SemanticDataSource extends Eloquent
{

    protected $appends = array('type');

    public function getTypeAttribute()
    {
        return str_replace('Source', '', get_called_class());
    }
}
