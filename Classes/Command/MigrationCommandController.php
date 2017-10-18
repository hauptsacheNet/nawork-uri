<?php

namespace Nawork\NaworkUri\Command;


use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;

class MigrationCommandController extends CommandController {
	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $databaseConnection;

	public function __construct() {
		$this->databaseConnection = $GLOBALS['TYPO3_DB'];
	}

	/**
	 * Delete deleted or hidden records. (2.0 -> 2.1)
	 *
	 * This is only necessary for upgrades from 2.0
	 */
	public function removeDeletedAndHiddenCommand() {
		$this->databaseConnection->exec_DELETEquery('tx_naworkuri_uri', 'deleted=1 OR hidden=1');
	}

	/**
	 * Migrate from sticky to locked (2.0 -> 2.1)
	 *
	 * This only necessary for the upgrades form 2.0
	 */
	public function stickyCommand() {
		$this->databaseConnection->sql_query('UPDATE tx_naworkuri_uri SET locked=sticky');
	}

	/**
	 * Migrate the pid to page_uid field (2.0 -> 2.1)
	 *
	 * This is only necessary for upgrades from 2.0
	 */
	public function pidToPageUidCommand() {
		$this->databaseConnection->sql_query('UPDATE tx_naworkuri_uri SET page_uid=pid WHERE pid!=0');
		$this->databaseConnection->sql_query('UPDATE tx_naworkuri_uri SET pid=0');
	}

	/**
	 * Remove duplicate paths per domain (2.0 -> 2.1)
	 *
	 * Remove duplicates because they would cause the key creation to fail.
	 */
	public function cleanupDuplicatesCommand() {
		$duplicateUrls = $this->databaseConnection->exec_SELECTgetRows(
			'path',
			'tx_naworkuri_uri',
			'',
			'path HAVING COUNT(*) > 1'
		);
		foreach ($duplicateUrls as $duplicateUrl) {
			$domains = $this->databaseConnection->exec_SELECTgetRows(
				'domain',
				'tx_naworkuri_uri',
				'path=' . $this->databaseConnection->fullQuoteStr($duplicateUrl['path'], 'tx_naworkuri_uri'),
				'domain'
			);
			foreach ($domains as $domain) {
				$urls = $this->databaseConnection->exec_SELECTgetRows(
					'uid,path,domain',
					'tx_naworkuri_uri',
					'path=' . $this->databaseConnection->fullQuoteStr(
						$duplicateUrl['path'],
						'tx_naworkuri_uri'
					) . ' AND domain=' . $this->databaseConnection->fullQuoteStr($domain['domain'], 'tx_naworkuri_uri')
				);
				if (count($urls) > 1) {
					array_shift($urls);
					foreach ($urls as $url) {
						$this->databaseConnection->exec_DELETEquery('tx_naworkuri_uri', 'uid=' . (int) $url['uid']);
					}
				}
			}
		}
	}

	/**
	 * Migrate domain names to domain records
	 *
	 * Convert the domain names in the url records
	 * to the uid of the corresponding domain record.
	 */
	public function urlDomainCommand() {
		$domainFromUrlRecords = $this->databaseConnection->exec_SELECTgetRows(
			'domain,count(domain)',
			'tx_naworkuri_uri',
			'domain NOT REGEXP "^[0-9]+$"',
			'domain',
			'domain ASC'
		);
		$this->outputLine('Found ' . count($domainFromUrlRecords) . ' domains in the url records');
		$this->outputLine('Processing...');
		$this->outputLine();

		foreach ($domainFromUrlRecords as $domainFromUrl) {
			$this->output('Processing domain "' . $domainFromUrl['domain'] . '":');
			$domainRecord = $this->getDomainRecordRecursive($domainFromUrl['domain']);
			if(is_array($domainRecord)) {
				$this->outputLine(
					'Found "' . $domainRecord['domainName'] . '" (UID ' . $domainRecord['uid'] . ')'
				);
				$this->output('Updating...');
				if(@$this->databaseConnection->exec_UPDATEquery(
					'tx_naworkuri_uri',
					'domain=' . $this->databaseConnection->fullQuoteStr($domainRecord['domainName'], 'tx_naworkuri_uri'),
					array('domain' => $domainRecord['uid'])
				)) {
					$this->outputLine(' ok!');
				} else {
					$this->outputLine(' failed!');
					$this->outputLine($this->databaseConnection->sql_error());
					$this->outputLine();
				}
			} else {
				$this->outputLine('No record found!');
			}
		}
	}

	/**
	 * Remove urls where the domain could not be converted
	 */
	public function cleanupCommand() {
		$this->databaseConnection->exec_DELETEquery('tx_naworkuri_uri', 'domain NOT REGEXP "^[0-9]+$"');
	}

	/**
	 * Determine the correct domain record recursively,
	 * using the domain name or record uid and respect the
	 * master domain record.
	 *
	 * @param string|int $domainNameOrUid
	 *
	 * @return array
	 */
	protected final function getDomainRecordRecursive($domainNameOrUid) {
		$domainRecord = NULL;
		if (MathUtility::canBeInterpretedAsInteger($domainNameOrUid)) {
			// go the recursive way with the master record
			$domainRecord = $this->databaseConnection->exec_SELECTgetSingleRow(
				'uid,domainName,tx_naworkuri_masterDomain',
				'sys_domain',
				'domainName = ' . $this->databaseConnection->fullQuoteStr($domainNameOrUid, 'sys_domain')
			);
		} else {
			// get the record based on the domain name
			$domainRecord = $this->databaseConnection->exec_SELECTgetSingleRow(
				'uid,domainName,tx_naworkuri_masterDomain',
				'sys_domain',
				'domainName = ' . $this->databaseConnection->fullQuoteStr($domainNameOrUid, 'sys_domain')
			);
		}
		if ($domainRecord['tx_naworkuri_masterDomain'] > 0) {
			$masterDomainRecord = $this->getDomainRecordRecursive($domainRecord['tx_naworkuri_masterDomain']);
			if (is_array($masterDomainRecord) && array_key_exists('uid', $masterDomainRecord)) {
				$domainRecord = $masterDomainRecord;
			}
		}

		return $domainRecord;
	}

	/**
	 * Override the output method to directly output instead
	 * of appending it to the response content.
	 *
	 * @param string $text
	 * @param array  $arguments
	 */
	protected function output($text, array $arguments = array()) {
		if ($arguments !== array()) {
			$text = vsprintf($text, $arguments);
		}
		echo $text;
	}

}
 