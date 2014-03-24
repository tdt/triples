<?php

/**
 * Parent class for all of the semantic data sources
 *
 * @license AGPLv3
 * @author Jan Vansteenlandt <jan@okfn.be>
 */
class SemanticSource extends Eloquent
{
    protected $table = 'semanticsources';

    public function source(){
        return $this->morphTo();
    }

    /**
     * Delete the related source type
     */
    public function delete()
    {
        $source_type = $this->source()->first();
        $source_type->delete();

        parent::delete();
    }
}
