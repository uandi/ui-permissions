<?php

declare(strict_types=1);

namespace UI\UiPermissions\Configuration;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Converts existing be_groups and sys_filemounts records back into the YAML structure that
 * ConfigurationCollector consumes. This is the inverse of the Dto classes and is meant to bootstrap
 * the YAML configuration of a project that already manages its permissions in the database.
 */
class ConfigurationExporter
{
    /**
     * Fields exported as a list of strings
     */
    public const CSV_FIELDS = [
        'tables_select',
        'tables_modify',
        'file_permissions',
        'custom_options',
        'mfa_providers',
        'groupMods',
        'tsconfig_includes',
    ];

    /**
     * Fields exported as a list of integers
     */
    public const INT_CSV_FIELDS = [
        'pagetypes_select',
        'allowed_languages',
        'db_mountpoints',
    ];

    /**
     * Fields that are exported by a dedicated conversion or that carry no meaning for the YAML abstraction
     */
    public const HANDLED_FIELDS = [
        'uid',
        'pid',
        'tstamp',
        'crdate',
        'cruser_id',
        'deleted',
        'sorting',
        'permission_key',
        'title',
        'description',
        'subgroup',
        'file_mountpoints',
        'non_exclude_fields',
        'explicit_allowdeny',
        'TSconfig',
        'workspace_perms',
        'identifier',
        'read_only',
        'base',
        'path',
    ];

    /**
     * Permission keys of all known records, per table: [table => [uid => permission key]]
     */
    protected array $keyMaps = [
        'be_groups' => [],
        'sys_filemounts' => [],
    ];

    /**
     * Keys that were derived from a title because the record had no permission_key yet:
     * [table => [uid => permission key]]
     */
    protected array $generatedKeys = [
        'be_groups' => [],
        'sys_filemounts' => [],
    ];

    /**
     * @var string[]
     */
    protected array $warnings = [];

    /**
     * Assign a permission key to every record. This has to happen for all records of the installation and not
     * only for the exported ones, otherwise subgroup and filemount references could not be resolved.
     */
    public function buildKeyMaps(array $beGroupRows, array $fileMountRows): void
    {
        $this->keyMaps = [
            'be_groups' => $this->buildKeyMap($beGroupRows),
            'sys_filemounts' => $this->buildKeyMap($fileMountRows),
        ];
    }

    /**
     * Convert the given records into the configuration array structure of a *.permissions.yaml file
     */
    public function export(array $beGroupRows, array $fileMountRows): array
    {
        $configuration = [];

        foreach ($fileMountRows as $fileMountRow) {
            $key = $this->getPermissionKey('sys_filemounts', (int)$fileMountRow['uid']);
            $this->rememberGeneratedKey('sys_filemounts', $fileMountRow, $key);
            $this->collectUnsupportedFields('sys_filemounts', $key, $fileMountRow);
            $configuration['sys_filemounts'][$key] = $this->exportFileMount($fileMountRow, $key);
        }

        $exportedFileMountUids = array_column($fileMountRows, 'uid');
        $exportedBeGroupUids = array_column($beGroupRows, 'uid');

        foreach ($beGroupRows as $beGroupRow) {
            $key = $this->getPermissionKey('be_groups', (int)$beGroupRow['uid']);
            $this->rememberGeneratedKey('be_groups', $beGroupRow, $key);
            $this->collectUnsupportedFields('be_groups', $key, $beGroupRow);
            $configuration['be_groups'][$key] = $this->exportBeGroup(
                $beGroupRow,
                $key,
                $exportedBeGroupUids,
                $exportedFileMountUids
            );
        }

        if (isset($configuration['sys_filemounts'])) {
            ksort($configuration['sys_filemounts']);
        }
        if (isset($configuration['be_groups'])) {
            ksort($configuration['be_groups']);
        }

        return $configuration;
    }

    /**
     * Permission keys that were derived from a record title and are not stored in the database yet
     */
    public function getGeneratedKeys(string $table): array
    {
        return $this->generatedKeys[$table] ?? [];
    }

