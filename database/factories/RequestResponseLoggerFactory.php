<?php

namespace Touhidurabir\RequestResponseLogger\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Touhidurabir\RequestResponseLogger\Models\RequestResponseLogger;

class RequestResponseLoggerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RequestResponseLogger::class;


    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {

        return [
            'request_method'        => $this->faker->randomElement(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
            'request_headers'       => ['accept' => 'application/json', 'user-agent' => $this->faker->userAgent()],
            'request_body'          => null,
            'request_url'           => $this->faker->url(),
            'request_ip'            => $this->faker->ipv4(),
            'request_auth_user_id'  => null,
            'response_headers'      => ['content-type' => 'application/json'],
            'response_body'         => json_encode(['status' => true]),
            'response_status_code'  => (string) $this->faker->randomElement([200, 201, 204, 400, 401, 404, 422, 500]),
            'marked'                => false,
        ];
    }


    /**
     * Indicate that the log is marked.
     *
     * @return static
     */
    public function marked()
    {

        return $this->state(function (array $attributes) {
            return ['marked' => true];
        });
    }
}
