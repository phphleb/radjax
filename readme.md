 #### Radjax (fast Ajax- and API-router)

The Radjax is not included in the original configuration of the framework [HLEB](https://github.com/phphleb/hleb), so it must be copied to the folder with the vendor/phphleb  libraries from the [github.com/phphleb/radjax](https://github.com/phphleb/radjax)  repository or installed using Composer:

```html
$ composer require phphleb/radjax
```

Connection to the project in /routes/ajax.php or /routes/api.php

```php

Radjax\Route::get("/info/", ["get"], "App\Controllers\TestController@index", ["protected"=>false, "autoloader" => true]);

// and advanced customization

Radjax\Route::get("/weather/{year}/{month}/{day}/{hour?}/", ["get","post"], "App\Controllers\TestController@weather", ["protected"=>true, "autoloader" => false, "arguments"=>["list"], "session_saved" => false]);

```