    /**
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getPermissionKey(string $table, int $uid): string
    {
        return $this->keyMaps[$table][$uid] ?? '';
    }

    protected function exportFileMount(array $row, string $key): array
    {
        $item = [];

        if ((string)$row['title'] !== $key) {
            $item['title'] = (string)$row['title'];
        }

        $description = trim((string)($row['description'] ?? ''));
        if ($description !== '') {
            $item['description'] = $description;
        }

        $identifier = trim((string)($row['identifier'] ?? ''));

        // Account for the filemount syntax of TYPO3 versions before v12
        if ($identifier === '' && (string)($row['base'] ?? '') !== '' && (string)($row['path'] ?? '') !== '') {
            $identifier = $row['base'] . ':' . $row['path'];
        }

        if ($identifier !== '') {
            $item['identifier'] = $identifier;
        }

        if ((int)($row['read_only'] ?? 0) !== 0) {
            $item['read_only'] = true;
        }

        return $item;
    }

    protected function exportBeGroup(array $row, string $key, array $exportedBeGroupUids, array $exportedFileMountUids): array
    {
        $item = [];

        if ((string)$row['title'] !== $key) {
            $item['title'] = (string)$row['title'];
        }

        $description = trim((string)($row['description'] ?? ''));
        if ($description !== '') {
            $item['description'] = $description;
        }

        $this->addList($item, $row, 'tables_select');
        $this->addList($item, $row, 'tables_modify');
        $this->addList($item, $row, 'pagetypes_select');

        $nonExcludeFields = $this->convertNonExcludeFields((string)($row['non_exclude_fields'] ?? ''));
        if ($nonExcludeFields !== []) {
            $item['non_exclude_fields'] = $nonExcludeFields;
        }

        $explicitAllowdeny = $this->convertExplicitAllowdeny((string)($row['explicit_allowdeny'] ?? ''), $key);
        if ($explicitAllowdeny !== []) {
            $item['explicit_allowdeny'] = $explicitAllowdeny;
        }

        $this->addList($item, $row, 'allowed_languages');
        $this->addList($item, $row, 'db_mountpoints');

        $fileMountpoints = $this->resolveReferences(
            'sys_filemounts',
            (string)($row['file_mountpoints'] ?? ''),
            $exportedFileMountUids,
            $key,
            'file_mountpoints'
        );
        if ($fileMountpoints !== []) {
            $item['file_mountpoints'] = $fileMountpoints;
        }

        $this->addList($item, $row, 'file_permissions');
        $this->addList($item, $row, 'custom_options');
        $this->addList($item, $row, 'mfa_providers');

        $subgroup = $this->resolveReferences(
            'be_groups',
            (string)($row['subgroup'] ?? ''),
            $exportedBeGroupUids,
            $key,
            'subgroup'
        );
        if ($subgroup !== []) {
            $item['subgroup'] = $subgroup;
        }

        $this->addList($item, $row, 'groupMods');
        $this->addList($item, $row, 'tsconfig_includes');

        $tsConfig = rtrim((string)($row['TSconfig'] ?? ''));
        if ($tsConfig !== '') {
            // A literal block in YAML has to end with a newline
            $item['TSconfig'] = $tsConfig . "\n";
        }

        if ((int)($row['workspace_perms'] ?? 0) !== 0) {
            $item['workspace_perms'] = (int)$row['workspace_perms'];
        }

        return $item;
    }

    /**
     * Add a comma separated database field as a list, skipping empty values
     */
    protected function addList(array &$item, array $row, string $field): void
    {
        $values = GeneralUtility::trimExplode(',', (string)($row[$field] ?? ''), true);
        if ($values === []) {
            return;
        }

        if (\in_array($field, self::INT_CSV_FIELDS, true)) {
            $values = array_map(intval(...), $values);
        }

        $item[$field] = array_values(array_unique($values));
    }

    /**
     * "pages:title,pages:slug" becomes ['pages' => ['title', 'slug']]
     */
    protected function convertNonExcludeFields(string $value): array
    {
        $nonExcludeFields = [];

        foreach (GeneralUtility::trimExplode(',', $value, true) as $item) {
            $parts = explode(':', $item, 2);
            if (\count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }
            $nonExcludeFields[$parts[0]][] = $parts[1];
        }

        foreach ($nonExcludeFields as &$fields) {
            $fields = array_values(array_unique($fields));
        }
        unset($fields);

        return $nonExcludeFields;
    }

