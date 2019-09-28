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
    private $path;
    private $lineage;
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
     * @param string $jsonPath jsonPath
     *
     * @return (false|mixed)
     */
    public function &__get(string $jsonPath)
    {
        return $this->get($jsonPath);
    }

    /**
     * Syntactic sugar for set() method. The starting '$' is not needed (implicit)
     * Usage: $obj->{'.json.path'} = $value or $obj->{'json.path'} = $value;
     *
     * @param string $jsonPath jsonPath
     * @param mixed  $value    value
     *
     * @return JsonObject
     */
    public function __set(string $jsonPath, $value): self
    {
        return $this->set($jsonPath, $value, false);
    }
    /**
     * Magic method isset
     *
     * @param  string $jsonPath
     * @return boolean
     */
    public function __isset(string $jsonPath): bool
    {
        return $this->exists($jsonPath);
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
     * Set the root symbol used for path queries
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

    private function getPath(): array
    {
        return $this->path;
    }

    private function setPath(array $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Return an array of the path lineage elements
     *
     * @return array
     */
    private function &getLineage(): array
    {
        return $this->lineage;
    }

    private function setLineage($data): self
    {
        $this->lineage = $data;
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
     * @param string $query
     * @return boolean
     */
    public function isCached(string $query): bool
    {
        return isset($this->cache[$query]);
    }

    private function &getCache(string $query)
    {
        return $this->cache[$query];
    }

    private function setCache(string $query, &$data): self
    {
        $this->cache[$query] = &$data;
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

    private function absPath(string $dotquery): string
    {
        $from = (empty($this->getFrom())) ? '' : $this->getFrom() . $this->getDelimiter();
        return $from . $dotquery;
    }

    private function absPathArray(string $dotquery): array
    {
        return explode($this->getDelimiter(), $this->absPath($dotquery));
    }

    private function cleanQuery(string $dotquery): string
    {
        $dotquery = ltrim($dotquery, $this->getRootSymbol() . $this->getDelimiter());
        $dotquery = ltrim($dotquery, $this->getRootSymbol());
        $dotquery = trim($dotquery);
        return $dotquery;
    }

    private function &processPathQuery(string $dotquery)
    {
        $dotquery = $this->cleanQuery($dotquery);
        if ($this->isCached($dotquery)) {
            $this->setExists(true);
            return $this->getCache($dotquery);
        }

        $data = $this->getWorking();
        if ($dotquery === '') {
            return $data;
        }

        $paths = $this->absPathArray($dotquery);
        $this->setPath($paths);

        $absPath = null;
        $lineage = [];
        foreach ($paths as $pathPosition => $path) {
            $exists = false;
            if (is_array($data)) {
                // by array value
                if (!isset($data[$path]) && substr($path, 0, 1) == '{' && substr($path, -1) == '}') {
                    $path = array_search(str_replace(['{', '}'], '', $path), $data);
                    $exists = ($path !== false);
                    $data = &$data[$path];
                } else { // by array index
                    $exists = isset($data[$path]);
                    $data = &$data[$path];
                }
            } elseif (is_object($data)) { // by object property
                $exists = isset($data->{$path});
                $data = &$data->{$path};
            } else {
                $exists = false;
                $data = null;
            }
            $absPath = (!empty($absPath)) ? $absPath . $this->getDelimiter() . $path : $path;
            $lineage[$absPath] = &$data;
        }
        $this->setLineage($lineage);
        $this->setExists($exists);
        $this->setCache($dotquery, $data);
        return $data;
    }

    /**
     * API
     */

    /**
     * Return the parent element of the working path
     * TODO Deprecate in 1.0
     * @return mixed
     */
    public function &parentElement()
    {
        $lineage = &$this->getLineage();
        if (count($lineage) == 1) {
            return $lineage[key($lineage)];
        }

        $parentIndex = count($lineage) - 2;
        $currentIndex = 0;
        $parent = null;
        foreach ($lineage as &$element) {
            if ($currentIndex === $parentIndex) {
                $parent = &$element;
                break;
            }
            $currentIndex++;
        }
        return $parent;
    }

    /**
     * Return the parent element at the given path
     *
     * @param string $path
     * @return mixed
     */
    public function &getParent(string $path)
    {
        $this->processPathQuery($path);
        return $this->parentElement();
    }

    /**
     * Return the value of an element at the given path
     *
     * @param  string $path
     * @return mixed
     */
    public function &get(string $path)
    {
        return $this->processPathQuery($path);
    }

    /**
     * Set the value of an element at the given path
     *
     * @param  string $path
     * @param  mixed  $value
     * @param  bool $mustExist
     * @return \self
     */
    public function set(string $path, $value, bool $mustExist = false): self
    {
        if ($mustExist && !$this->exists($path)) {
            throw new \Exception('Path ' . $path . ' must exist');
        }

        $data = &$this->processPathQuery($path);
        $data = $value;
        $this->onAfterSet($data);
        return $this;
    }

    /**
     * Check if an object element exists at the given path
     * TODO Fix exists check adding to object if item doesn't exist
     *
     * @param  string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        $this->processPathQuery($path);
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
     * @param  string $path
     * @return \self
     */
    public function from(string $path): self
    {
        $this->setFrom($path);
        return $this;
    }

    /**
     * Return the path index of the currently selected item
     *
     * @return string Index path of current item
     */
    public function pathIndex(): string
    {
        $pathIndex = key(array_slice($this->getLineage(), -1, 1, true));
        return $pathIndex;
    }

    /**
     * Return an array of path elements and values keyed by path value
     *
     * @return array Array of path elements and values
     */
    public function &lineage(): array
    {
        return $this->getLineage();
    }

    /**
     * Remove an object property/array item based on path
     *
     * @param  string $path
     * @return \self
     */
    public function unset(string $path): self
    {

        $parent = &$this->getParent($path);

        $keys = explode($this->getDelimiter(), $this->pathIndex());
        $key = $keys[count($keys) - 1];
        if (is_array($parent)) {
            unset($parent[$key]);
            // have to renumber array for json otherwise turns array to object
            // https://stackoverflow.com/questions/35672604/unset-converts-array-into-object
            $parent = array_values($parent);
        } else {
            unset($parent->{$key});
        }
        $this->onAfterUnset($path, $parent);
        return $this;
    }

    /**
     * Return the current path
     *
     * @return string
     */
    public function path(): string
    {
        $path = implode($this->getDelimiter(), $this->getPath());
        return $path;
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
