<?php

/**
 * Parent class for all of the semantic data sources
 *
 * @license AGPLv3
 * @author Jan Vansteenlandt <jan@okfn.be>
 */
class SemanticSource extends Eloquent
{
    protected $table = 'semantic_sources';

    public function source(){
        return $this->morphTo();
    }
}
