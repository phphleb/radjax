<?php
declare(strict_types=1);

namespace Radjax\Src;

use Radjax\Route;
use Request;

class App
{
    protected $params;

    protected $uri;

    protected $data = [];

    function __construct(array $routes_files_path)
    {
        foreach($routes_files_path as $route){
            (new RCreator($route))->view();
        }

        $this->params = Route::getParams();

        $this->uri = trim(explode("?", $_SERVER['REQUEST_URI'])[0] , "/");
    }

    function get()
    {
        if (empty($this->params)) return;

        // Нахождение подходящего роута
        foreach ($this->params as $route_data) {
            $this->search_actual_route($route_data);
        }
    }

    protected function search_actual_route(array $data)
    {
        $this->data = [];

        if($data["route"] === $this->uri || $this->params_in_uri($data["route"], $data['where'])){

            // Роут найден

            $data["type"][] = "OPTIONS";

            if($data["add_headers"]) {

                if (strtoupper($_SERVER['REQUEST_METHOD']) == "OPTIONS") {

                    if (!headers_sent()) {
                        header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
                        header("Allow: " . implode(",", array_unique($data["type"])));
                        header("Content-length: 0");
                    }
                    exit();
                }

                if (!in_array(strtoupper($_SERVER['REQUEST_METHOD']), $data["type"]) ||
                    !in_array(strtoupper($_SERVER['REQUEST_METHOD']), Route::ALL_TYPES)) {

                    if (!headers_sent()) {
                        header($_SERVER["SERVER_PROTOCOL"] . " 405 Method Not Allowed");
                        header("Allow: " . implode(",", array_unique($data["type"])));
                        header("Content-length: 0");
                    }
                    exit();
                }
            }

            if(!isset($_SESSION)) session_start();
            if($data["protected"] && !$this->is_protected()){

                header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
                die("Protected from CSRF");

            }
            if(!$data["save_session"]) session_write_close();

            if (defined("HLEB_PROJECT_LOG_ON") && HLEB_PROJECT_LOG_ON) {

                ini_set('log_errors', 'On');

                ini_set('error_log', HLEB_GLOBAL_DIRECTORY . '/storage/logs/' . date("Y_m_d_") . 'errors.log');
            }

            if(!defined('HLEB_GLOBAL_DIRECTORY')) define("HLEB_GLOBAL_DIRECTORY", dirname(__FILE__, 5));

            if(!defined('HLEB_VENDOR_DIR_NAME')) define('HLEB_VENDOR_DIR_NAME', array_reverse(explode(DIRECTORY_SEPARATOR, dirname(__DIR__, 3)))[0]);

            if(!defined('HLEB_VENDOR_DIRECTORY')) define('HLEB_VENDOR_DIRECTORY', HLEB_GLOBAL_DIRECTORY . DIRECTORY_SEPARATOR . HLEB_VENDOR_DIR_NAME);

            if(!defined('HLEB_PROJECT_DIRECTORY')) define("HLEB_PROJECT_DIRECTORY", HLEB_VENDOR_DIRECTORY . "/phphleb/framework");

            if ($data["autoloader"] && file_exists(HLEB_VENDOR_DIRECTORY . "/" . 'autoload.php')) {
                require_once (HLEB_VENDOR_DIRECTORY . "/" . 'autoload.php');
            }

            ////////////////////////////////////////////// HLEB /////////////////////////////////////////////////////////

            if(defined("HLEB_FRAME_VERSION")) {

                 if (HLEB_PROJECT_CLASSES_AUTOLOAD && function_exists('radjax_main_autoloader')) {
                    spl_autoload_register('radjax_main_autoloader', true, true);
                 }

                if($this->data) {
                    foreach ($this->data as $key => $value) {
                        \Hleb\Constructor\Handlers\Request::add($key, $value);
                    }
                }
            }

            /////////////////////////////////////////////////////////////////////////////////////////////////////////////

            if(count($data["before"])) $this->get_before($data);

            $result = $this->get_controller($data);

            if(is_string($result) || is_numeric($result)) {
                print $result;
            }

            exit();

        }

        // Подходящего роута не найдено

        if(defined("HLEB_FRAME_VERSION")) $GLOBALS["HLEB_MAIN_DEBUG_RADJAX"]["/" . $data["route"] . "/"] = $data;

    }

    private function get_before(array $param)
    {
        $before_conrollers = $param["before"];

        foreach($before_conrollers as $before) {

            $call = explode("@", $before);

            $initiator = trim($call[0], "\\");

            $controller = new $initiator();

            $method = ($call[1] ?? "index") .
                (method_exists($controller, ($call[1] ?? "index")) ? "" : "Http" . ucfirst(strtolower($_SERVER['REQUEST_METHOD'])));

            $controller->{$method}();
        }

    }

    private function get_controller(array $param)
    {

        $call = explode("@", $param["controller"]);

        $initiator = trim($call[0], "\\");

        $controller = new $initiator();

        $method = ($call[1] ?? "index") .
            ( method_exists($controller, ($call[1] ?? "index")) ? "" : "Http" . ucfirst(strtolower($_SERVER['REQUEST_METHOD'])) );

        return $controller->{$method}(...$param["arguments"]);

    }

    protected function is_protected()
    {
        return ($_REQUEST['_token'] ?? "") === Route::key();
    }

    protected function params_in_uri($route, $where)
    {

        if ((strpos($route, "}") !== false && strpos($route, "}") !== false ) || strpos($route, "?") !== false) {

            $parts = explode("/", trim($route, "/"));

            $uri = explode("/", trim($this->uri, "/"));

            if (strpos(end($parts), "?") === false && count($uri) !== count($parts)) return false;

            if (strpos(end($parts), "?") !== false && !(count($uri) === count($parts) -1 || count($uri) === count($parts))) return false;

            foreach ($parts as $key => $part) {

                $pattern_name = trim($part, "{?}");

                if (isset($uri[$key]) && $part{0} == "{" && $part{strlen($part) - 1} == "}") {

                    if(count($where) && array_key_exists($pattern_name,$where)){

                        preg_match("/^" . $where[$pattern_name] . "$/", $uri[$key], $matches);

                        if (empty($matches[0]) || $matches[0] != $uri[$key]) {

                            return false;
                        }
                    }

                    $this->data[$pattern_name] = $uri[$key];

                } else if (!(($key === count($parts) - 1 && !isset($uri[$key])) || $pattern_name === $uri[$key])) {

                    return false;
                }
            }

            if(!defined("HLEB_FRAME_VERSION")) {
                // Проверки прошли успешно
                require "Request.php";

                Request::addAll($this->data);
            }

            return true;
        }
        return false;
    }

}


