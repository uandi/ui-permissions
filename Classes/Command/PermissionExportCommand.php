<?php

declare(strict_types=1);

namespace UI\UiPermissions\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use UI\UiPermissions\Configuration\ConfigurationExporter;
use UI\UiPermissions\Domain\Repository\BackendUserGroupRepository;
use UI\UiPermissions\Domain\Repository\FileMountRepository;

#[AsCommand(
    name: 'ui_permissions:export',
    description: 'Generate permission YAML files from the existing be_groups and sys_filemounts records',
)]
class PermissionExportCommand extends Command
{
    public const FILE_SUFFIX = '.permissions.yaml';

    public const FILEMOUNT_FILE_NAME = 'Filemounts';

    public const SINGLE_FILE_NAME = 'Permissions';

    public function __construct(
        protected ConfigurationExporter $configurationExporter,
        protected BackendUserGroupRepository $backendUserGroupRepository,
        protected FileMountRepository $fileMountRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'Reads the existing be_groups and sys_filemounts records and writes them as *' . self::FILE_SUFFIX
                . ' files, so an existing project can be migrated to deployable permissions. Without --output the'
                . ' result is printed to stdout.'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Directory to write the permission files to, e.g. packages/my_sitepackage/Configuration/Permissions'
            )
            ->addOption(
                'single-file',
                null,
                InputOption::VALUE_NONE,
                'Write all definitions into one file instead of one file per permission key'
            )
            ->addOption(
                'group',
                'g',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Limit the export to the given be_groups, identified by uid, permission key or title (repeatable)'
            )
            ->addOption(
                'include-hidden',
                null,
                InputOption::VALUE_NONE,
                'Also export disabled records'
            )
            ->addOption(
                'write-permission-keys',
                null,
                InputOption::VALUE_NONE,
                'Store the generated permission keys in the database so the YAML files match the existing records'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing files'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $includeHidden = (bool)$input->getOption('include-hidden');
        $groupFilter = (array)$input->getOption('group');

        $allBeGroups = $this->backendUserGroupRepository->findAll();
        $allFileMounts = $this->fileMountRepository->findAll();

        // References have to be resolvable against every record, not only against the exported ones
        $this->configurationExporter->buildKeyMaps($allBeGroups, $allFileMounts);

        $beGroups = $this->filterRows($allBeGroups, $groupFilter, $includeHidden);
        if ($beGroups === []) {
            $io->warning('No matching be_groups records found.');

            return Command::SUCCESS;
        }

        $fileMounts = $this->filterRows($allFileMounts, [], $includeHidden);
        if ($groupFilter !== []) {
            // A filtered export should not drag in unrelated filemounts
            $fileMounts = $this->filterReferencedFileMounts($fileMounts, $beGroups);
        }

        $configuration = $this->configurationExporter->export($beGroups, $fileMounts);

        $outputDirectory = (string)($input->getOption('output') ?? '');
        if ($outputDirectory === '') {
            $output->writeln($this->dumpYaml($configuration), OutputInterface::OUTPUT_RAW);
        } else {
            $this->writeFiles($io, $configuration, $outputDirectory, (bool)$input->getOption('single-file'), (bool)$input->getOption('force'));
        }

        $io->section('Summary');
        $io->writeln(sprintf(
            'Exported %d be_groups and %d sys_filemounts records.',
            \count($configuration['be_groups'] ?? []),
            \count($configuration['sys_filemounts'] ?? [])
        ));

        foreach ($this->configurationExporter->getWarnings() as $warning) {
            $io->writeln('<comment>!</comment> ' . $warning);
        }

        $this->handleGeneratedKeys($io, $beGroups, $fileMounts, (bool)$input->getOption('write-permission-keys'));

        return Command::SUCCESS;
    }

    /**
     * Keep the records that match the given identifiers (uid, permission key or title)
     */
    protected function filterRows(array $rows, array $identifiers, bool $includeHidden): array
    {
        $filteredRows = [];

        foreach ($rows as $row) {
            if (!$includeHidden && (int)($row['hidden'] ?? 0) !== 0) {
                continue;
            }

            if (
                $identifiers !== []
                && !\in_array((string)$row['uid'], $identifiers, true)
                && !\in_array((string)$row['title'], $identifiers, true)
                && !\in_array((string)($row['permission_key'] ?? ''), $identifiers, true)
            ) {
                continue;
            }

            $filteredRows[] = $row;
        }

        return $filteredRows;
    }

    protected function filterReferencedFileMounts(array $fileMounts, array $beGroups): array
    {
        $referencedUids = [];
        foreach ($beGroups as $beGroup) {
            foreach (GeneralUtility::intExplode(',', (string)($beGroup['file_mountpoints'] ?? ''), true) as $uid) {
                $referencedUids[$uid] = true;
            }
        }

        return array_values(array_filter(
            $fileMounts,
            static fn(array $fileMount): bool => isset($referencedUids[(int)$fileMount['uid']])
        ));
    }

