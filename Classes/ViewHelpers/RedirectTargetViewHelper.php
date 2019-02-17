<?php

namespace Nawork\NaworkUri\ViewHelpers;

use Nawork\NaworkUri\Domain\Model\Domain;
use Nawork\NaworkUri\Domain\Model\Language;
use Nawork\NaworkUri\Utility\ConfigurationUtility;
use Nawork\NaworkUri\Utility\TransformationUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;

class RedirectTargetViewHelper extends AbstractViewHelper
{

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('url', 'Nawork\NaworkUri\Domain\Model\Url', 'The url to get the redirect target for', true);
        $this->registerArgument('as', 'string', 'Variable name, that the result should be assigned to, when rendering the children');
    }

    /**
     * @param \Nawork\NaworkUri\Domain\Model\Url $url
     * @param string|null                        $as
     *
     * @return string
     */
    public function render($url, $as = null)
    {
        $pageUid = $url->getPageUid();
        $parameters = $url->getParameters();
        $language = $url->getLanguage();
        if ($language instanceof Language) {
            $language = $language->getUid();
        }
        if ($language === null) {
            $language = 0;
        }

        $domainName = $url->getDomain() instanceof Domain ? $url->getDomain()->getDomainname() : GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY');
        ConfigurationUtility::getConfiguration($domainName);
        /* @var $translator \Nawork\NaworkUri\Utility\TransformationUtility */
        $translator = GeneralUtility::makeInstance(TransformationUtility::class);
        $newUrlParameters = array_merge(
            \Nawork\NaworkUri\Utility\GeneralUtility::explode_parameters($parameters),
            ['id' => $pageUid, 'L' => $language]
        );
        // try to find a new url or create one, if it does not exist
        try {
            $newUrl = $translator->params2uri(
                \Nawork\NaworkUri\Utility\GeneralUtility::implode_parameters($newUrlParameters, false),
                false,
                true
            );
            if (substr($newUrl, 0, 1) !== '/') {
                $newUrl = '/' . $newUrl;
            }
        } catch (\Exception $e) {
            /**
             * @todo Log this some where
             */
            $newUrl = '';
        }

        if (!empty($as)) {
            $this->templateVariableContainer->add($as, $newUrl);
            $content = $this->renderChildren();
            $this->templateVariableContainer->remove($as);

            return $content;
        }

        return $newUrl;
    }
}
