<?php

namespace AttributesRouter;

use AttributesRouter\Attribute\Route;

class Router
{
    /**
     * Allows to define with a single call to the constructor, all the configuration necessary for the operation
     * of the router
     *
     * @param array  $controllers Classes containing Route attributes
     * @param string $baseURI     Part of the URI to exclude
     */
    public function __construct(
        private array $controllers = [],
        private string $baseURI = '',
    ) {}

    /**
     * Define the base URI in order to exclude it in the route correspondence, useful when the project is called from a
     * sub-folder
     *
     * @param string $baseURI Part of the URI to exclude
     */
    public function setBaseURI(string $baseURI): void
    {
        $this->baseURI = $baseURI;
    }

    /**
     * Add the controllers sent as arguments to those already stored
     *
     * @param array $controllers Classes containing Route attributes
     */
    public function addControllers(array $controllers): void
    {
        $this->controllers = array_merge($this->controllers, $controllers);
    }

    /**
     * Iterate over all the attributes of the controllers in order to find the first one corresponding to the request.
     * If a match is found then an array is returned with the class, method and parameters, otherwise null is returned
     *
     * @return string[]|null
     * @throws \ReflectionException if the controller does not exist
     */
    public function match(): ?array
    {
        $request = $_SERVER['REQUEST_URI'];

        if (!empty($this->baseURI)) {
            $baseURI = preg_quote($this->baseURI, '/');
            $request = preg_replace("/^{$baseURI}/", '', $request);
        }
        $request = (empty($request) ? '/': $request);

        foreach ($this->controllers as $controller) {
            $reflectionController = new \ReflectionClass($controller);

            foreach ($reflectionController->getMethods() as $method) {
                $routeAttributes = $method->getAttributes(Route::class);

                foreach ($routeAttributes as $attribute) {
                    $route = $attribute->newInstance();

                    if ($this->matchRequest($request, $route)) {
                        return [
                            'class'  => $method->class,
                            'method' => $method->name,
                            'params' => $route->getParameters(),
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Check if the user's request matches the given route
     *
     * @param string $request Request URI
     * @param Route  $route   Route attribute
     *
     * @return bool
     */
    private function matchRequest(string $request, Route $route): bool
    {
        $requestArray = explode('/', $request);
        $pathArray = explode('/', $route->getPath());
        unset($pathArray[0]);

        if (!$route->getMethod() === $_SERVER['REQUEST_METHOD']) {
            return false;
        }

        foreach ($pathArray as $index => $urlPart) {
            if (isset($requestArray[$index])) {
                if (str_starts_with($urlPart, '{')) {
                    $params = explode(' ', preg_replace('/{([\w\-%]+)(<(.+)>)?}/', '$1 $3', $urlPart));
                    $paramName = $params[0];
                    $paramRegExp = (empty($params[1]) ? '[\w\-]+': $params[1]);

                    if (preg_match('/^' . $paramRegExp . '$/', $requestArray[$index])) {
                        $route->addParameter($paramName, $requestArray[$index]);

                        continue;
                    }

                } elseif ($urlPart === $requestArray[$index]) {
                    continue;
                }
            }

            return false;
        }

        return true;
    }
}
