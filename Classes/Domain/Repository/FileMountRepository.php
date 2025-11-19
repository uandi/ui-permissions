<?php

declare(strict_types=1);

namespace UI\UiPermissions\Domain\Repository;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use UI\UiPermissions\Dto\FileMountDto;

class FileMountRepository extends AbstractRepository
{
    public const TABLE = 'sys_filemounts';

    protected bool $createFilemountDirectories = true;

    public function __construct(
        ConnectionPool $connectionPool
    ) {
        parent::__construct($connectionPool);

        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('ui_permissions');

        $this->createFilemountDirectories = (bool)$extensionConfiguration['createFilemountDirectories'];
        $this->pid = (int)$extensionConfiguration['pidSysFilemounts'];
    }

    public function persist(array $fileMounts): void
    {
        foreach ($fileMounts as $fileMount) {
            if ($fileMount instanceof FileMountDto) {
                $existingFileMount = $this->findOneByPermissionKey($fileMount->getPermissionKey());

                // If filemount wasn't found, try finding it by title, which was the way to go in previous versions
                if ($existingFileMount === false) {
                    $existingFileMount = $this->findOneByTitle($fileMount->getPermissionKey());
                }

                if ($existingFileMount !== false) {
                    $this->update($existingFileMount['uid'], $fileMount->toDatabaseFieldArray());
                } else {
                    $this->add($fileMount->toDatabaseFieldArray());
                }

                if ($this->createFilemountDirectories && $fileMount->getIdentifier() !== '') {
                    $this->createFolderInFileadmin($fileMount->getIdentifier());
                }
            }
        }
    }

    private function createFolderInFileadmin(string $identifier): void
    {
        $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
        $identifier = explode(':', $identifier);

        if (\count($identifier) === 2 && (int)$identifier[0] > 0) {
            $storageObject = $storageRepository->findByUid((int)trim($identifier[0]));
            if ($storageObject instanceof ResourceStorage) {
                if (!$storageObject->hasFolder(trim($identifier[1]))) {
                    $storageObject->createFolder(trim($identifier[1]));
                }
            }
        }
    }
}
