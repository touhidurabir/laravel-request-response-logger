<?php

namespace Touhidurabir\RequestResponseLogger\Tests\App\Factories;

use Touhidurabir\RequestResponseLogger\Database\Factories\RequestResponseLoggerFactory;

/**
 * A factory an app defines for the package model
 */
class AppRequestResponseLoggerFactory extends RequestResponseLoggerFactory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {

        return array_merge(parent::definition(), ['request_method' => 'APP']);
    }
}
