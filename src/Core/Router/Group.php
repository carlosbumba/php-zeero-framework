<?php

namespace Zeero\Core\Router;

use Closure;

/**
 * Group classe
 * 
 * this classe handle route group creation
 * 
 * @author carlos bumba <carlosbumbanio@gmail.com>
 */
class Group
{
    /**
     * current group attributes
     *
     * @var array
     */
    private array $attrs;
    /**
     * the group scope
     *
     * @var Closure
     */
    private Closure $scope;

    public function __construct(array $attrs, Closure $scope)
    {
        $this->attrs = $attrs;
        $this->scope = $scope;
    }

    /**
     * return a Route object that received news routes from the group scope
     *
     * @param Route $owner
     * @return array
     */
    public function getScope(Route $owner)
    {
        ($this->scope)($owner);
        return $owner->getAllRoutes();
    }
}
