<?php

declare(strict_types=1);

namespace UI\UiPermissions\Domain\Repository;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AbstractRepository
{
    public const TABLE = '';

    protected int $pid = 0;

    /**
     * Lowercased names of the columns that exist in this installation, resolved on first use
     *
     * @var array<string, bool>|null
     */
    protected ?array $columnNames = null;

    /**
     * Fields that were dropped because the table has no such column
     *
     * @var array<string, bool>
     */
    protected array $skippedFields = [];

    public function __construct(
        protected ConnectionPool $connectionPool
    ) {}

    /**
     * @throws Exception
     */
    public function findOneByPermissionKey(string $groupKey): array|false
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(static::TABLE);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $result = $queryBuilder
            ->select('*')
            ->from(static::TABLE)
            ->where(
                $queryBuilder->expr()->eq('permission_key', $queryBuilder->createNamedParameter($groupKey))
            )
            ->setMaxResults(1)
            ->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @throws Exception
     */
    public function findOneByTitle(string $title): array|false
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(static::TABLE);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $result = $queryBuilder
            ->select('*')
            ->from(static::TABLE)
            ->where(
                $queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter($title))
            )
            ->setMaxResults(1)
            ->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * @throws Exception
     */
    public function findAll(bool $includeHidden = true): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(static::TABLE);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $queryBuilder
            ->select('*')
            ->from(static::TABLE)
            ->orderBy('title')
            ->addOrderBy('uid');

        if (!$includeHidden) {
            $queryBuilder->where(
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            );
        }

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * Store a permission key on an existing record without touching any other field
     */
    public function updatePermissionKey(int $uid, string $permissionKey): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(static::TABLE);
        $queryBuilder
            ->update(static::TABLE)
            ->set('permission_key', $permissionKey)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT))
            )
            ->executeStatement();
    }

    /**
     * Configuration fields that could not be written because this installation has no such column
     *
     * @return string[]
     */
    public function getSkippedFields(): array
    {
        return array_keys($this->skippedFields);
    }

    protected function add(array $values): void
    {
        // Always use the configured pid
        $values['pid'] = $this->pid;

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(static::TABLE);
        $queryBuilder
            ->insert(static::TABLE)
            ->values($this->filterToExistingColumns($values))
            ->executeStatement();
    }

    protected function update(string|int $identifier, array $values): void
    {
        // Always use the configured pid
        $values['pid'] = $this->pid;

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(static::TABLE);
        $queryBuilder->update(static::TABLE);

        if (is_int($identifier)) {
            $queryBuilder->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($identifier, Connection::PARAM_INT))
            );
        } else {
            $queryBuilder->where(
                $queryBuilder->expr()->eq('permission_key', $queryBuilder->createNamedParameter($identifier))
            );
        }

        foreach ($this->filterToExistingColumns($values) as $field => $value) {
            $queryBuilder->set($field, $value);
        }

        $queryBuilder->executeStatement();
    }

    /**
     * Drop values that have no column in this installation.
     *
     * Optional core features add their own columns to the permission tables, for example "availableWidgets"
     * which only exists when EXT:dashboard is installed. Writing such a field would make the whole record
     * fail, so a configuration that is shared across projects would be unusable. The dropped fields are
     * collected so the calling command can report them.
     */
    protected function filterToExistingColumns(array $values): array
    {
        $columnNames = $this->getColumnNames();
        $filteredValues = [];

        foreach ($values as $field => $value) {
            if (isset($columnNames[strtolower((string)$field)])) {
                $filteredValues[$field] = $value;
                continue;
            }

            $this->skippedFields[$field] = true;
        }

        return $filteredValues;
    }

    /**
     * @return array<string, bool>
     */
    protected function getColumnNames(): array
    {
        if ($this->columnNames !== null) {
            return $this->columnNames;
        }

        // TYPO3's own SchemaInformation is marked @internal and its API differs between v12 and v14,
        // so the schema is read through Doctrine, which keys the columns by their lowercased name
        $columns = $this->connectionPool
            ->getConnectionForTable(static::TABLE)
            ->createSchemaManager()
            ->listTableColumns(static::TABLE);

        // The database does not distinguish the case of column names, so neither do we
        $this->columnNames = array_change_key_case(array_fill_keys(array_keys($columns), true));

        return $this->columnNames;
    }
}
