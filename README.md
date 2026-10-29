# Laravel Request Response Logger

A PHP laravel package to log the request/response of an app in database in an elegant way with the utilization of Queue and Redis combined . 


# Why ?

The first question that with come into anyones' mind is WHY ? There are probably a good number of such package as this one . Also this is not a very diffuclt stuff for anyone to implement into their app . And I do agree with it . 

However this package aims to solve one crucial issue that is continious Database write. With the combination of Laravel's excellent Queuing system and Redis list storage , it can handle that case where it put each log data into the redis and then only when a certain limit hits, it will move those data into the DB table in batch which perform a lot less write opetation . It is not required on have the redis or queue must for this package to perform the loggin operation but that is what make this package unique . 

For example, lets say you have an app that have one endpoint/route that has need a request/response logging and one average it get hit for like 100/hour . So in traditional way , that just 100 write to DB per hours and that totally acceptable. Now on a high traffic day once or twice a month it get hits 1000 per hours so that 1000 writes to DB which is not too much but still a lot as there are also other read/write from other sections of the app . Using a queue job we can push those DB write task to jobs but thats 1000 jobs per hours which is still a bit unrealistic. But using the Redis list , we can push all those task to redis list and then once a certain limit or time passed , we import those records from redis list to DB through the same job in batch . 

