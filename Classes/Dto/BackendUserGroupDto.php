<?php

declare(strict_types=1);

namespace UI\UiPermissions\Dto;

class BackendUserGroupDto extends AbstractDto
{
    public const FIELD_MAPPING = [
        'groupMods' => 'groupMods',
        'TSconfig' => 'TSconfig',
    ];

    protected string $title;
    protected string $description;
    protected array $tablesModify;
    protected array $tablesSelect;
    protected array $pagetypesSelect;
    protected array $nonExcludeFields;
    protected array $explicitAllowdeny;

    protected array $dbMountpoints;
    protected array $fileMountpoints = [];
    protected array $filePermissions;

    protected array $subgroup = [];
    protected array $groupMods;
    protected string $TSconfig;

    protected array $allowedLanguages;
    protected array $customOptions;
    protected array $mfaProviders;

    //TBD
    protected array $categoryPerms;
    //TBD
    protected array $workspacePerms;

    public function getAllowedLanguages(): array
    {
        return $this->allowedLanguages;
    }

    public function getAllowedLanguagesProcessedForDatabase(): string
    {
        return implode(',', $this->allowedLanguages);
    }

    public function setAllowedLanguages(array|string $allowedLanguages): void
    {
        if (\is_string($allowedLanguages)) {
            $allowedLanguages = $this->explode($allowedLanguages);
        }

        $this->allowedLanguages = $allowedLanguages;
    }

    public function getCustomOptions(): array
    {
        return $this->customOptions;
    }

    public function getCustomOptionsProcessedForDatabase(): string
    {
        return implode(',', $this->customOptions);
    }

