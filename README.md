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

Thats it. 

> NOTE : It is very unrealistic that this there may be a need to log request/response for every routes of an app, though that depends on the need to application. Register the middleare as per your need and try to avoid it using a global middleare unless that what the app require . 


### Register and Run available commands

This package ships with several userful commands . such as 

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

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

Please make sure to update tests as appropriate.

## License
[MIT](./LICENSE.md)