    /**
     * "tt_content:CType:text" becomes ['tt_content' => ['CType' => ['text']]]
     */
    protected function convertExplicitAllowdeny(string $value, string $permissionKey): array
    {
        $explicitAllowdeny = [];

        foreach (GeneralUtility::trimExplode(',', $value, true) as $item) {
            $parts = explode(':', $item, 3);
            if (\count($parts) !== 3) {
                continue;
            }

            [$table, $field, $allowedValue] = $parts;

            // Account for the explicitADmode syntax of TYPO3 versions before v12
            $mode = '';
            if (str_ends_with($allowedValue, ':ALLOW')) {
                $mode = 'ALLOW';
            } elseif (str_ends_with($allowedValue, ':DENY')) {
                $mode = 'DENY';
            }

            if ($mode !== '') {
                $allowedValue = substr($allowedValue, 0, -\strlen($mode) - 1);
            }

            if ($mode === 'DENY') {
                $this->warnings[] = sprintf(
                    'be_groups "%s": skipped the denied value "%s" of explicit_allowdeny, only allowed values are supported.',
                    $permissionKey,
                    $allowedValue
                );
                continue;
            }

            if ($table === '' || $field === '' || $allowedValue === '') {
                continue;
            }

            $explicitAllowdeny[$table][$field][] = $allowedValue;
        }

        foreach ($explicitAllowdeny as &$fields) {
            foreach ($fields as &$values) {
                $values = array_values(array_unique($values));
            }
            unset($values);
        }
        unset($fields);

        return $explicitAllowdeny;
    }

    /**
     * Turn a comma separated list of uids into a list of permission keys
     */
    protected function resolveReferences(string $table, string $value, array $exportedUids, string $permissionKey, string $field): array
    {
        $references = [];
        $exportedUids = array_map(intval(...), $exportedUids);

        foreach (GeneralUtility::intExplode(',', $value, true) as $uid) {
            $referencedKey = $this->getPermissionKey($table, $uid);

            if ($referencedKey === '') {
                $this->warnings[] = sprintf(
                    'be_groups "%s": %s references %s uid %d which does not exist, the reference was dropped.',
                    $permissionKey,
                    $field,
                    $table,
                    $uid
                );
                continue;
            }

            if (!\in_array($uid, $exportedUids, true)) {
                $this->warnings[] = sprintf(
                    'be_groups "%s": %s references "%s" which is not part of this export, add it manually or export it as well.',
                    $permissionKey,
                    $field,
                    $referencedKey
                );
            }

            $references[] = $referencedKey;
        }

        return array_values(array_unique($references));
    }

    /**
     * Warn about database fields that carry a value but have no representation in the YAML abstraction
     */
    protected function collectUnsupportedFields(string $table, string $permissionKey, array $row): void
    {
        foreach ($row as $field => $value) {
            if (
                \in_array($field, self::HANDLED_FIELDS, true)
                || \in_array($field, self::CSV_FIELDS, true)
                || \in_array($field, self::INT_CSV_FIELDS, true)
            ) {
                continue;
            }

            if ($value === null || (string)$value === '' || (string)$value === '0') {
                continue;
            }

            $this->warnings[] = sprintf(
                '%s "%s": the field "%s" is not covered by the YAML abstraction and has to be handled manually.',
                $table,
                $permissionKey,
                $field
            );
        }
    }

    /**
     * @return array<int, string>
     */
    protected function buildKeyMap(array $rows): array
    {
        $keyMap = [];
        $usedKeys = [];

        // Records with an existing permission key win, so derived keys can not occupy them
        foreach ($rows as $row) {
            $permissionKey = trim((string)($row['permission_key'] ?? ''));
            if ($permissionKey !== '') {
                $keyMap[(int)$row['uid']] = $permissionKey;
                $usedKeys[$permissionKey] = true;
            }
        }

        foreach ($rows as $row) {
            $uid = (int)$row['uid'];
            if (isset($keyMap[$uid])) {
                continue;
            }

            $permissionKey = $this->makeUniqueKey($this->sanitizeKey((string)$row['title']), $uid, $usedKeys);
            $keyMap[$uid] = $permissionKey;
            $usedKeys[$permissionKey] = true;
        }

        return $keyMap;
    }

    protected function sanitizeKey(string $title): string
    {
        $key = strtr($title, [
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'Ä' => 'Ae',
            'Ö' => 'Oe',
            'Ü' => 'Ue',
            'ß' => 'ss',
        ]);

        return trim((string)preg_replace('/[^A-Za-z0-9_-]+/', '_', $key), '_');
    }

    /**
     * @param array<string, bool> $usedKeys
     */
    protected function makeUniqueKey(string $key, int $uid, array $usedKeys): string
    {
        if ($key === '') {
            $key = 'group_' . $uid;
        }

        if (!isset($usedKeys[$key])) {
            return $key;
        }

        $suffix = 2;
        while (isset($usedKeys[$key . '_' . $suffix])) {
            ++$suffix;
        }

        return $key . '_' . $suffix;
    }

    protected function rememberGeneratedKey(string $table, array $row, string $key): void
    {
        if (trim((string)($row['permission_key'] ?? '')) === '') {
            $this->generatedKeys[$table][(int)$row['uid']] = $key;
        }
    }
}
