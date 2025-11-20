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
    public const string TABLE = '';

    protected int $pid = 0;

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

    protected function add(array $values): void
    {
        // Always use the configured pid
        $values['pid'] = $this->pid;

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(static::TABLE);
        $queryBuilder
            ->insert(static::TABLE)
            ->values($values)
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

        foreach ($values as $field => $value) {
            $queryBuilder->set($field, $value);
        }

        $queryBuilder->executeStatement();
    }
}
