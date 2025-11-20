<?php

declare(strict_types=1);

namespace UI\UiPermissions\Dto;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class AbstractDto
{
    public const array FIELD_MAPPING = [];

    protected string $permissionKey;

    public function __construct(array $row = [])
    {
        foreach ($row as $fieldKey => $fieldValue) {
            $fieldKeyCamelCase = $this->snakeToCamelCase($fieldKey);
            if (method_exists($this, 'set' . $fieldKeyCamelCase)) {
                $callable = [$this, 'set' . $fieldKeyCamelCase];
                if (is_callable($callable)) {
                    \call_user_func($callable, $fieldValue);
                }
            }
        }
    }

    public function __toArray(): array
    {
        $array = [];

        $dtoProperties = $this->getProperties();
        if ($dtoProperties !== []) {
            foreach ($dtoProperties as $propertyKey => $propertyValue) {
                if (isset(static::FIELD_MAPPING[$propertyKey])) {
                    $fieldKey = static::FIELD_MAPPING[$propertyKey];
                } else {
                    $fieldKey = $this->camelToSnakeCase($propertyKey);
                }

                if (method_exists($this, 'get' . ucfirst($propertyKey))) {
                    $callable = [$this, 'get' . ucfirst($propertyKey)];
                    if (is_callable($callable)) {
                        $value = \call_user_func($callable);
                        if (
                            $value !== ''
                            && $value !== []
                        ) {
                            $array[$fieldKey] = $value;
                        }
                    }
                }
            }
        }

        return $array;
    }

    public function toDatabaseFieldArray(): array
    {
        $databaseFieldArray = [];

        $dtoProperties = $this->getProperties();
        if ($dtoProperties !== []) {
            foreach ($dtoProperties as $propertyKey => $propertyValue) {
                if (isset(static::FIELD_MAPPING[$propertyKey])) {
                    $fieldKey = static::FIELD_MAPPING[$propertyKey];
                } else {
                    $fieldKey = $this->camelToSnakeCase($propertyKey);
                }

                if (method_exists($this, 'get' . ucfirst($propertyKey) . 'ProcessedForDatabase')) {
                    $callable = [$this, 'get' . ucfirst($propertyKey) . 'ProcessedForDatabase'];
                    if (is_callable($callable)) {
                        $databaseFieldArray[$fieldKey] = \call_user_func($callable);
                    }
                } elseif (method_exists($this, 'get' . ucfirst($propertyKey))) {
                    $callable = [$this, 'get' . ucfirst($propertyKey)];
                    if (is_callable($callable)) {
                        $databaseFieldArray[$fieldKey] = \call_user_func($callable);
                    }
                }
            }
        }

        return $databaseFieldArray;
    }

    public function getPermissionKey(): string
    {
        return $this->permissionKey;
    }

    public function setPermissionKey(string $permissionKey): void
    {
        $this->permissionKey = $permissionKey;
    }

    protected function getProperties(): array
    {
        return get_object_vars($this);
    }

    protected function snakeToCamelCase(string $string): string
    {
        return str_replace('_', '', ucwords($string, '_'));
    }

    protected function camelToSnakeCase(string $string): string
    {
        return strtolower((string)preg_replace('/([^A-Z])([A-Z])/', '$1_$2', $string));
    }

    protected function explode(string $string): array
    {
        // Replace whitespace with comma and explode to array
        $string = preg_replace('/\s+/', ',', $string);

        return GeneralUtility::trimExplode(',', (string)$string, true);
    }
}
