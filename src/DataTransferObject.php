<?php

namespace D3JDigital\DataTransferObject;

class DataTransferObject
{
    /**
     * @var object
     */
    private object $_params;

    public function __construct(
        ?array $properties = null,
        ?array $map = null,
    ) {
        $this->_params = (object) [
            'properties' => [],
            'map' => [],
        ];

        if ($map) {
            $this->map($map);
        }
        if ($properties) {
            $this->fill($properties);
        }
    }

    /**
     * @param array $properties
     * 
     * @return self
     */
    public function map(array $properties): self
    {
        $this->_params->map = $properties;

        return $this;
    }

    /**
     * @param array $properties
     * 
     * @return self
     */
    public function fill(array $properties): self
    {
        $this->_populate($properties);

        $this->_populate(array_filter($this->toUnparsedArray(), [$this, '_isEmpty']));

        return $this;
    }

    /**
     * @return self
     */
    public function get(): self
    {
        return $this;
    }

    /**
     * @return self
     */
    public function all(): self
    {
        return $this->get();
    }

    /**
     * @return array
     */
    public function toUnparsedArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return json_decode(json_encode($this->all()), true);
    }

    /**
     * @param array $properties
     * 
     * @return void
     */
    private function _populate(array $properties): void
    {
        foreach ($properties as $key => $value) {
            $property = $this->_snakeToCamel(
                array_search($key, $this->_params->map) ?: $key
            );

            if (!property_exists($this, $property)) {
                continue;
            }

            $method = ucfirst($property);

            if (method_exists($this, "set{$method}")) {
                $this->{"set{$method}"}($value);
            } else {
                $this->_populateReflectionProperty($key, $property, $value);
            }
        }
    }

    /**
     * @param string $key
     * @param string $property
     * @param mixed $value
     * 
     * @return void
     */
    private function _populateReflectionProperty(string $key, string $property, mixed $value): void
    {
        if (!$value) {
            return;
        }

        $map = $this->_params->map[$key] ?? [];

        $reflectionProperty = new \ReflectionProperty($this, $property);
        $reflectionPropertyType = $reflectionProperty?->getType()?->getName() ?? '';

        switch ($reflectionPropertyType) {
            case 'array':
                preg_match('/@var array{(?<class>.*)}/m', $reflectionProperty->getDocComment(), $matches);

                if (isset($matches[1])) {
                    $classInstance = $this->_createReflectionClassInstance($matches[1], [$this, '_populateArrayableDtoProperty'], $property, $value, $map);
                    if ($classInstance) {
                        return;
                    }
                }
                break;
            default:
                $classInstance = $this->_createReflectionClassInstance($reflectionPropertyType, [$this, '_populateDtoProperty'], $property, $value, $map);
                if ($classInstance) {
                    return;
                }
                break;
        }

        $this->{$property} = $value;
    }

    /**
     * @param string $classPath
     * @param mixed $callback
     * @param string $property
     * @param mixed $value
     * @param array $map
     * 
     * @return bool
     */
    private function _createReflectionClassInstance(string $classPath, $callback, string $property, mixed $value, array $map = []): bool
    {
        if (class_exists($classPath)) {
            $class = new $classPath();
            if ($class instanceof DataTransferObject) {
                $callback($class, $property, $value, $map);
                return true;
            }
        }
        return false;
    }

    /**
     * @param DataTransferObject $class
     * @param string $property
     * @param array $value
     * @param array $map
     * 
     * @return void
     */
    private function _populateDtoProperty(DataTransferObject $class, string $property, array $value, array $map = []): void
    {
        $class->map($map);
        $class->fill($value);

        $this->{$property} = $class->get();
    }

    /**
     * @param DataTransferObject $class
     * @param string $property
     * @param array $value
     * @param array $map
     * 
     * @return void
     */
    private function _populateArrayableDtoProperty(DataTransferObject $class, string $property, array $value, array $map = []): void
    {
        foreach ($value as $properties) {
            $this->{$property}[] = (new $class($properties ?? [], $map))->get();
        }
    }
    
    /**
     * @param string $input
     * 
     * @return string
     */
    private function _snakeToCamel(string $input): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));
    }

    /**
     * @param mixed $property
     * 
     * @return bool
     */
    private function _isEmpty(mixed $property): bool
    {
        return (bool) !$property;
    }
}