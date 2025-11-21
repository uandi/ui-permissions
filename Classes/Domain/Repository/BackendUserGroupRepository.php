<?php

declare(strict_types=1);

namespace UI\UiPermissions\Domain\Repository;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use UI\UiPermissions\Dto\BackendUserGroupDto;

class BackendUserGroupRepository extends AbstractRepository
{
    public const TABLE = 'be_groups';

    public function __construct(
        ConnectionPool $connectionPool
    ) {
        parent::__construct($connectionPool);

        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('ui_permissions');

        $this->pid = (int)$extensionConfiguration['pidBeGroups'];
    }

    public function persist(array $beGroups): void
    {
        /** @var FileMountRepository $fileMountRepository */
        $fileMountRepository = GeneralUtility::makeInstance(FileMountRepository::class);

        foreach ($beGroups as $beGroup) {
            if ($beGroup instanceof BackendUserGroupDto) {
                $existingBeGroup = $this->findOneByPermissionKey($beGroup->getPermissionKey());

                // If be_group wasn't found, try finding it by title, which was the way to go in previous versions
                if ($existingBeGroup === false) {
                    $existingBeGroup = $this->findOneByTitle($beGroup->getPermissionKey());
                }

                if ($existingBeGroup !== false) {
                    $this->update($existingBeGroup['uid'], $beGroup->toDatabaseFieldArray());
                } else {
                    $this->add($beGroup->toDatabaseFieldArray());
                }
            }
        }

        // Resolve relation fields. This only works if records (including filemount) are already persisted
        foreach ($beGroups as $beGroup) {
            if ($beGroup instanceof BackendUserGroupDto) {
                // Resolve subgroup uids
                $subgroupUids = [];
                foreach ($beGroup->getSubgroup() as $subgroupKey) {
                    $group = $this->findOneByPermissionKey($subgroupKey);
                    if ($group !== false) {
                        $subgroupUids[] = $group['uid'];
                    }
                }

                // Resolve filemount uids
                $fileMountUids = [];
                foreach ($beGroup->getFileMountpoints() as $fileMountKey) {
                    $fileMount = $fileMountRepository->findOneByPermissionKey($fileMountKey);
                    if ($fileMount !== false) {
                        $fileMountUids[] = $fileMount['uid'];
                    }
                }

                // Update fields in the database
                $this->update($beGroup->getPermissionKey(), [
                    'subgroup' => implode(',', $subgroupUids),
                    'file_mountpoints' => implode(',', $fileMountUids),
                ]);
            }
        }
    }
}