    public function setCustomOptions(array|string $customOptions): void
    {
        if (\is_string($customOptions)) {
            $customOptions = $this->explode($customOptions);
        }

        $this->customOptions = $customOptions;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getExplicitAllowdeny(): array
    {
        return $this->explicitAllowdeny;
    }

    public function getExplicitAllowdenyProcessedForDatabase(): string
    {
        $return = [];
        foreach ($this->explicitAllowdeny as $tableName => $field) {
            foreach ($field as $fieldName => $fieldValue) {
                $fieldValue = preg_filter('/^/', $tableName . ':' . $fieldName . ':', $fieldValue);
                $return = array_merge($return, (array)$fieldValue);
            }
        }

        return implode(',', $return);
    }

    public function setExplicitAllowdeny(array $explicitAllowdeny): void
    {
        $_explicitAllowdeny = [];

        // Account for old syntax
        if (isset($explicitAllowdeny['allow'])) {
            $explicitAllowdeny = $explicitAllowdeny['allow'];
        }

        foreach ($explicitAllowdeny as $tableName => $field) {
            if (!\is_array($field)) {
                $field = $this->explode((string)$field);
            }
            foreach ($field as $fieldName => $fieldValue) {
                if (!\is_array($fieldValue)) {
                    $fieldValue = $this->explode((string)$fieldValue);
                }
                $_explicitAllowdeny[$tableName][$fieldName] = $fieldValue;
            }
        }
        $this->explicitAllowdeny = $_explicitAllowdeny;
    }

    public function getDbMountpoints(): array
    {
        return $this->dbMountpoints;
    }

    public function getDbMountpointsProcessedForDatabase(): string
    {
        return implode(',', $this->dbMountpoints);
    }

    public function setDbMountpoints(array|int|string $dbMountpoints): void
    {
        if (\is_string($dbMountpoints)) {
            $dbMountpoints = $this->explode($dbMountpoints);
        }

        $this->dbMountpoints = (array)$dbMountpoints;
    }

    public function getFileMountpoints(): array
    {
        return $this->fileMountpoints;
    }

    public function getFileMountpointsProcessedForDatabase(): string
    {
        // FileMountpoints are handled dynamically in the repository class
        return '';
    }

    public function setFileMountpoints(array|string $fileMountpoints): void
    {
        if (\is_string($fileMountpoints)) {
            $fileMountpoints = $this->explode($fileMountpoints);
        }

        $this->fileMountpoints = $fileMountpoints;
    }

    public function getFilePermissions(): array
    {
        return $this->filePermissions;
    }

    public function getFilePermissionsProcessedForDatabase(): string
    {
        return implode(',', $this->filePermissions);
    }

    public function setFilePermissions(array|string $filePermissions): void
    {
        if (is_string($filePermissions)) {
            $filePermissions = $this->explode($filePermissions);
        }
        $this->filePermissions = $filePermissions;
    }

    public function getGroupMods(): array
    {
        return $this->groupMods;
    }

    public function getGroupModsProcessedForDatabase(): string
    {
        return implode(',', $this->groupMods);
    }

    public function setGroupMods(array|string $groupMods): void
    {
        if (\is_string($groupMods)) {
            $groupMods = $this->explode($groupMods);
        }

        $this->groupMods = $groupMods;
    }

    public function getMfaProviders(): array
    {
        return $this->mfaProviders;
    }

    public function getMfaProvidersProcessedForDatabase(): string
    {
        return implode(',', $this->mfaProviders);
    }

    public function setMfaProviders(array $mfaProviders): void
    {
        $this->mfaProviders = $mfaProviders;
    }

    public function getNonExcludeFields(): array
    {
        return $this->nonExcludeFields;
    }

    public function getNonExcludeFieldsProcessedForDatabase(): string
    {
        $return = [];
        foreach ($this->nonExcludeFields as $table => $fields) {
            $fields = preg_filter('/^/', $table . ':', $fields);
            $return = array_merge($return, (array)$fields);
        }

        return implode(',', $return);
    }

    public function setNonExcludeFields(array $nonExcludeFields): void
    {
        foreach ($nonExcludeFields as &$table) {
            if (!\is_array($table)) {
                $table = $this->explode($table);
            }
        }

        $this->nonExcludeFields = $nonExcludeFields;
    }

    public function getPagetypesSelect(): array
    {
        return $this->pagetypesSelect;
    }

    public function getPagetypesSelectProcessedForDatabase(): string
    {
        return implode(',', $this->pagetypesSelect);
    }

    public function setPagetypesSelect(array|int|string $pagetypesSelect): void
    {
        if (\is_string($pagetypesSelect)) {
            $pagetypesSelect = $this->explode($pagetypesSelect);
        }

        $this->pagetypesSelect = (array)$pagetypesSelect;
    }

    public function getSubgroup(): array
    {
        return $this->subgroup;
    }

    public function setSubgroup(array|string $subgroup): void
    {
        if (\is_string($subgroup)) {
            $subgroup = $this->explode($subgroup);
        }

        $this->subgroup = $subgroup;
    }

    public function getSubgroupProcessedForDatabase(): string
    {
        // Subgroups are handled dynamically in the repository class
        return '';
    }

    public function getTablesModify(): array
    {
        return $this->tablesModify;
    }

    public function getTablesModifyProcessedForDatabase(): string
    {
        return implode(',', $this->tablesModify);
    }

    public function setTablesModify(array|string $tablesModify): void
    {
        if (\is_string($tablesModify)) {
            $tablesModify = $this->explode($tablesModify);
        }

        $this->tablesModify = $tablesModify;
    }

    public function getTablesSelect(): array
    {
        return $this->tablesSelect;
    }

    public function getTablesSelectProcessedForDatabase(): string
    {
        return implode(',', $this->tablesSelect);
    }

    public function setTablesSelect(array|string $tablesSelect): void
    {
        if (\is_string($tablesSelect)) {
            $tablesSelect = $this->explode($tablesSelect);
        }

        $this->tablesSelect = $tablesSelect;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getTSconfig(): string
    {
        return $this->TSconfig;
    }

    public function setTSconfig(string $TSconfig): void
    {
        $this->TSconfig = $TSconfig;
    }
}
