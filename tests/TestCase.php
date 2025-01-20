<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesVersionsS3\Tests;

use OCP\IDBConnection;

abstract class TestCase extends \Test\TestCase {
	public static function tearDownAfterClass(): void {
		if (self::$realDatabase !== null) {
			// in case an error is thrown in a test, PHPUnit jumps straight to tearDownAfterClass,
			// so we need the database again
			\OC::$server->registerService(IDBConnection::class, function () {
				return self::$realDatabase;
			});
		}
		$dataDir = \OC::$server->getConfig()->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data-autotest');

		self::tearDownAfterClassCleanStrayDataFiles($dataDir);
		self::tearDownAfterClassCleanStrayHooks();
		self::tearDownAfterClassCleanStrayLocks();

		\PHPUnit\Framework\TestCase::tearDownAfterClass();
	}
}
