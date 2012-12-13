<?php

if (!defined('TYPO3_MODE'))
	die('Access denied.');


// add new fields to page and pages_language_overlay records
$tempColumns = Array(
	'tx_naworkuri_pathsegment' => array(
		'label' => 'LLL:EXT:nawork_uri/Resources/Language/locallang_db.xml:pages.tx_naworkuri_pathsegment',
		'config' => Array(
			'type' => 'input',
			'size' => '60',
			'max' => '60',
		),
	),
	'tx_naworkuri_exclude' => array(
		'label' => 'LLL:EXT:nawork_uri/Resources/Language/locallang_db.xml:pages.tx_naworkuri_exclude',
		'config' => Array(
			'type' => 'check',
			'default' => '0'
		)
	)
);

t3lib_div::loadTCA('pages');
t3lib_extMgm::addTCAcolumns('pages', $tempColumns, 1);
t3lib_extMgm::addToAllTCAtypes('pages', 'tx_naworkuri_pathsegment,tx_naworkuri_exclude', '', 'after:title');

t3lib_div::loadTCA('pages_language_overlay');
t3lib_extMgm::addTCAcolumns('pages_language_overlay', $tempColumns, 1);
t3lib_extMgm::addToAllTCAtypes('pages_language_overlay', 'tx_naworkuri_pathsegment,tx_naworkuri_exclude', '', 'after:title');

$tempColumns = array(
	'tx_naworkuri_masterDomain' => array(
		'label' => 'LLL:EXT:nawork_uri/Resources/Language/locallang_db.xml:sys_domain.tx_naworkuri_masterDomain',
		'config' => array(
			'type' => 'group',
			'internal_type' => 'db',
			'allowed' => 'sys_domain',
			'size' => '1',
			'minitems' => '0',
			'maxitems' => '1',
		),
	),
);
t3lib_div::loadTCA('sys_domain');
t3lib_extMgm::addTCAcolumns('sys_domain', $tempColumns, 1);
t3lib_extMgm::addToAllTCAtypes('sys_domain', 'tx_naworkuri_masterDomain');

// add URI-Records
$TCA['tx_naworkuri_uri'] = Array(
	'ctrl' => Array(
		'title' => 'URI',
		'label' => 'path',
		'type' => 'type',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'languageField' => 'sys_language_uid',
		'sortby' => 'crdate',
		'delete' => '',
		'rootLevel' => '-1',
		'enablecolumns' => array(
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY) . 'tca.php',
		'iconfile' => t3lib_extMgm::extRelPath($_EXTKEY) . 'Resources/GFX/Icons/uri.png',
		'hideTable' => true,
		'typeicon_column' => 'type',
		'typeicons' => Array(
			'0' => t3lib_extMgm::extRelPath($_EXTKEY) . 'Resources/GFX/Icons/uri.png',
			'1' => t3lib_extMgm::extRelPath($_EXTKEY) . 'Resources/GFX/Icons/uri_old.png',
			'2' => t3lib_extMgm::extRelPath($_EXTKEY) . 'Resources/GFX/Icons/redirect.png',
		),
	),
);

t3lib_extMgm::allowTableOnStandardPages('tx_naworkuri_uri');

if (TYPO3_MODE == 'BE') {
	t3lib_extMgm::addModule('web', 'txnaworkuriM1', '', t3lib_extMgm::extPath($_EXTKEY) . 'mod1/');
}

?>
