<?php
/**
 * @author  Foma Tuturov <fomiash@yandex.ru>
 */

declare(strict_types=1);

namespace Radjax;

class Route
{
    private static $instance;

    public static $params = [];

    const ALL_TYPES = ["GET", "POST", "PATCH", "DELETE", "PUT", "OPTIONS", "CONNECT", "TRACE"];

    const STANDARD_TYPES = ["GET", "POST"];

    private function __construct(){}

    public function __clone(){}

    static public function instance()
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    public static function __callStatic($method, $args)
    {
        return call_user_func_array(array(self::instance(), $method), $args);
    }

    /*
       $route = "/page/{number?}/",
       $type = ["get", "post", "delete"],
       $controller = "App\Controllers\TestController@index",
       $params = [
         "protected" => false,
         "format" => "json",
         "arguments" => ["value1", "value2"],
         "autoloader" => false,
         "save_session" => false
     ] */

    static function get(string $route, array $type , string $controller, array $params)
    {
        $type = count($type) ? array_map("strtoupper", $type) :  self::STANDARD_TYPES;

        $sort_params = [];

        $sort_params["protected"] = $params["protected"] ?? false;

        $sort_params["arguments"] = $params["arguments"] ?? [];

        $sort_params["autoloader"] = $params["autoloader"] ?? false;

        $sort_params["save_session"] = $params["save_session"] ?? false;

        $route = trim($route, "/");

        self::$params[] = array_merge (["route"=>$route, "type"=>$type, "controller"=>$controller], $sort_params);
    }

    static function getParams() : array
    {
        return self::$params;
    }

    public static function key()
    {
        return md5(session_id() . ($_SESSION['_SECURITY_TOKEN'] ?? 0));
    }


}

