<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesVersionsS3\Command;

use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Enable extends Base {
	private $configManager;

	public function __construct(ConfigManager $configManager) {
		parent::__construct();
		$this->configManager = $configManager;
	}

	protected function configure() {
		parent::configure();

		$this
			->setName('files_versions_s3:enable')
			->setDescription('Enable S3 object versioning')
			->addArgument('id', InputArgument::REQUIRED, 'Id of the s3 configuration to enable versioning for');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$configs = $this->configManager->getS3Configs();

		$id = $input->getArgument('id');

		$config = null;

		foreach ($configs as $config) {
			if ($config->getId() === $id) {
				if ($config instanceof BrokenConfig) {
					$output->writeln("<error>S3 configuration is invalid</error>");
					$output->writeln("<error>" . $config->getException()->getMessage() . "</error>");
					return 1;
				}
				$config->enableVersioning();
				return 0;
			}
		}

		$output->writeln("<error>Config not found: $id</error>");
		return 1;
	}
}
