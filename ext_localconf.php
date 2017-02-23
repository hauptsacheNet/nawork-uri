<?php

use Nawork\NaworkUri\Controller\Frontend\UrlController;
use Nawork\NaworkUri\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;

// hook into typolink to comvert params 2 path
$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tstemplate.php']['linkData-PostProc']['nawork_uri'] = 'EXT:nawork_uri/Classes/Controller/Frontend/UrlController.php:&Nawork\NaworkUri\Controller\Frontend\UrlController->params2uri';
// hook to extract params from path
$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_fe.php']['checkAlternativeIdMethods-PostProc']['nawork_uri'] = 'EXT:nawork_uri/Classes/Controller/Frontend/UrlController.php:&Nawork\NaworkUri\Controller\Frontend\UrlController->uri2params';
// redirect old urls to new
$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_fe.php']['configArrayPostProc']['nawork_uri'] = 'EXT:nawork_uri/Classes/Controller/Frontend/UrlController.php:&Nawork\NaworkUri\Controller\Frontend\UrlController->redirect2uri';
$TYPO3_CONF_VARS['SC_OPTIONS']['tce']['formevals']['tx_naworkuri_path'] = 'EXT:nawork_uri/Classes/Validation/class.tx_naworkuri_path.php';

// register transformation services
\Nawork\NaworkUri\Utility\TransformationUtility::registerTransformationService('Hidden', \Nawork\NaworkUri\Transformation\Hidden\TransformationService::class);
\Nawork\NaworkUri\Utility\TransformationUtility::registerTransformationService('Plain', \Nawork\NaworkUri\Transformation\Plain\TransformationService::class);
\Nawork\NaworkUri\Utility\TransformationUtility::registerTransformationService('ValueMap', \Nawork\NaworkUri\Transformation\ValueMap\TransformationService::class);
\Nawork\NaworkUri\Utility\TransformationUtility::registerTransformationService('PagePath', \Nawork\NaworkUri\Transformation\PagePath\TransformationService::class);
\Nawork\NaworkUri\Utility\TransformationUtility::registerTransformationService('Database', \Nawork\NaworkUri\Transformation\Database\TransformationService::class);

// register default configuration, but do not override if default is already set
if (class_exists(ConfigurationUtility::class)) { // check this to avoid php error when activating the extension
	ConfigurationUtility::registerConfiguration(
		'default',
		'EXT:nawork_uri/Configuration/Url/DefaultConfiguration.xml',
		FALSE
	);
}

// configure caching framework
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['naworkuri_configuration'])) {
	$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['naworkuri_configuration'] = array();
}
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['naworkuri_configuration']['backend'])) {
	$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['naworkuri_configuration']['backend'] = Typo3DatabaseBackend::class;
}

// init hook object array
$GLOBALS['TYPO3_CONF_VARS']['EXT']['tx_naworkuri'][UrlController::class] = array();

// add nawork-uri fields to pageOverlayFields
$GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields'] .= ',tx_naworkuri_pathsegment,tx_naworkuri_exclude';

// slot registration
$extensionConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['nawork_uri']);
if ($extensionConfiguration['pageNotFoundBasicLanguageSupportEnable']) {
    $signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class
    );
    $signalSlotDispatcher->connect(
        \Nawork\NaworkUri\Controller\Frontend\UrlController::class,
        'afterSetting404PageId',
        \Nawork\NaworkUri\Slot\AfterSetting404PageId::class,
        'afterSetting404PageId'
    );

    $signalSlotDispatcher->connect(
        \Nawork\NaworkUri\Controller\Frontend\UrlController::class,
        'beforeInternal404Request',
        \Nawork\NaworkUri\Slot\BeforeInternal404Request::class,
        'beforeInternal404Request'
    );
}
