<?php
declare(strict_types=1);

namespace Radjax\Src;

use Radjax\Route;

class App
{
    protected $params;

    protected $uri = '';

    protected $data = [];

    protected $route = null;

    protected $number = null;

    function __construct(array $routes_files_path) {
        foreach ($routes_files_path as $route) {
            (new RCreator($route))->view();
        }

        $this->params = Route::getParams();
    }

    /**
     * Возвращает факт обработки роута или отсутствие совпадения.
     * @return bool
     */
    public function get() {
        $this->number = 0;
        if (empty($this->params)) return false;
        $this->uri = trim(explode("?", $_SERVER['REQUEST_URI'])[0], "/");
        // Нахождение подходящего роута
        foreach ($this->params as $routeData) {
            $this->number++;
            if ($this->searchActualRoute($routeData)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Возвращает только результат нахождения определенного URL как подходящего под один из маршрутов.
     * @param string $uri
     * @return bool
     */
    public function searchRoute(string $uri) {
        $this->number = 0;
        if (empty($this->params)) return false;
        $this->uri = trim(explode("?", $uri)[0], "/");
        foreach ($this->params as $data) {
            $this->number++;
            $this->data = [];
            if ($data["route"] === $this->uri || $this->paramsInUri($data["route"], $data['where'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Возвращает найденный шаблон маршрута.
     * @return null|string
     */
    public function getRoute() {
        return $this->route;
    }

    /**
     * Возвращает позицию текущего роута.
     * @return int|null
     */
    public function getNumber() {
        return $this->number;
    }

    protected function searchType(array $type) {
        return in_array(strtoupper($_SERVER['REQUEST_METHOD']),  $type) && in_array(strtoupper($_SERVER['REQUEST_METHOD']), Route::ALL_TYPES);

    }

    protected function searchActualRoute(array $data) {
        $this->data = [];

        if ($data["route"] === $this->uri || $this->paramsInUri($data["route"], $data['where'])) {

            // Роут найден

            $data["type"][] = "OPTIONS";

            if ($data["add_headers"]) {

                if (strtoupper($_SERVER['REQUEST_METHOD']) == "OPTIONS") {

                    if (!headers_sent()) {
                        header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
                        header("Allow: " . implode(",", array_unique($data["type"])));
                        header("Content-length: 0");
                    }
                    exit();
                }

                if (!$this->searchType($data["type"])) {

                    if (!headers_sent()) {
                        header($_SERVER["SERVER_PROTOCOL"] . " 405 Method Not Allowed");
                        header("Allow: " . implode(",", array_unique($data["type"])));
                        header("Content-length: 0");
                    }
                    exit();
                }
            } else if (!$this->searchType($data["type"])) {
                exit();
            }

            if (!isset($_SESSION)) {
                session_start();
            }
            if ($data["protected"] && !$this->isProtected()) {

                header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
                die("Protected from CSRF");

            }
            if (!$data["save_session"]) {
                session_write_close();
            }

            if (defined("HLEB_FRAME_VERSION")) {
                if (file_exists(HLEB_GLOBAL_DIRECTORY . '/app/Optional/aliases.php')) {
                    require HLEB_GLOBAL_DIRECTORY . '/app/Optional/aliases.php';
                }
                if ($this->data) {
                    foreach ($this->data as $key => $value) {
                        \Hleb\Constructor\Handlers\Request::add($key, $value);
                    }
                }
            } else {
                require __DIR__ . "/Request.php";
                Request::addAll($this->data);
            }

            if (count($data["before"])) {
               if ($this->getBefore($data) === false) {
                   exit();
               }
            }
            $result = $this->getController($data);
            if ($result === false) {
                exit();
            }
            if (is_string($result) || is_numeric($result)) {
                print $result;
            }
            if (is_array($result)) {
                $this->addJsonData($result);
            }

            if (defined('HLEB_PROJECT_FULL_VERSION') && HLEB_PROJECT_FULL_VERSION < '1.5.53') {
                exit();
            } else {
                return true;
            }
        }

        // Подходящего роута не найдено

        if (defined("HLEB_FRAME_VERSION")) {
            $GLOBALS["HLEB_MAIN_DEBUG_RADJAX"]["/" . $data["route"] . "/"] = $data;
        }

        return false;

    }

    private function getBefore(array $param) {
        $beforeConrollers = $param["before"];

        foreach ($beforeConrollers as $before) {

            $call = explode("@", $before);

            $initiator = trim($call[0], "\\");

            $controller = new $initiator();

            $method = ($call[1] ?? "index") .
                (method_exists($controller, ($call[1] ?? "index")) ? "" : "Http" . ucfirst(strtolower($_SERVER['REQUEST_METHOD'])));

           if ($beforeData = ($controller->{$method}() === false)) {
               return false;
           }
            if (is_array($beforeData)) {
                $this->addJsonData($beforeData);
            }
        }
         return true;
    }

    private function getController(array $param) {
        $call = explode("@", $param["controller"]);

        $initiator = trim($call[0], "\\");

        $controller = new $initiator();

        $method = ($call[1] ?? "index") .
            (method_exists($controller, ($call[1] ?? "index")) ? "" : "Http" . ucfirst(strtolower($_SERVER['REQUEST_METHOD'])));

        return $controller->{$method}(...$param["arguments"]);

    }

    protected function isProtected() {
        return ($_REQUEST['_token'] ?? "") === Route::key();
    }

    protected function paramsInUri($route, $where) {

        if ((strpos($route, "}") !== false && strpos($route, "}") !== false) || strpos($route, "?") !== false) {

            $parts = explode("/", trim($route, "/"));

            $uri = explode("/", trim($this->uri, "/"));

            if (strpos(end($parts), "?") === false && count($uri) !== count($parts)) return false;

            if (strpos(end($parts), "?") !== false && !(count($uri) === count($parts) - 1 || count($uri) === count($parts))) return false;

            $firstPart = $parts[0];

            if (count($uri) == 1 && count($parts) == 1 && $uri[0] === "" && strpos($firstPart, "?") !== false) {

                if ($firstPart[0] == "{" && $firstPart[strlen($firstPart) - 1] == "}") {
                    $this->data[trim($firstPart, "{?}")] = "";
                }

            } else {

                foreach ($parts as $key => $part) {

                    $patternName = trim($part, "{?}");

                    if (isset($uri[$key]) && $part[0] == "{" && $part[strlen($part) - 1] == "}") {

                        if (count($where) && array_key_exists($patternName, $where)) {

                            preg_match("/^" . $where[$patternName] . "$/", $uri[$key], $matches);

                            if (empty($matches[0]) || $matches[0] != $uri[$key]) {

                                return false;
                            }
                        }

                        $this->data[$patternName] = $uri[$key];

                    } else if (!(($key === count($parts) - 1 && !isset($uri[$key])) || $patternName === $uri[$key])) {

                        return false;
                    }
                }
            }
            $this->route = trim($route, " /\\");

            return true;
        }
        return false;
    }

    private function addJsonData(array $params) {
        if (!defined('HLEB_TAG_INTERNAL') || !isset($result[HLEB_TAG_INTERNAL])) {
            headers_sent() or header("Content-Type: application/json");
            $json = json_encode($params);
            if ($json === false) {
                http_response_code(500);
                $json = '{"error": "json_encode"}';
            }
            exit($json);
        }
    }

}


