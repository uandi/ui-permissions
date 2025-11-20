<?php

declare(strict_types=1);

namespace UI\UiPermissions\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use UI\UiPermissions\Configuration\ConfigurationCollector;
use UI\UiPermissions\Domain\Repository\BackendUserGroupRepository;
use UI\UiPermissions\Domain\Repository\FileMountRepository;

#[AsCommand(
    name: 'ui_permissions:update',
    description: 'Parse permission definitions and generate the corresponding database entries',
)]
class PermissionUpdatesCommand extends Command
{
    protected function configure(): void
    {
        $this->setDescription('Parse permission definitions and generate the corresponding database entries');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configurationCollector = GeneralUtility::makeInstance(ConfigurationCollector::class);
        $configuration = $configurationCollector->collect();

        if (isset($configuration['sys_filemounts'])) {
            $fileMountRepository = GeneralUtility::makeInstance(FileMountRepository::class);
            $fileMountRepository->persist($configuration['sys_filemounts']);
        }

        if (isset($configuration['be_groups'])) {
            $backendUserGroupRepository = GeneralUtility::makeInstance(BackendUserGroupRepository::class);
            $backendUserGroupRepository->persist($configuration['be_groups']);
        }

        return Command::SUCCESS;
    }
}
