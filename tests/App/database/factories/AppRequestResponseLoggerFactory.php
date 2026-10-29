<?php

namespace Database\Factories\Touhidurabir\RequestResponseLogger\Models;

use Touhidurabir\RequestResponseLogger\Database\Factories\RequestResponseLoggerFactory as PackageFactory;

/**
 * A factory an app defines for the package model, at the name Laravel guesses for it
 */
class RequestResponseLoggerFactory extends PackageFactory
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
