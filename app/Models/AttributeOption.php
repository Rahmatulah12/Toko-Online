<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeOption extends Model
{
    protected $table = 'attribute_options';

    protected $fillable = [
        'attribute_id', 'name'
    ];

    /**
     * relation to attribute many to one
     */
    public function attribute()
    {
        return $this->belongsTo('App\Models\Attribute');
    }
}