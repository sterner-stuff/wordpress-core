<?php

namespace SternerStuffWordPress\Commands;

use WP_CLI;

class Update extends Command {

	public $name = 'update';

	private $updateInfo = [];

	/**
	 * Update dependencies and spit out a table.
	 */
	public function __invoke() {

		if (!$this->isWordPress()) {
			WP_CLI::error('This command can only be run in a WordPress environment.');
		}

		$this->old_plugins = $this->getPlugins();
		$this->old_themes = $this->getThemes();
		$this->old_core = $this->getCore();

		$this->updateComposer();
		$this->updatePlugins();
		$this->updateThemes();

		$this->new_plugins = $this->getPlugins();
		$this->new_themes = $this->getThemes();
		$this->new_core = $this->getCore();

		if ($this->old_core != $this->new_core) {
			$this->updateInfo[] = [
				'WordPress [core]',
				$this->old_core,
				$this->new_core,
			];
		}

		$this->updateInfo = array_merge($this->updateInfo, $this->diffPlugins() ?: []);
		$this->updateInfo = array_merge($this->updateInfo, $this->diffThemes() ?: []);

		\WP_CLI\Utils\format_items('table', $this->updateInfo, ['Item', 'Old Version', 'New Version']);

	}

	private function updateComposer() {
		exec('composer update --no-interaction');
	}

	private function updateThemes() {
		if (WP_CLI::runcommand('theme list --update=available', [
			'return' => true,
		])) {
			return WP_CLI::runcommand('theme update --all', [
				'return' => true,
				'exit_error' => false,
			]);
		} else {
			WP_CLI::log('No themes to update.');
			return;
		}
	}

	private function updatePlugins() {
		if (WP_CLI::runcommand('plugin list --update=available', [
			'return' => true,
		])) {
			return WP_CLI::runcommand('plugin update --all', [
				'return' => true,
				'exit_error' => false,
			]);
		} else {
			WP_CLI::log('No plugins to update.');
			return;
		}
	}

	private function getPlugins() {
		return WP_CLI::runcommand('plugin list --fields=name,status,update,version,title --format=json', [
			'parse' => 'json',
			'return' => true,
		]);
	}

	private function getThemes() {
		return WP_CLI::runcommand('theme list --fields=name,status,update,version,title --format=json', [
			'parse' => 'json',
			'return' => true,
		]);
	}

	private function getCore() {
		return WP_CLI::runcommand('core version', [
			'return' => true,
		]);
	}

	private function diffPlugins() {
		if (!$this->old_plugins || !$this->new_plugins) {
			return false;
		}
		return $this->diff($this->old_plugins, $this->new_plugins, 'plugin');
	}

	private function diffThemes() {
		if (!$this->old_themes || !$this->new_themes) {
			return false;
		}
		return $this->diff($this->old_themes, $this->new_themes, 'theme');
	}

	public function isWordPress() {
		return false !== WP_CLI::runcommand('core is-installed');
	}

	private function diff($oldThings, $newThings, $type = false) {
		$updateInfo = [];
		$installed = [];

		foreach ($newThings as $new) {
			$installed[$new['name']] = $new['version'];
			$found = false;
			foreach ($oldThings as $old) {
				if ($old['name'] == $new['name']) {
					$found = true;
					if ($old['version'] == $new['version']) {
						break;
					}
					$updateInfo[] = [
						'Item' => $old['title'] . ($type ? ' [' . $type . ']' : ''),
						'Old Version' => $old['version'],
						'New Version' => $new['version'],
					];
					break;
				}
			}
			if (!$found) {
				$updateInfo[] = [
					'Item' => $new['title'] . ($type ? ' [' . $type . ']' : ''),
					'Old Version' => 'installed',
					'New Version' => $new['version'],
				];
			}
		}
		foreach ($oldThings as $old) {
			if (!array_key_exists($old['name'], $installed)) {
				$updateInfo[] = [
					'Item' => $old['title'] . ($type ? ' [' . $type . ']' : ''),
					'Old Version' => 'removed',
					'New Version' => 'N/A',
				];
			}
		}

		return $updateInfo;
	}

}