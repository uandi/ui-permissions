<?php

declare(strict_types=1);

namespace UI\UiPermissions\Configuration;

use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Package\Package;
use TYPO3\CMS\Core\Package\PackageManager;
use UI\UiPermissions\Dto\BackendUserGroupDto;
use UI\UiPermissions\Dto\FileMountDto;

class ConfigurationCollector
{
    public const PERMISSION_FILE_PATTERN = '/.*\.permissions\.yaml$/';

    protected PackageManager $packageManager;

    protected YamlFileLoader $yamlFileLoader;

    public function __construct(
        PackageManager $packageManager,
        YamlFileLoader $yamlFileLoader
    ) {
        $this->packageManager = $packageManager;
        $this->yamlFileLoader = $yamlFileLoader;
    }

    public function collect(): array
    {
        $permissionFiles = [];

        // Gather config file paths
        /** @var Package $package */
        foreach ($this->packageManager->getActivePackages() as $package) {
            if ($package->getPackageMetaData()->getPackageType() === 'typo3-cms-extension') {
                $permissionFiles = array_merge($permissionFiles, $this->findPermissionFilesInPath($package->getPackagePath(), self::PERMISSION_FILE_PATTERN));
            }
        }

        // Load yaml files and merge them into one configuration array
        $mergedConfiguration = [];
        foreach ($permissionFiles as $permissionFile) {
            $configuration = $this->yamlFileLoader->load($permissionFile);
            $mergedConfiguration = $this->mergeConfiguration($mergedConfiguration, $configuration);
        }

        // Convert configurations to DTO
        return $this->convertToDtos($mergedConfiguration);
    }

    public function convertToDtos(array $configuration): array
    {
        if (\is_array($configuration['sys_filemounts'] ?? null)) {
            foreach ($configuration['sys_filemounts'] as $fileMountKey => &$fileMount) {
                $fileMount['permission_key'] = $fileMountKey;
                if (($fileMount['title'] ?? '') === '') {
                    $fileMount['title'] = $fileMountKey;
                }

                // Account for old filemount identifier syntax
                if (!isset($fileMount['identifier']) && isset($fileMount['base']) && (string)$fileMount['path'] !== '') {
                    $fileMount['identifier'] = $fileMount['base'] . ':' . $fileMount['path'];
                }

                $fileMount = new FileMountDto($fileMount);
            }
        }

        if (\is_array($configuration['be_groups'] ?? null)) {
            foreach ($configuration['be_groups'] as $beGroupKey => &$beGroup) {
                $beGroup['permission_key'] = $beGroupKey;
                if (($beGroup['title'] ?? '') === '') {
                    $beGroup['title'] = $beGroupKey;
                }
                $beGroup = new BackendUserGroupDto($beGroup);
            }
        }

        return $configuration;
    }

    public function findPermissionFilesInPath(string $path, string $regexPattern): array
    {
        $directory = new \RecursiveDirectoryIterator($path);
        $iterator = new \RecursiveIteratorIterator($directory);
        $matchedFiles = new \RegexIterator($iterator, $regexPattern, \RegexIterator::MATCH);

        $permissionFiles = [];

        /** @var \SplFileInfo $file */
        foreach ($matchedFiles as $file) {
            $permissionFiles[] = $file->getRealPath();
        }

        return $permissionFiles;
    }

    /**
     * Merge two configuration arrays recursively
     *
     * Native PHP array_merge_recursive or array_replace_recursive were not suited for this, as some values would be
     * converted to arrays or list arrays would partially overwrite each other.
     */
    private function mergeConfiguration(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && is_array($array1[$key] ?? null)) {
                if (array_is_list($value)) {
                    $array1[$key] = array_unique(array_merge($array1[$key], $value));
                } else {
                    $array1[$key] = $this->mergeConfiguration($array1[$key], $value);
                }
            } else {
                $array1[$key] = $value;
            }
        }

        return $array1;
    }
}