This package also contains several handly commands to export data or delete which make use of laravel's LazyCollection and unique appraoch to delete massive amount of data as describe in [Freek's Blog Post](https://flareapp.io/blog/7-how-to-safely-delete-records-in-massive-tables-on-aws-using-laravel)


## Installation

Require the package using composer:

```bash
composer require touhidurabir/laravel-request-response-logger
```

To publish the config and migration file:
```bash
php artisan vendor:publish --provider="Touhidurabir\RequestResponseLogger\RequestResponseLoggerServiceProvider"
```

Next, it's required to run the migration for the new database table. Run the following in your console:
```bash
php artisan migrate
```

## Usage

### Configure the Options

Before using it , please make sure to go throught he pushlished config file to know the available options/settings that you can modify as per your need as this package provide a lot of flexibility. 

For example, the ability to push the write task to queue jobs defined by option **`log_on_queue`** and utilize the redis capability defined by option **`store_on_redis`** are set to **`false`** . So depending on your app capabilities and requirements, configure the options as you see fit . 

The request body fields listed in the **`hidden_request_fields`** option are never stored. By default those are **`password`**, **`password_confirmation`** and **`current_password`**. Add any other sensitive field of your app there; nested fields can be defined with "dot" notation, like **`user.password`**. In the same way, the request and response headers listed in the **`hidden_headers`** option are never stored. By default those are the headers that carry credentials: **`authorization`**, **`cookie`**, **`set-cookie`**, **`php-auth-pw`**, **`x-csrf-token`** and **`x-xsrf-token`**.

The redis connection used by **`store_on_redis`** is defined by the **`redis_configs`** option. It is either an array of connection details (host, port, database, etc.), used as a dedicated connection with the **`client`** and **`options`** of **`config/database.php`**, or the name of a redis connection defined in **`config/database.php`**, like **`'default'`** or **`'cache'`**.

### Setting Up Middleware 

The most important part is is setting up the middleware as all the request/response loggin is done by the provided middleware **`\Touhidurabir\RequestResponseLogger\Middlewares\LogRequestResponse`** by the package . 

There are several ways to use the middleare like registering it in the **`/app/Http/Kernel.php`** file and then use it like 

Register it as a named middleare and then use it as you like only for the routes where it's needed 

```php
protected $routeMiddleware = [
    ...
    'requests.responses.logger' => \Touhidurabir\RequestResponseLogger\Middlewares\LogRequestResponse::class,
];

// And then use it for one or more routes
Route::any('/some-route', SomeController::class)->middleware(['requests.responses.logger']);
```

Or register it as route middleware group 

```php
protected $middlewareGroups = [
    ...
    'api' => [
        \Touhidurabir\RequestResponseLogger\Middlewares\LogRequestResponse::class,
    ],
];
```

Or register it so that by default it apply for every route 

```php
protected $middleware = [
    ...
    \Touhidurabir\RequestResponseLogger\Middlewares\LogRequestResponse::class,
];
```

On Laravel 11 and newer, which have no **`/app/Http/Kernel.php`**, register it in **`bootstrap/app.php`** instead

```php
->withMiddleware(function (Middleware $middleware) {
    // as a named middleware
    $middleware->alias([
        'requests.responses.logger' => \Touhidurabir\RequestResponseLogger\Middlewares\LogRequestResponse::class,
    ]);

    // or for a middleware group
    $middleware->appendToGroup('api', \Touhidurabir\RequestResponseLogger\Middlewares\LogRequestResponse::class);

    // or for every route
    $middleware->append(\Touhidurabir\RequestResponseLogger\Middlewares\LogRequestResponse::class);
})
```

Thats it. 

> NOTE : It is very unrealistic that this there may be a need to log request/response for every routes of an app, though that depends on the need to application. Register the middleare as per your need and try to avoid it using a global middleare unless that what the app require . 


### Register and Run available commands

This package ships with several userful commands . such as 

> NOTE : On Laravel 11 and newer, which have no **`\App\Console\Kernel`** class, schedule the commands below in **`routes/console.php`** instead, e.g. **`Schedule::command('request-response-logger:clear')->weekly();`** .

#### Log Cleaner

To clear up the logs, use the command **`request-response-logger:clear`** as 

```bash
php artisan request-response-logger:clear
```

Or to set up the cleaning process periodically, register it in **`\App\Console\Kernel`** class's **`schedule`** method as : 

```php
protected function schedule(Schedule $schedule) {
    ...
    $schedule->command('request-response-logger:clear')->weekly();
}
```

This package also have some handle options available as such : 
| Command        | Type    | Description                                                      |
| ---------------|---------|------------------------------------------------------------------|
| keep-till-last | value   | Keep the record that has stored in the last given hours          |
| limit          | value   | Number of records to delete in per dispatch                      |
| only-unmarked  | flag    | Delete only the unmarked records                                 |
| on-job         | flag    | Run the deletion process through a Queue Job                     |

The records are deleted in segments of the **`limit`** option (or the **`delete_in_segment_count`** config). Without **`--on-job`** all the segments are deleted in one go; with it, each segment is deleted by its own queue job, which queues the next one. When Redis is reachable and the default cache store supports locks, only one cleaning job runs at a time: a queued cleaning job that finds another one running is tried again 10 seconds later, and a non queued one is skipped.


#### Log Exporter 

To export up the logs as CSV and store in storage directory, use the command **`request-response-logger:export`** as 


```bash
php artisan request-response-logger:export
```

Or to set up the exporting process periodically, register it in **`\App\Console\Kernel`** class's **`schedule`** method as : 

```php
protected function schedule(Schedule $schedule) {
    ...
    $schedule->command('request-response-logger:export')->dailyAt('00:01');
}
```

Unless a specific file name is passed with the option **`--filename`** for this command , it will use the current Datetime as the name of the file . 

> NOTE that this package currently only export files in CSV format. Also by default it store the exported CSV files in the **`storage`** dierctory unless provided any other path . See the available command options to know more it 

This package also have some handle options available as such : 
| Command        | Type    | Description                                                                    |
| ---------------|---------|--------------------------------------------------------------------------------|
| filename       | value   | Name of the CSV file to store in storage directory                             |
| path           | value   | The absolute file store path if decided to store other than storage directory  |
| of-last        | value   | Export only last provided hours records                                        |
| replace        | flag    | If such file exists at given location, replace it with new file                |
| only-marked    | flag    | Export only marked records                                                     |
| with-trashed   | flag    | Export records along with soft deleted entries                                 |


#### Redis List Storage Importer

This is one unique command only applicable if you utilize the redis list storage with this package . What is does that it will import whatever logs that has been stored in the redis (through the specified key of config) into the DB table . Handy if you need to import all the records stored in redis list right away and dont want to wait till it hit the **`max_redis_count`** specificed in the config file . 

Run the command as : 

```bash
php artisan request-response-logger:redis-import
```

Or to set up the importing process periodically, register it in **`\App\Console\Kernel`** class's **`schedule`** method as : 

```php
protected function schedule(Schedule $schedule) {
    ...
    $schedule->command('request-response-logger:redis-import')->hourly();
}
```

### Model

If you would like to work with the data you've logged, you may want to retrieve data based on the http code . Or you may want to mark some records based on some logic which needed to be presist for a longer time . To Do such stuff , use the mode defined in the config file's **`model`** option . By default it will use the **`\Touhidurabir\RequestResponseLogger\Models\RequestResponseLogger::class`** model class . 

Also the default model provide some handle methods/scopes to work with such as : 

```php
use Touhidurabir\RequestResponseLogger\Models\RequestResponseLogger;

// Get every logged item with an http response code of 2xx:
RequestResponseLogger::successful()->get();

// Get every logged item with an http response code that ISN'T 2xx:
RequestResponseLogger::failed()->get();

// Get every logged item which are marked
RequestResponseLogger::marked()->get();
```

This package also keep track if request are coming from logged in user and make the user data to log data association . So it's possible to find for which user is request log has registered as : 

```php
RequestResponseLogger::with(['user'])->get();
```

If you need more methods , just extends the default model class or create your own and register it in the published config file . 

The default model also comes with a factory for the tests of your app, unless your app defines its own **`Database\Factories\Touhidurabir\RequestResponseLogger\Models\RequestResponseLoggerFactory`** : 

```php
RequestResponseLogger::factory()->count(10)->create();

RequestResponseLogger::factory()->marked()->create();
```

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

Please make sure to update tests as appropriate.

## License
[MIT](./LICENSE.md)
