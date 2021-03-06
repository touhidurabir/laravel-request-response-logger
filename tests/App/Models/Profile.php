<?php

namespace Touhidurabir\RequestResponseLogger\Tests\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profile extends Model {

    use SoftDeletes;

    /**
     * The model associated table
     *
     * @var string
     */
    protected $table = 'profiles';


    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

}