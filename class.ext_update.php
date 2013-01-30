<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ext_update
 *
 * @author thorben
 */
class ext_update {

	const TX_NAWORKURI_UPDATE_MODE_OLD = 0;
	const TX_NAWORKURI_UPDATE_MODE_COOLURI = 1;
	const TX_NAWORKURI_UPDATE_MODE_STORAGE = 2;
	const TX_NAWORKURI_UPDATE_MODE_CLEANUP_DELETE = 3;
	const TX_NAWORKURI_UPDATE_MODE_DOMAIN = 4;

	protected $numRecords = 0;
	protected $mode = 0;
	protected $coolUriWasInstalled = FALSE;
	protected $extConf = array();

	/**
	 *
	 * @var t3lib_db
	 */
	protected $db;

	public function main() {
		$this->init();
		$output = '<div>';
		$output .= $this->renderModeForm();
		if (t3lib_div::_GP('doCheck') > 0) {
			$output .= $this->renderHeader();
			$output .= $this->renderStatus();
		} elseif (t3lib_div::_GP('doUpdate') > 0) {
			$success = TRUE;
			switch ($this->mode) {
				case ext_update::TX_NAWORKURI_UPDATE_MODE_COOLURI:
					break;
				case ext_update::TX_NAWORKURI_UPDATE_MODE_STORAGE:
					$this->db->exec_UPDATEquery('tx_naworkuri_uri', '', array('pid' => intval($this->extConf['storagePage'])));
					header('Location: ' . t3lib_div::getIndpEnv('TYPO3_REQUEST_URL'));
					break;
				case self::TX_NAWORKURI_UPDATE_MODE_CLEANUP_DELETE:
					$this->db->exec_DELETEquery('tx_naworkuri_uri', 'deleted=1');
					header('Location: ' . t3lib_div::getIndpEnv('TYPO3_REQUEST_URL'));
					break;
				case static::TX_NAWORKURI_UPDATE_MODE_DOMAIN:
					$domainRecords = $this->db->exec_SELECTgetRows('uid,domainName', 'sys_domain', '');
					if (is_array($domainRecords)) {
						foreach ($domainRecords as $rec) {
							if (!$this->db->exec_UPDATEquery('tx_naworkuri_uri', 'domain=' . $this->db->fullQuoteStr($rec['domainName'], 'tx_naworkuri_uri'), array('domain' => intval($rec['uid'])))) {
								$success = FALSE;
							}
						}
					}
					break;
				default:
					$this->db->sql_query('UPDATE tx_naworkuri_uri SET page_uid=pid, locked=sticky WHERE pid>0');
					$this->db->exec_UPDATEquery('tx_naworkuri_uri', 'pid!=' . intval($this->extConf['storagePage']) . ' AND deleted=0', array('pid' => intval($this->extConf['storagePage'])));
					header('Location: ' . t3lib_div::getIndpEnv('TYPO3_REQUEST_URL'));
			}
			if ($success) {
				$output .='<p>Update successful!</p>';
			}
		}
		$output .= '</div>';
		return $output;
	}