    /**
     * Split the configuration into files and write them to the given directory
     */
    protected function writeFiles(SymfonyStyle $io, array $configuration, string $directory, bool $singleFile, bool $force): void
    {
        $directory = $this->resolveDirectory($directory);
        GeneralUtility::mkdir_deep($directory);

        $files = $singleFile
            ? [self::SINGLE_FILE_NAME => $configuration]
            : $this->splitConfiguration($configuration);

        $written = 0;
        $skipped = 0;

        foreach ($files as $name => $fileConfiguration) {
            $path = $directory . '/' . $this->sanitizeFileName($name) . self::FILE_SUFFIX;

            if (!$force && file_exists($path)) {
                $io->writeln(sprintf('<comment>skipped</comment> %s (already exists, use --force to overwrite)', $path));
                ++$skipped;
                continue;
            }

            GeneralUtility::writeFile($path, $this->getFileHeader() . $this->dumpYaml($fileConfiguration), true);
            $io->writeln(sprintf('<info>written</info> %s', $path));
            ++$written;
        }

        $io->newLine();
        $io->writeln(sprintf('%d file(s) written, %d skipped.', $written, $skipped));
    }

    /**
     * One file per be_group. Filemounts are placed in the file of the first group that references them,
     * everything else ends up in a shared filemount file.
     *
     * @return array<string, array>
     */
    protected function splitConfiguration(array $configuration): array
    {
        $files = [];
        $fileMounts = $configuration['sys_filemounts'] ?? [];

        foreach ($configuration['be_groups'] ?? [] as $groupKey => $group) {
            foreach ($group['file_mountpoints'] ?? [] as $fileMountKey) {
                if (isset($fileMounts[$fileMountKey])) {
                    $files[$groupKey]['sys_filemounts'][$fileMountKey] = $fileMounts[$fileMountKey];
                    unset($fileMounts[$fileMountKey]);
                }
            }

            $files[$groupKey]['be_groups'][$groupKey] = $group;
        }

        if ($fileMounts !== []) {
            $files[self::FILEMOUNT_FILE_NAME]['sys_filemounts'] = $fileMounts;
        }

        return $files;
    }

    /**
     * Report permission keys that were derived from a record title and optionally store them in the database
     */
    protected function handleGeneratedKeys(SymfonyStyle $io, array $beGroups, array $fileMounts, bool $writePermissionKeys): void
    {
        $repositories = [
            'be_groups' => [$this->backendUserGroupRepository, $beGroups],
            'sys_filemounts' => [$this->fileMountRepository, $fileMounts],
        ];

        $generatedCount = 0;
        $ambiguous = [];

        foreach ($repositories as $table => [$repository, $rows]) {
            $titles = array_column($rows, 'title', 'uid');

            foreach ($this->configurationExporter->getGeneratedKeys($table) as $uid => $permissionKey) {
                ++$generatedCount;

                // "ui_permissions:update" falls back to matching a record by its title, so a derived key that
                // differs from the title would create a second record instead of updating the existing one
                if ((string)($titles[$uid] ?? '') !== $permissionKey) {
                    $ambiguous[] = sprintf('%s uid %d: "%s" becomes "%s"', $table, $uid, $titles[$uid] ?? '', $permissionKey);
                }

                if ($writePermissionKeys) {
                    $repository->updatePermissionKey((int)$uid, $permissionKey);
                }
            }
        }

        if ($generatedCount === 0) {
            return;
        }

        if ($writePermissionKeys) {
            $io->success(sprintf('Stored %d generated permission key(s) in the database.', $generatedCount));

            return;
        }

        $io->writeln(sprintf('%d record(s) had no permission_key, a key was derived from their title.', $generatedCount));

        if ($ambiguous !== []) {
            $io->warning(
                'For the following records the derived key differs from the record title. Applying these files with'
                . " \"ui_permissions:update\" would create new records instead of updating the existing ones.\n"
                . 'Re-run with --write-permission-keys to store the derived keys in the database first.'
            );
            $io->listing($ambiguous);
        }
    }

    /**
     * Absolute paths are used as given, "EXT:" paths are resolved and everything else is relative to the project root
     */
    protected function resolveDirectory(string $directory): string
    {
        if (str_starts_with($directory, 'EXT:')) {
            $directory = GeneralUtility::getFileAbsFileName($directory);
        } elseif (!str_starts_with($directory, '/')) {
            $directory = Environment::getProjectPath() . '/' . $directory;
        }

        return rtrim($directory, '/');
    }

    protected function getFileHeader(): string
    {
        return implode("\n", [
            '#',
            '# Generated by "ui_permissions:export" from the permission records of a TYPO3 installation.',
            '# Review the permission keys and the file layout before committing, see',
            '# https://github.com/uandi/ui-permissions#naming-conventions-permission-files-and-keys',
            '#',
            '',
            '',
        ]);
    }

    protected function dumpYaml(array $configuration): string
    {
        return Yaml::dump($configuration, 99, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    protected function sanitizeFileName(string $name): string
    {
        return trim((string)preg_replace('/[^A-Za-z0-9_.-]+/', '_', $name), '_');
    }
}
