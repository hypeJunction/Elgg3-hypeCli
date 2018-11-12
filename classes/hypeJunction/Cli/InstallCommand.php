<?php

namespace hypeJunction\Cli;

use Elgg\Cli\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputOption;

class InstallCommand extends Command {

	/**
	 * {@inheritdoc}
	 */
	protected function configure() {
		$this->setName('hypejunction:install')
			->setDescription('Install hypeJunction plugins')
			->addOption('pack', 'p', InputOption::VALUE_OPTIONAL,
				'Specify plugin pack to install'
			);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function command() {

		_elgg_generate_plugin_entities();

		$pack = $this->option('pack');

		try {
			if ($pack) {
				$this->processPack($pack);
			} else {
				$packs = array_keys($this->getPluginPacks());
				foreach ($packs as $pack) {
					$this->processPack($pack);
				}
			}
		} catch (\Exception $ex) {
			$this->error($ex);

			return 1;
		}

		return 0;
	}

	/**
	 * Process a pack
	 *
	 * @param string $pack Pack name
	 *
	 * @return void
	 * @throws \InvalidParameterException
	 */
	protected function processPack($pack) {
		$packs = $this->getPluginPacks();

		if (!array_key_exists($pack, $packs)) {
			throw new \InvalidParameterException("Unknown plugin pack $pack");
		}

		$plugin_ids = $packs[$pack];

		$this->notice("Activating $pack plugins");

		$progress = new ProgressBar($this->output, count($plugin_ids));

		$progress->start();

		foreach ($plugin_ids as $plugin_id) {
			if ($this->activatePlugin($plugin_id)) {
				$progress->advance();
			}
		}

		$progress->finish();

		$this->output->writeln('');
	}

	/**
	 * Get plugins by pack
	 * @return array
	 */
	protected function getPluginPacks() {
		return [
			'core' => [
				'hypeVue',
				'hypeStash',
				'hypeTwig',
				'hypeCapabilities',
				'hypeLists',
				'hypeTime',
				'hypePost',
				'hypeScraper',
				'hypeShortcode',
				'hypeTrees',
				'hypeDropzone',
				'hypeAutocomplete',
				'hypeAjax',
				'hypeShutdown',
			],
			'content' => [
				'hypeWall',
				'hypeBlog',
				'hypeEmbed',
				'hypeProfile',
				'hypeGroups',
				'hypeActivity',
				'hypeNotifications',
				'hypeMentions',
				'hypeMedia',
				'hypeMapsOpen',
				'hypeAttachments',
				'hypeInteractions',
				'hypeDiscussions',
				'hypeStaticPages',
			],
			'tools' => [
				'hypeCountries',
				'hypeSlug',
				'hypeDraft',
				'hypeIllustration',
				'hypeInvite',
				'hypeModerator',
				'hypeMenus',
				'hypeCaptcha',
			],
			'commerce' => [
				'hypePayments',
				'hypeStripePayments',
				'hypePaypalPayments',
				'hypeBraintreePayments',
				'hypePaywall',
				'hypeSubscriptions',
				'hypeStripeSubscriptions',
				'hypePaypalSubscriptions',
				'hypeBraintreeSubscriptions',
				'hypeDownloads',
				'hypeSatis',
			],
			'theming' => [
				'hypeHero',
				'hypeTheme',
				'hypeSlider',
				
			],
		];
	}

	/**
	 * Activate the plugin as well as all plugins required by it
	 *
	 * @param string $id Plugin ID
	 *
	 * @return bool
	 * @throws \InvalidParameterException
	 * @throws \PluginException
	 */
	public function activatePlugin($id) {
		if (!$id) {
			return false;
		}

		$plugin = elgg_get_plugin_from_id($id);
		if (!$plugin) {
			return false;
		}

		if ($plugin->isActive()) {
			return true;
		}

		$manifest = $plugin->getManifest();
		if (!$manifest) {
			return false;
		}

		$conflicts = $manifest->getConflicts();
		foreach ($conflicts as $conflict) {
			if ($conflict['type'] == 'plugin') {
				$this->deactivatePlugin($conflict['name']);
			}
		}

		$requires = $manifest->getRequires();
		foreach ($requires as $require) {
			if ($require['type'] == 'plugin') {
				$this->activatePlugin($require['name']);
			}
		}

		$plugin->setPriority('last');

		$result = $plugin->activate();

		elgg_flush_caches();

		return $result;
	}

	/**
	 * Forcefully deactivate plugin and its dependants
	 *
	 * @param string $id Plugin ID
	 *
	 * @return bool
	 * @throws \PluginException
	 */
	public function deactivatePlugin($id) {

		$plugin = elgg_get_plugin_from_id($id);

		if (!$plugin) {
			return true;
		}

		if (!$plugin->isActive()) {
			return true;
		}

		$dependants = elgg_get_plugins('active');

		foreach ($dependants as $dependant) {
			$manifest = $dependant->getManifest();

			$requires = $manifest->getRequires();

			foreach ($requires as $require) {
				if ($require['type'] == 'plugin' && $require['name'] == $id) {
					$this->deactivatePlugin($dependant->getID());
				}
			}
		}

		return $plugin->deactivate();
	}
}