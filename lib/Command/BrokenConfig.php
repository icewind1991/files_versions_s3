<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesVersionsS3\Command;

use Exception;

class BrokenConfig {
	private $id;
	private $bucket;
	private $name;
	private $exception;

	public function __construct(string $id, string $bucket, string $name, Exception $exception) {
		$this->id = $id;
		$this->bucket = $bucket;
		$this->name = $name;
		$this->exception = $exception;
	}

	public function getId(): string {
		return $this->id;
	}

	public function getBucket(): string {
		return $this->bucket;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getException(): Exception {
		return $this->exception;
	}
}
