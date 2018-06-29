<?php

namespace hypeJunction\Cli;

use Elgg\Hook;

class RegisterCliCommands {

	/**
	 * Register cli commands
	 * @elgg_plugin_hook commands cli
	 *
	 * @param Hook $hook Hook
	 *
	 * @return array
	 */
	public function __invoke(Hook $hook) {
		$commands = $hook->getValue();

		$commands[] = InstallCommand::class;

		return $commands;
	}
}