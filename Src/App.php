<?php

declare(strict_types=1);

namespace Radjax\Src;

use Radjax\Route;
use Request;

class App
{
    protected $params;

    protected $uri;

    protected $secret_key;

    protected $data = [];

    function __construct(array $all_params) // insert Route::getParams();
    {
        $this->params = $all_params;

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


            define("HLEB_GLOBAL_DIRECTORY", dirname(__FILE__, 5));

            define('HLEB_VENDOR_DIRECTORY', array_reverse(explode(DIRECTORY_SEPARATOR, dirname(__DIR__, 3)))[0]);

            define("HLEB_PROJECT_DIRECTORY", HLEB_GLOBAL_DIRECTORY . "/" . HLEB_VENDOR_DIRECTORY . "/phphleb/framework");

            if ($data["autoloader"] && file_exists(HLEB_GLOBAL_DIRECTORY . "/" . HLEB_VENDOR_DIRECTORY . "/" . 'autoload.php')) {
                require_once (HLEB_GLOBAL_DIRECTORY . "/" . HLEB_VENDOR_DIRECTORY . "/" . 'autoload.php');          }



            ////////////////////////////////////////////// HLEB /////////////////////////////////////////////////////////

            if(is_dir(HLEB_GLOBAL_DIRECTORY . "/app/Optional/") && is_dir(HLEB_PROJECT_DIRECTORY . "/Main/")) {

                require_once HLEB_PROJECT_DIRECTORY . "/Main/Insert/DeterminantStaticUncreated.php";

                require_once HLEB_PROJECT_DIRECTORY . "/Scheme/Home/Main/Connector.php";

                require_once HLEB_GLOBAL_DIRECTORY  . "/app/Optional/MainConnector.php";

                require_once HLEB_PROJECT_DIRECTORY . "/Main/HomeConnector.php";

                require_once HLEB_PROJECT_DIRECTORY . "/Scheme/App/Commands/MainTask.php";

                require_once HLEB_PROJECT_DIRECTORY . "/Scheme/App/Controllers/MainController.php";

                require_once HLEB_PROJECT_DIRECTORY . "/Scheme/App/Middleware/MainMiddleware.php";

                require_once HLEB_PROJECT_DIRECTORY . "/Scheme/App/Models/MainModel.php";

                require_once HLEB_PROJECT_DIRECTORY . "/Main/MainAutoloader.php";


                if(function_exists('radjax_main_autoloader')) {
                    spl_autoload_register('radjax_main_autoloader', true, true);
                }
            }

            /////////////////////////////////////////////////////////////////////////////////////////////////////////////

            if(count($data["before"])) $this->get_before($data);

            $result = $this->get_controller($data);

            if(!is_string($result) && !is_numeric($result)) {
                error_log("Radjax/App: The controller " . $data["controller"] . " returned an invalid value format. ");
            }

            print $result;

            exit();

        }

        // Подходящего роута не найдено

        $GLOBALS["HLEB_MAIN_DEBUG_RADJAX"]["/" . $data["route"] . "/"] = $this->create_debug_info($data);

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
            // Проверки прошли успешно
            require_once "Request.php";

            Request::addAll($this->data);

            return true;
        }
        return false;
    }

    private function create_debug_info(array $param){
        $result = [];
        // Библиотеки могут выводить отладку в собственной манере
        foreach($param as $key=>$value) {
            $result[]= "<span style='color:yellowgreen'> " . $key . "</span>: <span style='color:whitesmoke'>" .
                (is_string($value) ? htmlentities($value) : htmlentities(json_encode($value))) . "</span>";
        }

        return "[ " .implode(", ", $result) . " ]";
    }
}


