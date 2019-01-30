<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryComposerInstaller;

use Composer\IO\IOInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Config\File\ConfigFilePool;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\DriverPool;

class InventoryConfiguratorFactory
{
    /**
     * @var DeploymentConfig;
     */
    private $deploymentConfig;

    /**
     * @var IOInterface
     */
    private $io;

    public function __construct(IOInterface $io)
    {
        $this->io = $io;
    }

    public function createConfigurator(string $projectDir): InventoryConfiguratorInterface
    {
        $deploymentConfig = $this->getDeploymentConfig($projectDir);
        if ($this->isModulesConfigured($deploymentConfig)) {
            $deploymentConfigWriter = $this->createDeploymentConfigWriter($projectDir);
            $isNeedEnableNewModules = $this->checkInventoryApiModule($deploymentConfig);
            return new DisabledInventoryConfiguration(
                $deploymentConfig,
                $deploymentConfigWriter,
                $this->io,
                $isNeedEnableNewModules
            );
        } else {
            return new NoChangesConfigurator($this->io);
        }
    }

    private function isModulesConfigured(DeploymentConfig $deploymentConfig): bool
    {
        return is_array($deploymentConfig->get('modules'));
    }

    private function getDeploymentConfig(string $projectDir): DeploymentConfig
    {
        $cacheKey = realpath($projectDir);
        if (!isset($this->deploymentConfig[$cacheKey])) {
            $this->deploymentConfig[$cacheKey] = $this->createDeploymentConfig($projectDir);
        }
        return $this->deploymentConfig[$cacheKey];
    }

    private function createDeploymentConfig(string $projectDir): DeploymentConfig
    {
        $deploymentConfigReader = $this->createDeploymentConfigReader($projectDir);
        $deploymentConfig = new DeploymentConfig($deploymentConfigReader);
        return  $deploymentConfig;
    }

    private function createDeploymentConfigReader(string $projectDir): DeploymentConfig\Reader
    {
        $dirList = $this->createDirectoryList($projectDir);
        $filesystemDriverPool = $this->createFilesystemDriverPool();
        $configFilePool = $this->createConfigFilePool();
        $reader = new DeploymentConfig\Reader(
            $dirList,
            $filesystemDriverPool,
            $configFilePool
        );
        return $reader;
    }

    private function createDeploymentConfigWriter(string $projectDir): DeploymentConfig\Writer
    {
        $deploymentConfigReader = $this->createDeploymentConfigReader($projectDir);
        $configFilePool = $this->createConfigFilePool();
        $deploymentConfig = $this->createDeploymentConfig($projectDir);
        $dirList = $this->createDirectoryList($projectDir);
        $filesystemDriverPool = $this->createFilesystemDriverPool();
        $filesystemReader = new Filesystem\Directory\ReadFactory($filesystemDriverPool);
        $filesystemWriter = new Filesystem\Directory\WriteFactory($filesystemDriverPool);
        $filesystem = new Filesystem(
            $dirList,
            $filesystemReader,
            $filesystemWriter
        );

        $deploymentConfigWriter = new DeploymentConfig\Writer(
            $deploymentConfigReader,
            $filesystem,
            $configFilePool,
            $deploymentConfig
        );
        return $deploymentConfigWriter;
    }

    private function createDirectoryList(string $projectDir): DirectoryList
    {
        $dirsConfig = DirectoryList::getDefaultConfig();
        $dirsConfig[DirectoryList::ROOT] = $projectDir;
        $dirList = new DirectoryList($projectDir, $dirsConfig);
        return $dirList;
    }

    private function createFilesystemDriverPool(): DriverPool
    {
        $driverPool = new DriverPool();
        return $driverPool;
    }

    private function createConfigFilePool(): ConfigFilePool
    {
        $configFilePool = new ConfigFilePool();
        return $configFilePool;
    }


    /**
     * Search module Magento_InventoryApi in Config.php.
     * Return true if module Magento_InventoryApi was found and value equal to 1.
     *
     * @param DeploymentConfig $deploymentConfig
     * @return bool
     */
    private function checkInventoryApiModule(DeploymentConfig $deploymentConfig): bool
    {
        $inventoryApiModuleEnabled = false;

        foreach ($deploymentConfig->get('modules') as $moduleName => $moduleEnabled) {
            if ('Magento_InventoryApi' === $moduleName) {
                $inventoryApiModuleEnabled = (bool)$moduleEnabled;
                break;
            }
        }

        return $inventoryApiModuleEnabled;
    }
}