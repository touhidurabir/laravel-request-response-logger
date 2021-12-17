<?php

namespace Touhidurabir\RequestResponseLogger\Models;

use Touhidurabir\ModelUuid\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Touhidurabir\RequestResponseLogger\Casts\JsonToArray;

class RequestResponseLogger extends Model {
    
    use SoftDeletes;
    use HasFactory;
    use HasUuid;

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];


    /**
     * The attributes that should be cast to proper type
     *
     * @var array
     */
    protected $casts = [
        'request_headers'   => JsonToArray::class,
        'request_body'      => JsonToArray::class,
        'response_headers'  => JsonToArray::class,
        'response_body'     => JsonToArray::class,
    ];


    /**
     * The uuid custom configurations
     *
     * @return array
     */
    public function uuidable() : array {

        return [
            'column' => 'uuid'
        ];
    }
    

    /**
     * Get the model associated table name
     *
     * @return string
     */
    public function getTable() {

        return config('request-response-logger.table', parent::getTable());
    }
    

    /**
     * Local scope a query to only include failed status code responses.
     *
     * @param  object<\Illuminate\Database\Eloquent\Builder>  $query
     * @return object<\Illuminate\Database\Eloquent\Builder>
     */
    public function scopeFailed(Builder $query) {

        return $query->whereNotBetween('response_status_code', [200, 299]);
    }
    

    /**
     * Local scope a query to only include success status code responses.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSuccessful(Builder $query) {

        return $query->whereBetween('response_status_code', [200, 299]);
    }
    

    /**
     * Local scope a query to only include marked records
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMarked(Builder $query) {

        return $query->where('marked', true);
    }
    
}