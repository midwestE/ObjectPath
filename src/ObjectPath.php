<?php

namespace MidwestE;

/**
 * Generic 'dot path' class for accessing and modifying data classes and arrays
 *
 * "schema.properties.agreement-email.title": "Spanish title",
 * "schema.properties.agreement-email.enum.0": "Spanish title",
 * "schema.properties.agreement-email.enum.1": "Spanish title",
 * "schema.properties.agreement-email.enum.{Y}": "Spanish title",
 * "schema.properties.agreement-email.enum.{N}": "Spanish title"
 */
class ObjectPath implements \JsonSerializable
{
    private $json;
    private $working;
    private $delimiter;
    private $rootSymbol;
    private $from;
    private $exists;
    private $cache = [];

    public function __construct($mixed)
    {
        $this->setDelimiter('.');
        $this->setRootSymbol('$');
        $this->setFrom('');
        $this->setData($mixed);
    }

    /**
     * Create a ObjectPath object based on object, array, or json string
     *
     * @param  object|array|string $mixed object, array, or json string
     * @return ObjectPath
     */
    public static function factory($mixed): ObjectPath
    {
        return new self($mixed);
    }

    /**
     * Syntactic sugar for toJson() method.
     * Usage:
     *  $json = (string)$instance;
     * or
     *  echo $instance;
     *
     * @param string $jsonPath jsonPath
     *
     * @return (false|string)
     */
    public function __toString(): ?string
    {
        return $this->toJson();
    }

    /**
     * Syntactic sugar for get() method. The starting '$' is not needed (implicit)
     * Usage: $obj->{'.json.path'} or $obj->{'json.path'};
     *
     * @param string $pathQuery jsonPath
     *
     * @return (false|mixed)
     */
    public function &__get(string $pathQuery)
    {
        return $this->get($pathQuery);
    }

    /**
     * Syntactic sugar for set() method. The starting '$' is not needed (implicit)
     * Usage: $obj->{'.json.path'} = $value or $obj->{'json.path'} = $value;
     *
     * @param string $pathQuery jsonPath
     * @param mixed  $value    value
     *
     * @return JsonObject
     */
    public function __set(string $pathQuery, $value): self
    {
        if (!$this->exists($pathQuery)) {
            throw new \Exception('Path ' . $pathQuery . ' must exist to use =>{\'my.path\'} format.  Use ->set($path, $value, false) to override');
        }
        return $this->set($pathQuery, $value, true);
    }
    /**
     * Magic method isset
     *
     * @param  string $pathQuery
     * @return boolean
     */
    public function __isset(string $pathQuery): bool
    {
        return $this->exists($pathQuery);
    }

    /**
     * Return json of the original data
     *
     * @return string Json of original data
     */
    private function getJson(): string
    {
        return $this->json;
    }

    /**
     * Set json
     *
     * @param  string $json
     * @return \self
     */
    private function setJson(string $json): self
    {
        $this->json = $json;
        return $this;
    }

    private function getWorking()
    {
        return $this->working;
    }

    private function setWorking($working)
    {
        $this->working = $working;
        return $this;
    }

    /**
     * Get the current path delimiter
     *
     * @return string
     */
    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * Set the delimiter used for path queries
     *
     * @param  string $delimiter
     * @return \self
     */
    public function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    /**
     * Get the current root symbol (default $)
     *
     * @return string
     */
    public function getRootSymbol(): string
    {
        return $this->rootSymbol;
    }