	private function init() {
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['nawork_uri']);
		$this->db = $GLOBALS['TYPO3_DB'];
		if (t3lib_div::_GP('tx_naworkuri_update_mode') == 'cooluri') {
			$this->mode = ext_update::TX_NAWORKURI_UPDATE_MODE_COOLURI;
		} elseif (t3lib_div::_GP('tx_naworkuri_update_mode') == 'storage') {
			$this->mode = ext_update::TX_NAWORKURI_UPDATE_MODE_STORAGE;
		} elseif (t3lib_div::_GP('tx_naworkuri_update_mode') == 'deleted') {
			$this->mode = self::TX_NAWORKURI_UPDATE_MODE_CLEANUP_DELETE;
		} elseif (t3lib_div::_GP('tx_naworkuri_update_mode') == 'domain') {
			$this->mode = static::TX_NAWORKURI_UPDATE_MODE_DOMAIN;
		}
	}

	private function countRecords() {
		switch ($this->mode) {
			case ext_update::TX_NAWORKURI_UPDATE_MODE_COOLURI:
				break;
			case ext_update::TX_NAWORKURI_UPDATE_MODE_STORAGE:
				return $this->db->exec_SELECTcountRows('uid', 'tx_naworkuri_uri', 'pid!=' . intval($this->extConf['storagePage']) . ' AND deleted=0');
				break;
			case self::TX_NAWORKURI_UPDATE_MODE_CLEANUP_DELETE:
				return $this->db->exec_SELECTcountRows('uid', 'tx_naworkuri_uri', 'deleted=1');
				break;
			case static::TX_NAWORKURI_UPDATE_MODE_DOMAIN:
				return $this->db->exec_SELECTcountRows('uid', 'tx_naworkuri_uri', 'domain NOT REGEXP "^[0-9]+$"');
			default:
				return $this->db->exec_SELECTcountRows('uid', 'tx_naworkuri_uri', '(pid!=' . intval($this->extConf['storagePage']) . ' OR ((page_uid=0 AND type=0) OR (sticky=1 AND locked=0))) AND deleted=0');
				break;
		}
	}

	private function renderModeForm() {
		$output = '';
		$output .= '<form action="" method="post">
				<input type="hidden" name="doCheck" value="1" />
				<label for="tx_naworkuri_update_mode">Mode:</label>
				<select id="tx_naworkuri_update_mode" name="tx_naworkuri_update_mode">
				<option value="old_version">Update from an old version of nawork_uri</option>';
		if ($this->coolUriWasInstalled) {
			$output .= '<option value="cooluri">Update from cooluri</option>';
		}
		$output .= '<option value="storage"' . ($this->mode == ext_update::TX_NAWORKURI_UPDATE_MODE_STORAGE ? ' selected="selected"' : '') . '>Update storage page</option>';
		$output .= '<option value="deleted"' . ($this->mode == self::TX_NAWORKURI_UPDATE_MODE_CLEANUP_DELETE ? ' selected="selected"' : '') . '>Remove deleted urls</option>';
		$output .= '<option value="domain"' . ($this->mode == static::TX_NAWORKURI_UPDATE_MODE_DOMAIN ? ' selected="selected"' : '') . '>Update domain field for uri records</option>';
		$output .= '</select>
				<input type="submit" value="Check" />
				</form>';
		return $output;
	}

	private function renderStatus() {
		$this->numRecords = $this->countRecords();
		$output = '';
		switch ($this->mode) {
			case ext_update::TX_NAWORKURI_UPDATE_MODE_COOLURI:
				break;
			case ext_update::TX_NAWORKURI_UPDATE_MODE_STORAGE:
				if ($this->numRecords > 0) {
					$output .= '<p>There are ' . $this->numRecords . ' records that need to be updated!</p>';
					$output .= '<form action="" method="post">';
					$output .= '<input type="hidden" name="doUpdate" value="1" />';
					$output .= '<input type="hidden" name="tx_naworkuri_update_mode" value="storage" />';
					$output .= '<input type="submit" value="Update!" />';
					$output .= '</form>';
				} else {
					$output .= '<p>URL records are up-to-date! There is nothing to do!</p>';
				}
				break;
			case self::TX_NAWORKURI_UPDATE_MODE_CLEANUP_DELETE:
				if ($this->numRecords > 0) {
					$output .= '<p>There are ' . $this->numRecords . ' records that need to be deleted!</p>';
					$output .= '<form action="" method="post">';
					$output .= '<input type="hidden" name="doUpdate" value="1" />';
					$output .= '<input type="hidden" name="tx_naworkuri_update_mode" value="deleted" />';
					$output .= '<input type="submit" value="Update!" />';
					$output .= '</form>';
				} else {
					$output .= '<p>URL records are up-to-date! There is nothing to do!</p>';
				}
				break;
			case static::TX_NAWORKURI_UPDATE_MODE_DOMAIN:
				if ($this->numRecords > 0) {
					$output .= '<p>There are ' . $this->numRecords . ' records that need to be updated with the domain record\'s uid!</p>';
					$output .= '<form action="" method="post">';
					$output .= '<input type="hidden" name="doUpdate" value="1" />';
					$output .= '<input type="hidden" name="tx_naworkuri_update_mode" value="domain" />';
					$output .= '<input type="submit" value="Update!" />';
					$output .= '</form>';
				} else {
					$output .= '<p>URL records are up-to-date! There is nothing to do!</p>';
				}
				break;
			default:
				if ($this->numRecords > 0) {
					$output .= '<p>There are ' . $this->numRecords . ' records that need to be updated!</p>';
					$output .= '<form action="" method="post">';
					$output .= '<input type="hidden" name="doUpdate" value="1" />';
					$output .= '<input type="hidden" name="tx_naworkuri_update_mode" value="old_version" />';
					$output .= '<input type="submit" value="Update!" />';
					$output .= '</form>';
				} else {
					$output .= '<p>URL records are up-to-date! There is nothing to do!</p>';
				}
				break;
		}
		return $output;
	}

	private function renderHeader() {
		switch ($this->mode) {
			case ext_update::TX_NAWORKURI_UPDATE_MODE_COOLURI:
				return '<h2>Update from an CoolURI installation</h2>';
			case ext_update::TX_NAWORKURI_UPDATE_MODE_STORAGE:
				return '<h2>Update the storage page to the current value</h2>';
			case self::TX_NAWORKURI_UPDATE_MODE_CLEANUP_DELETE:
				return '<h2>Remove deleted urls from database</h2>';
			case static::TX_NAWORKURI_UPDATE_MODE_DOMAIN:
				return '<h2>Update domain field to point to the domain record\'s uid</h2>';
			default:
				return '<h2>Update from an old nawork_uri version</h2>';
		}
	}

	private function checkIfCoolUriWasInstalled() {
		if (is_dir(PATH_site . 'typo3conf/ext/cooluri')) {
			$res = $GLOBALS['TYPO3_DB']->sql_query('SHOW TABLES');
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_row($res)) {
				if ($row[0] == 'link_cache') {
					$this->coolUriWasInstalled = TRUE;
				}
			}
		}
	}

	/**
	 * Checks how many rows are found and returns true if there are any
	 * (this function is called from the extension manager)
	 *
	 * @param	string		$what: what should be updated
	 * @return	boolean
	 */
	function access($what = 'all') {
		return TRUE;
	}

}

?>
