<?php

namespace Zeero\Core\Router;

use App\Controllers\AuthController;
use Closure;
use Exception;

/**
 * Route
 * 
 * A representation of Routes in application Routing
 * 
 * @author carlos bumba <carlosbumbanio@gmail.com>
 */
final class Route
{

    // array with all the routes
    private $map;
    // the information about the last route
    private $lastRoute;
    // the routes names
    private static $names;
    // token handler names
    private static $handlers;
    private static $instance;
    private $attrs;
    private $_token;

    private function __construct()
    {
    }


    /**
     * 
     * Singleton Method
     *
     * @return Route
     */
    public static function getInstance(): Route
    {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Register a route
     *
     * @param string $method
     * @param string $route
     * @param Closure|array|null $action
     * @param boolean $auth
     * @param int|array|null $level
     * @return void
     */
    private function register(string $method, string $route, $action, bool $auth, $level)
    {
        $requires = ['auth' => $auth, 'user_level' => $level];
        $token = null;

        if (isset($this->attrs)) {
            foreach ($this->attrs as $key => $value) {
                if (!is_array($value)) {
                    // group prefix
                    if ($key == 'prefix') {
                        $route = $value . $route;
                    } else {
                        // group controller
                        if ($key == 'controller') {
                            if (is_string($action)) {
                                $action = [$value, $action];
                            }
                        } else {
                            // normal attribute
                            $requires[$key] = $value;
                        }
                    }
                } else {
                    // not logged array
                    if (count($value) == 3 and $key == 'not_logged') {
                        list($_auth, $_level, $redirect) = $value;
                    }
                }

                if ($key == 'token') {
                    $token = $value;
                }
            }
        }


        /**
         * 
         * create a StdObject that represent the route
         * and insert the defaults or overwrited requirements
         * 
         */
        $this->map[$method][$route] = (object) ['action' =>  $action, 'route' => $route];
        $this->map[$method][$route]->require = $requires;
        $this->lastRoute = [$method, $route];

        if (isset($this->_token)) {
            $token = $this->_token;
        }

        if (isset($token)) {
            if (!is_array($token)) $token = [$token];

            foreach ($token as $tk) {
                $this->tokenHandler($this->map[$method][$route], $tk);
            }
        }

        /**
         * the dynamic attribute *_token is triggered in *resource() method
         * and finish at *delete method
         * 
         * so after that method this attributes must be unseted
         */
        if (isset($this->_token) and $method == 'delete') unset($this->_token);

        /**
         * check if the current group is using the *not_logged feature
         * the next statement will overwrite the current *auth and other route requirements
         * to shared requirements from the group
         */
        if (isset($_auth)) {
            $this->route_not_logged($_auth, $_level, $redirect);
        }
    }

    private function tokenHandler(&$route, string $handlername)
    {
        if (!isset(self::$handlers[$handlername])) {
            throw new Exception("Token Validation Handler: '{$handlername}' Not Exists");
        }

        $route->tokenHandler[] = self::$handlers[$handlername];
    }


    /**
     * Set a Route Name
     *
     * @param string $name
     * @return void
     */
    public function name(string $name)
    {
        if (is_null($this->lastRoute)) {
            throw new Exception("No route selected");
        }

        self::$names[$name] = $this->lastRoute;
    }




    /**
     * Create a Route Group
     *
     * @param array $attrs the group attributes
     * @param Closure $scope the closure that contains the group definition
     * @return void
     */
    public function group(array $attrs, Closure $scope)
    {
        if (isset($this->attrs)) {

            if (isset($attrs['prefix']) && isset($this->attrs['prefix'])) {
                $attrs['prefix'] = $this->attrs['prefix'] . $attrs['prefix'];
            }

            if (isset($attrs['token']) and isset($this->attrs['token'])) {
                $tkns = $attrs['token'];
                $tkns2 = $this->attrs['token'];
                if (!is_array($tkns)) $tkns = [$tkns];
                if (!is_array($tkns2)) $tkns2 = [$tkns2];

                $attrs['token'] = array_merge($tkns, $tkns2);
            }

            $attrs = array_merge($this->attrs, $attrs);
        }

        $group = new Group($attrs, $scope);
        // the owner
        $self = new Route;
        // append top level attributes
        $self->attrs = $attrs;
        // get the internal map
        $map = $group->getScope($self) ?? [];

        /**
         * 
         * Update the current group map
         * 
         */
        foreach ($map as $method => $routes) {
            // if does not exits the current method , then create a empty array
            if (!isset($this->map[$method])) $this->map[$method] = [];

            // merge routes from internal map to current map
            $this->map[$method] = array_merge($this->map[$method], $routes);
        }
    }



    /**
     * Create a not logged Route
     *
     * @param boolean $auth
     * @param int|array|null $level
     * @param string $redirect
     * @return Route
     */
    public function route_not_logged(bool $auth = false, $level = null, string $redirect = '')
    {
        $lastRouteInfo = $this->lastRoute;

        if (is_null($lastRouteInfo)) {
            throw new Exception("No route selected");
        }

        $this->map[$lastRouteInfo[0]][$lastRouteInfo[1]]->not_logged = ['auth' => $auth, 'user_level' => $level, 'redirect' => $redirect];
        return $this;
    }



    /**
     * get the route by name
     *
     * @param string $name
     * @param array|null $params
     * @return string|null
     */
    public static function route(string $name, array $params = null)
    {
        if (isset(self::$names[$name])) {
            $route = self::$names[$name][1];

            $route_parts = explode('/', $route);
            // filter params
            $route_parts = array_filter($route_parts, function ($i) {
                return preg_match("/(\{[a-zA-Z0-9\?\_\-]+\})/", $i);
            });

            foreach ($route_parts as $param) {
                $real_param = substr($param, 1, strlen($param) - 2);

                if (strpos($real_param, '?') === 0)
                    $real_param = substr($real_param, 1);

                if ($params and array_key_exists($real_param, $params)) {
                    $route = str_replace($param, $params[$real_param], $route);
                } else {
                    // check if is opcional parameter
                    if (strpos($param, '?') === 1) {
                        $route = str_replace("/{$param}", "", $route);
                    }
                }
            }

            return $route ?? '';
        }
    }


    /**
     * Register a Parameter Pattern
     *
     * @param array $pair the associative array that contains the parameter name as key and patterns as value
     * @throws Exception if no route is selected
     * @return void
     */
    public function where(array $pair)
    {
        $lastRouteInfo = $this->lastRoute;

        if (is_null($lastRouteInfo)) {
            throw new Exception("No route selected");
        }

        foreach ($pair as $paramName => $regexp) {
            $this->map[$lastRouteInfo[0]][$lastRouteInfo[1]]->params[$paramName] = $regexp;
        }
    }


    /**
     * Return all the routes registered
     *
     * @return array
     */
    public function getAllRoutes()
    {
        return $this->map ?? [];
    }


    /**
     * Register a GET route
     *
     * @param string $route
     * @param Closure|array|string|null $action
     * @param boolean $auth
     * @param int|array|null $level
     * @return Route
     */
    public function get(string $route, $action, bool $auth = false, $level = null)
    {
        $this->register('get', $route, $action, $auth, $level);
        return $this;
    }


    /**
     * Undocumented function
     *
     * @param string|null $controller
     * @return void
     */
    public function auth(string $controller = null)
    {
        $this->setControllerRoutes(
            $controller ?? AuthController::class,
            [
                '/login' => ['POST', 'login', false, null, [true, null, '/']],
                '/register' => ['POST', 'register', false, null, [true, null, '/']],
            ]
        );

        $this->get('/logout', function () {
            auth()->logout();
        });
    }


    /**
     * define a set of routes for a specified controller
     *
     * @param string $classname
     * @param array $routes
     * @return void
     */
    public function setControllerRoutes(string $classname, array $routes)
    {
        foreach ($routes as $route => $info) {
            // must contains *method and *method
            if (is_array($info) and count($info) > 1) {
                list($http_method, $method) = $info;
                $auth = $info[2] ?? false;
                $level = $info[3] ?? null;
                $not_logged = $info[4] ?? 0;
                // register the route
                $this->match(
                    [$http_method],
                    $route,
                    [$classname, $method],
                    $auth,
                    $level
                );
                // check if is a valid not_logged array
                if (is_array($not_logged) and count($not_logged) == 3) {
                    list($_auth, $_level, $redirect) = $not_logged;
                    $this->route_not_logged($_auth, $_level, $redirect);
                }
            }
        }
    }


    /**
     * Register a HEAD route
     *
     * @param string $route
     * @param Closure|array|string|null $action
     * @param boolean $auth
     * @param int|array|null $level
     * @return Route
     */
    public function head(string $route, $action, bool $auth = false, $level = null)
    {
        $this->register('head', $route, $action, $auth, $level);
        return $this;
    }


    /**
     * Register a POST route
     *
     * @param string $route
     * @param Closure|array|string|null $action
     * @param boolean $auth
     * @param int|array|null $level
     * @return Route
     */
    public function post(string $route, $action, bool $auth = false, $level = null)
    {
        $this->register('post', $route, $action, $auth, $level);
        return $this;
    }


    /**
     * Register a PUT route
     *
     * @param string $route
     * @param Closure|array|string|null $action
     * @param boolean $auth
     * @param int|array|null $level
     * @return Route
     */
    public function put(string $route, $action, bool $auth = false, $level = null)
    {
        $this->register('put', $route, $action, $auth, $level);
        return $this;
    }


    /**
     * Register a PATCH route
     *
     * @param string $route
     * @param Closure|array|string|null $action
     * @param boolean $auth
     * @param int|array|null $level
     * @return Route
     */
    public function patch(string $route, $action, bool $auth = false, $level = null)
    {
        $this->register('patch', $route, $action, $auth, $level);
        return $this;
    }


    /**
     * Register a DELETE route
     *
     * @param string $route
     * @param Closure|array|string|null $action
     * @param boolean $auth
     * @param int|array|null $level
     * @return Route
     */
    public function delete(string $route, $action, bool $auth = false, $level = null)
    {
        $this->register('delete', $route, $action, $auth, $level);
        return $this;
    }


    /**
     * Register a route in all request methods
     *
     * @param string $route
     * @param Closure|array|string|null $action
     * @param boolean $auth
     * @param int|array|null $level
     * @return void
     */
    public function any(string $route, $action, bool $auth = false, $level = null)
    {
        $methods = ['get', 'head', 'post', 'put', 'delete', 'patch'];
        foreach ($methods as $method) {
            $this->register($method, $route, $action, $auth, $level);
        }
    }


    /**
     * Register a route in specified request methods
     *
     * @param array $methods
     * @param string $route
     * @param Closure|array|string|null $action
     * @param boolean $auth
     * @param int|array|null $level
     * @return void
     */
    public function match(array $methods, string $route, $action, bool $auth = false, $level = null)
    {
        foreach ($methods as $method) {
            $this->register(strtolower($method), $route, $action, $auth, $level);
        }
    }


    /**
     * Undocumented function
     *
     * @param string $name
     * @param string $paramName
     * @param Closure $action
     * @return RouteTokenHandler
     */
    public function AddTokenValidator(string $name, string $paramName, Closure $action)
    {
        if (isset(self::$handlers[$name])) {
            throw new Exception("Duplicate Definition of {$name} Token Handler");
        }

        $handler = new RouteTokenHandler($action, $paramName);
        self::$handlers[$name] = $handler;
        return $handler;
    }


    /**
     * valid a token
     *
     * @param string|array $handlername
     * @return void
     */
    public function validToken($handler)
    {
        $lastRouteInfo = $this->lastRoute;

        if (is_null($lastRouteInfo)) {
            throw new Exception("No route selected");
        }

        if (!is_array($handler)) $handler = [$handler];

        foreach ($handler as $tk) {
            $this->tokenHandler($this->map[$lastRouteInfo[0]][$lastRouteInfo[1]], $tk);
        }
    }



    /**
     * Register a REST Resource
     *
     * @param string $name the name used in URL
     * @param string $classname the controller classname
     * @param boolean $auth
     * @param int|array|null $level
     * @param string|null $token
     * @return void
     */
    public function resource(string $name, string $classname, bool $auth = false, $level = null, string $token = null)
    {

        if ($token) $this->_token = $token;

        //index
        $this->match(
            ['get', 'head'],
            "/{$name}",
            [$classname, 'index'],
            $auth,
            $level
        );

        //store
        $this->post(
            "/{$name}",
            [$classname, 'store'],
            $auth,
            $level
        );

        //create
        $this->match(
            ['get', 'head'],
            "/{$name}/create",
            [$classname, 'create'],
            $auth,
            $level
        );

        //show
        $this->get(
            "/{$name}/{id}",
            [$classname, 'show'],
            $auth,
            $level
        );

        $this->head(
            "/{$name}/{id}",
            [$classname, 'show'],
            $auth,
            $level
        );

        //edit        
        $this->get(
            "/{$name}/{id}/edit",
            [$classname, 'edit'],
            $auth,
            $level
        );

        $this->head(
            "/{$name}/{id}/edit",
            [$classname, 'edit'],
            $auth,
            $level
        );

        //update
        $this->put(
            "/{$name}/{id}",
            [$classname, 'update'],
            $auth,
            $level
        );

        $this->patch(
            "/{$name}/{id}",
            [$classname, 'update'],
            $auth,
            $level
        );

        //destroy
        $this->delete(
            "/{$name}/{id}",
            [$classname, 'destroy'],
            $auth,
            $level
        );
    }
}
