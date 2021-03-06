<?php

namespace OmekaCli\Omeka;

use Omeka_Plugin_Factory;
use Omeka_Plugin_Installer;
use OmekaCli\Context\ContextAwareInterface;
use OmekaCli\Context\ContextAwareTrait;
use OmekaCli\Sandbox\OmekaSandbox;
use Plugin;
use Zend_Registry;

class PluginInstaller implements ContextAwareInterface
{
    use ContextAwareTrait;

    public function enable($pluginName)
    {
        $sandbox = $this->getSandbox();
        $sandbox->execute(function () use ($pluginName) {
            $this->enablePlugin($pluginName);
        });
    }

    public function disable($pluginName)
    {
        $sandbox = $this->getSandbox();
        $sandbox->execute(function () use ($pluginName) {
            $this->disablePlugin($pluginName);
        });
    }

    public function uninstall($pluginName)
    {
        $sandbox = $this->getSandbox();
        $sandbox->execute(function () use ($pluginName) {
            $this->uninstallPlugin($pluginName);
        });
    }

    public function getAll()
    {
        $sandbox = $this->getSandbox();
        $plugins = $sandbox->execute(function () {
            $plugins = $this->getAllPlugins();
            $plugins = array_map(function ($plugin) {
                $p = $plugin->toArray();
                $iniReader = Zend_Registry::get('plugin_ini_reader');
                if ($iniReader->hasPluginIniFile($plugin)) {
                    $p['info'] = parse_ini_file($iniReader->getPluginIniFilePath($plugin));
                }

                return $p;
            }, $plugins);

            return $plugins;
        }, OmekaSandbox::ENV_SHORTLIVED);

        return $plugins;
    }

    protected function enablePlugin($pluginName)
    {
        $plugin = $this->getPluginLoader()->getPlugin($pluginName);

        if ($plugin && $plugin->isActive()) {
            throw new \Exception('Plugin is already enabled');
        }

        if (!$plugin) {
            $plugin = new Plugin();
            $plugin->setDirectoryName($pluginName);
            $pluginIniReader = Zend_Registry::get('plugin_ini_reader');
            $pluginIniReader->load($plugin);
        }

        $requiredPlugins = $plugin->getRequiredPlugins();
        $missingDeps = array_filter($requiredPlugins, function ($requiredPlugin) {
            return !plugin_is_active($requiredPlugin);
        });
        if (!empty($missingDeps)) {
            throw new \Exception('Some required plugins are missing: ' . implode(', ', $missingDeps));
        }

        $pluginInstaller = $this->getPluginInstaller();

        if ($plugin->exists()) {
            $pluginInstaller->activate($plugin);
        } else {
            $pluginInstaller->install($plugin);
        }
    }

    protected function disablePlugin($pluginName)
    {
        $plugin = $this->getPluginLoader()->getPlugin($pluginName);
        if (!$plugin) {
            throw new \Exception('Plugin not found');
        }

        if (!$plugin->isActive()) {
            throw new \Exception('Plugin is already inactive');
        }

        $this->getPluginInstaller()->deactivate($plugin);
    }

    protected function uninstallPlugin($pluginName)
    {
        $plugin = $this->getPluginLoader()->getPlugin($pluginName);
        if (!$plugin) {
            throw new \Exception("$pluginName is not installed");
        }

        $this->getPluginInstaller()->uninstall($plugin);
    }

    protected function getAllPlugins()
    {
        $pluginLoader = $this->getPluginLoader();
        $installedPlugins = $pluginLoader->getPlugins();

        // Get plugins that are not installed and load them.
        $factory = new Omeka_Plugin_Factory(PLUGIN_DIR);
        $uninstalledPlugins = $factory->getNewPlugins($installedPlugins);
        $pluginLoader->loadPlugins($uninstalledPlugins);

        // Get the combination of installed and not-installed plugins.
        return $pluginLoader->getPlugins();
    }

    protected function getPluginLoader()
    {
        return Zend_Registry::get('plugin_loader');
    }

    protected function getPluginBroker()
    {
        return Zend_Registry::get('pluginbroker');
    }

    protected function getPluginInstaller()
    {
        $pluginLoader = $this->getPluginLoader();
        $pluginBroker = $this->getPluginBroker();

        return new Omeka_Plugin_Installer($pluginBroker, $pluginLoader);
    }
}
