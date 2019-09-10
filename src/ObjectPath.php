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
    private $from;
    private $path;
    private $lineage;
    private $exists;

    public function __construct($mixed)
    {
        $this->setDelimiter('.');
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
     * Usage: $obj->{'.json.path'};
     *
     * @param string $jsonPath jsonPath
     *
     * @return (false|mixed)
     */
    public function __get(string $jsonPath)
    {
        return $this->get($jsonPath);
    }

    /**
     * Syntactic sugar for set() method. The starting '$' is not needed (implicit)
     * Usage: $obj->{'.json.path'} = $value;
     *
     * @param string $jsonPath jsonPath
     * @param mixed  $value    value
     *
     * @return JsonObject
     */
    public function __set(string $jsonPath, $value): self
    {
        return $this->set($jsonPath, $value);
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

    private function cleanPath(string $dotquery): string
    {
        $dotquery = ltrim($dotquery, '$.');
        $dotquery = ltrim($dotquery, '$');
        //$dotquery = ltrim($dotquery, $this->getDelimiter() . $this->getDelimiter());
        return $dotquery;
    }

    private function &processPathQuery(string $dotquery)
    {
        $data = $this->getWorking();
        if ($dotquery == '$') {
            return $data;
        }

        $paths = $this->absPathArray($this->cleanPath($dotquery));
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
                }
                // by array index
                else {
                    $exists = isset($data[$path]);
                    $data = &$data[$path];
                }
            }
            // by object property
            elseif (is_object($data)) {
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
        return $data;
    }

    /**
     * API
     */

    /**
     * Return the parent element of the working path
     *
     * @return mixed
     */
    public function &parentElement()
    {
        $lineage = &$this->getLineage();
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
     * Return the value of an element at the given path
     *
     * @param  string $path
     * @return mixed
     */
    public function get(string $path)
    {
        $data = $this->processPathQuery($path);
        return $data;
    }

    /**
     * Set the value of an element at the given path
     *
     * @param  string $path
     * @param  mixed  $value
     * @return \self
     */
    public function set(string $path, $value): self
    {
        $data = &$this->processPathQuery($path);
        $data = $value;
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
        $this->set($destinationPath, $this->get($sourcePath));
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
        $this->processPathQuery($path);
        $parent = &$this->parentElement();

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
}