    /**
     * Set the root symbol used for path queries (default $)
     *
     * @param  string $symbol
     * @return \self
     */
    public function setRootSymbol(string $symbol): self
    {
        $this->rootSymbol = $symbol;
        return $this;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    private function setFrom(string $from): self
    {
        $this->from = $from;
        return $this;
    }

    private function getExists(): bool
    {
        return $this->exists;
    }

    private function setExists(bool $exists): self
    {
        $this->exists = $exists;
        return $this;
    }

    /**
     * Test if a query value is cached
     *
     * @param string $pathQuery
     * @return boolean
     */
    public function isCached(string $pathQuery): bool
    {
        return isset($this->cache[$pathQuery]);
    }

    private function &getCache(string $pathQuery)
    {
        return $this->cache[$pathQuery];
    }

    private function setCache(string $pathQuery, &$data): self
    {
        $this->cache[$pathQuery] = &$data;
        return $this;
    }

    private function unsetCache(string $query): self
    {
        if ($this->isCached($query)) {
            unset($this->cache[$query]);
        }
        return $this;
    }

    private function resetCache(): self
    {
        $this->cache = [];
        return $this;
    }

    /**
     * Helper
     */

    /**
     * Set the data used
     *
     * @param  string|object|array $data Dataset to use
     * @return \self
     */
    public function setData($data): self
    {
        $this->setJson((is_string($data)) ? $data : json_encode($data));
        $this->setWorking((is_string($data)) ? json_decode($data) : $data);
        $this->resetCache();
        return $this;
    }

    private function absPath(string $pathQuery): string
    {
        $from = (empty($this->getFrom())) ? '' : $this->getFrom() . $this->getDelimiter();
        $fromApplied = (substr_compare($pathQuery, $from, 0, strlen($from)) === 0) ? true : false;
        return ($fromApplied) ? $pathQuery : $from . $pathQuery;
    }

    private function normalizePath(string $pathQuery): string
    {
        $pathQuery = $this->absPath($pathQuery);
        $pathQuery = ltrim($pathQuery, $this->getRootSymbol() . $this->getDelimiter());
        $pathQuery = ltrim($pathQuery, $this->getRootSymbol());
        $pathQuery = trim($pathQuery);
        return $pathQuery;
    }

    private function &processPathQuery(string $pathQuery, $set = false)
    {
        $pathQuery = $this->normalizePath($pathQuery);

        $this->setExists(false);

        if ($this->isCached($pathQuery)) {
            $this->setExists(true);
            return $this->getCache($pathQuery);
        }

        $data = $this->getWorking();
        if ($pathQuery === '') {
            return $data;
        }

        $paths = explode($this->getDelimiter(), $pathQuery);
        $workingPaths = [];
        $null = null;
        foreach ($paths as $path) {
            $workingPaths[] = $path;
            $workingPath = implode($this->getDelimiter(), $workingPaths);
            if (is_array($data)) {
                if (!isset($data[$path]) && substr($path, 0, 1) == '{' && substr($path, -1) == '}') {
                    // by array value
                    $path = array_search(str_replace(['{', '}'], '', $path), $data);
                    if ($path === false) {
                        if (!$set) {
                            $this->setExists(false)->unsetCache($workingPath);
                            return $null;
                        }
                        $data[$path] = [];
                    }
                    $data = &$data[$path];
                    $this->setExists(true)->unsetCache($workingPath);
                } else {
                    // by array index
                    if (!isset($data[$path])) {
                        if (!$set) {
                            $this->setExists(false)->unsetCache($workingPath);
                            return $null;
                        }
                        $data[$path] = [];
                    }
                    $data = &$data[$path];
                    $this->setExists(true)->setCache($workingPath, $data);
                }
            } elseif (is_object($data)) {
                // by object property
                if (!isset($data->{$path})) {
                    if (!$set) {
                        $this->setExists(false)->unsetCache($workingPath);
                        return $null;
                    }
                    $data->{$path} =  new \stdClass();
                }
                $data = &$data->{$path};
                $this->setExists(true)->setCache($workingPath, $data);
            } else {
                $this->setExists(false)->unsetCache($workingPath);
                return $null;
            }
        }
        return $data;
    }

    /**
     * API
     */

    /**
     * Return the value of an element at the given path
     *
     * @param  string $pathQuery
     * @return mixed
     */
    public function &get(string $pathQuery)
    {
        return $this->processPathQuery($pathQuery);
    }

    /**
     * Set the value of an element at the given path
     *
     * @param  string $pathQuery
     * @param  mixed  $value
     * @param  bool $mustExist
     * @return \self
     */
    public function set(string $pathQuery, $value, bool $mustExist = true): self
    {
        if ($mustExist && !$this->exists($pathQuery)) {
            throw new \Exception('Path ' . $pathQuery . ' must exist');
        }

        $data = &$this->processPathQuery($pathQuery, true);
        $data = $value;
        $this->onAfterSet($data);
        return $this;
    }

    /**
     * Check if an object element exists at the given path
     *
     * @param  string $pathQuery
     * @return bool
     */
    public function exists(string $pathQuery): bool
    {
        $this->processPathQuery($pathQuery);
        return $this->getExists();
    }

    /**
     * Copy an object element to another element
     *
     * @param  string $sourcePath
     * @param  string $destinationPath
     * @return \self
     */
    public function copy(string $sourcePath, string $destinationPath): self
    {
        $this->set($destinationPath, $this->get($sourcePath), false);
        return $this;
    }

    /**
     * Set the base path for all further commands to use
     *
     * @param  string $rootPath
     * @return \self
     */
    public function from(string $rootPath): self
    {
        $this->setFrom($rootPath);
        return $this;
    }

    /**
     * Remove an object property/array item based on path
     *
     * @param  string $pathQuery
     * @return \self
     */
    public function unset(string $pathQuery): self
    {
        $pathQuery = $this->normalizePath($pathQuery);
        if ($pathQuery === '') {
            $this->setData(null);
        }

        $parent = &$this->parent($pathQuery);

        $pathParts = explode($this->getDelimiter(), $pathQuery);
        $key = $pathParts[count($pathParts) - 1];
        if (is_array($parent)) {
            unset($parent[$key]);
            // have to renumber array for json otherwise turns array to object
            // https://stackoverflow.com/questions/35672604/unset-converts-array-into-object
            $parent = array_values($parent);
        } else {
            unset($parent->{$key});
        }
        $this->unsetCache($pathQuery);
        $this->onAfterUnset($pathQuery, $parent);
        return $this;
    }

    /**
     * Return the parent element at the given path
     *
     * @param string $pathQuery
     * @return mixed
     */
    public function &parent(string $pathQuery)
    {
        return $this->processPathQuery($this->parentPath($pathQuery));
    }

    /**
     * Return the path of the parent element
     *
     * @param string $pathQuery
     * @return string
     */
    public function parentPath(string $pathQuery): string
    {
        $parentParts = explode($this->getDelimiter(), $this->normalizePath($pathQuery));
        $elementCount = count($parentParts);
        if ($elementCount == 1) {
            return $this->getRootSymbol();
        }
        unset($parentParts[$elementCount - 1]);
        return implode($this->getDelimiter(), $parentParts);
    }

    /**
     * Reset the working data set back to the original data
     *
     * @return \self
     */
    public function reset(): self
    {
        $this->setWorking(json_decode($this->getJson()));
        return $this;
    }

    /**
     * Return the data
     *
     * @return object|array Object or array
     */
    public function toData()
    {
        return $this->getWorking();
    }

    /**
     * Return a json representation of the data
     *
     * @return string json encoded string
     */
    public function toJson(): string
    {
        return json_encode($this->getWorking());
    }

    /*
    * Implements
    */

    public function jsonSerialize()
    {
        return $this->getWorking();
    }

    /**
     * Hooks
     */

    protected function onAfterSet($data)
    {
        // hook after set
    }

    protected function onAfterUnset($path, $parent)
    {
        // hook after unset
    }
}
