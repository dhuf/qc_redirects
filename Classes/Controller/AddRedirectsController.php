<?php
declare(strict_types=1);
/***
 *
 * This file is part of Qc Redirects project.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2022 <techno@quebec.ca>
 *
 ***/

namespace Qc\QcRedirects\Controller;

use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use Doctrine\DBAL\Driver\Exception;
use Psr\Http\Message\ServerRequestInterface;
use Qc\QcRedirects\Domaine\Repository\ImportRedirectsRepository;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class AddRedirectsController extends ActionController
{

    protected const LANG_FILE = 'LLL:EXT:qc_redirects/Resources/Private/Language/locallang.xlf:';
    /**
     * ModuleTemplate object
     *
     * @var ModuleTemplate
     */
    protected $moduleTemplate;

    /**
     * ModuleTemplate object
     *
     * @var ModuleTemplateFactory
     */
    protected $moduleTemplateFactory;

    /**
     * @var LocalizationUtility
     */
    protected $localizationUtility;


    /**
     * @var array|string[]
     */
    protected array $separators  = [
        'semicolon' => ';',
        'tabulation' => "\t",
        'pipe' => '|',
        'colon' => ':',
        'comma' => ',',
    ];

    /**
     * @var string
     */
    protected string $selectedSeparatedChar = '';

    /**
     * @var array
     */
    protected array $extraFields = [];

    /**
     * @var ImportFormValidator
     */
    protected ImportFormValidator $importFormValidator;

    /**
     * @var ImportRedirectsRepository
     */
    protected ImportRedirectsRepository  $importRedirectsRepository;

    /**
     * @var StandaloneView
     */
    protected $view;

    /**
     * @var PageRenderer
     */
    protected $pageRenderer;

    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * @var Icon
     */
    protected $icon;

    /**
     * The extension key of the controller extending this class
     *
     * @var string
     */
    protected $extKey;

    /**
     * The module name of the backend module extending this class
     * @var string
     */
    protected $moduleName;

    public function __construct(
    ) {
        $this->moduleTemplateFactory = GeneralUtility::makeInstance(ModuleTemplateFactory::class);
        $this->pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $this->localizationUtility = GeneralUtility::makeInstance(LocalizationUtility::class);
        $this->pageRenderer->addCssFile('EXT:qc_redirects/Resources/Public/Css/qc_redirects.css');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/QcRedirects/QcRedirectModuleBE');
        $this->view ??= GeneralUtility::makeInstance(StandaloneView::class);
        $this->importRedirectsRepository = GeneralUtility::makeInstance(ImportRedirectsRepository::class);
        $this->importFormValidator = GeneralUtility::makeInstance(ImportFormValidator::class);
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->icon = $this->iconFactory->getIcon('actions-document-export-csv', Icon::SIZE_SMALL);

    }

    /**
     * Set up the doc header properly here
     *
     * @return void
     * @throws RouteNotFoundException
     */
    protected function initializeView($request)
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create( $this->request);
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/ContextMenu');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Modal');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Recordlist/Tooltip');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/AjaxDataHandler');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Recordlist/Recordlist');
        $this->moduleTemplate->assignMultiple([
            'separators' => $this->separators,
            'icon' => $this->icon
        ]);

    }

    public function initializeAction()
    {

        $this->extKey = $this->request->getControllerExtensionKey();
        $this->moduleName = $this->request->getPluginName();
    }

    /**
     * this function is used to receive data form the BE form
     * @param ServerRequestInterface|null $request
     * @throws Exception
     */
    public function importAction(?ServerRequestInterface $request = null): ResponseInterface
    {

        $requestBody = [];
        if($request == null){
            return $this->moduleTemplate->renderResponse('Import');
        }
        if($request->getParsedBody() !== null){
            $this->extraFields = GeneralUtility::trimExplode(',',$request->getParsedBody()['extraFields'], true);
            if(!$this->importFormValidator->checkForInvalidFields($this->extraFields) || !$this->importFormValidator->checkForReadOnlyFields($this->extraFields)){
                $this->generateAlertMessage(false);
                // we display the inserted data when an error appears
                $requestBody = $request->getParsedBody();
            }
            else{
                $redirectsList = $request->getParsedBody()['redirectsList'];
                $this->selectedSeparatedChar = $request->getParsedBody()['separationCharacter'] ?? ';';
                // convert data to array
                $redirectsListArray = explode("\r\n", $redirectsList);
                $created = $this->processRedirects($redirectsListArray);
                if(!$created)
                    $requestBody = $request->getParsedBody();
                // alert message
                $this->generateAlertMessage($created);
            }
        }
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assignMultiple([
            'separators' => $this->separators,
            'icon' => $this->icon,
            'redirectsList' => $requestBody['redirectsList'] ?? '',
            'separationCharacter' => $requestBody['separationCharacter'] ?? '',
            'extraFields' => $requestBody['extraFields'] ?? ''
        ]);


        return $moduleTemplate->renderResponse('Import');
    }
    public function resetAction(?ServerRequestInterface $request = null): ResponseInterface{
        return (new ForwardResponse('import'));
    }
    /**
     * This function will be used to get the imported data header, by combining mandatory fields with additional fields
     * @return array
     */
    protected function getImportedDataHeader() : array{
        return [];
    }

    /**
     * This function returns True if the list was successfully stored in the database
     * @param array $redirectListArray
     * @return bool
     * @throws Exception
     */
    protected function processRedirects(array $redirectListArray): bool
    {
        $validImport = true;
        $redirectEntities = [];
        // get the source_paths from the DB
        $sourcePathArray = $this->importRedirectsRepository->getSourcePaths();
        // precessing each line in the array
        foreach ($redirectListArray as $item){
            // separate the row columns
            $separator = $this->separators[$this->selectedSeparatedChar] ?? ';';
            $row = GeneralUtility::trimExplode($separator,$item);
            // empty lines
            if($row[0] === '')
                continue;
            $mappedRow = [];
            $index = 0;
            $this->importFormValidator->setRowsConstraints(array_merge($this->importFormValidator->getMandatoryFields(), $this->extraFields));
            // adding optional fields to be validated
            foreach ($this->importFormValidator->getRowsConstraints() as $fieldName){
                if($row[$index] ?? false){
                    $mappedRow[$fieldName] = $row[$index];
                }
                 $index++;
            }

            // remove white spacing
            if($this->selectedSeparatedChar !== "tabulation"){
                $row[0] = preg_replace('/\s+/', '', $row[0] ?? '');
                $row[1] = preg_replace('/\s+/', '', $row[1] ?? '');
            }
            // empty line
            if(count($row) == 1 && $row[0] == ''){
                continue;
            }

            // make sure that we have all important fields
            if(count($row) == count($this->importFormValidator->getRowsConstraints())){
                // verify if the source path,source host, target value is not empty
                if(!$this->importFormValidator->verifyMandatoryColumnsExistence($mappedRow)){
                    $validImport = false;
                    break;
                }
                // Map Row to Redirect Entity
                // verify if the source_path is already exists
                if(in_array($mappedRow['source_path'], $sourcePathArray, TRUE)){
                    $validImport = false;
                    $this->importFormValidator->setDuplicatedSourcePath($mappedRow['source_path']);
                    $this->importFormValidator->setErrorsTypes(
                        'duplicatedSourcePath',
                        true,
                        $this->importFormValidator->getDuplicatedSourcePath()  .'"'.$this->localizationUtility->translate(self::LANG_FILE.'is_duplicated')
                    );
                    break;
                }
                // verify fields values
                $index = 0;
                foreach ($mappedRow as $key => $value){
                    $renderType = $this->importFormValidator->getAllowedAdditionalFields()[$key]['config']['renderType'] ??
                        $this->importFormValidator->getAllowedAdditionalFields()[$key]['config']['type'] ?? null;
                    if($renderType == 'inputDatetime'){
                        $renderType = 'datetime';
                    }
                    if($renderType != null &&  in_array($renderType, $this->importFormValidator->getCheckingRules() )){
                        $checkingMethodName = $renderType.'Verify';
                        if(!$this->importFormValidator->$checkingMethodName($key,$value)){
                            $this->importFormValidator->setWrongValuekey($index);
                            $this->importFormValidator->setErrorsTypes(
                                'invalidValue',
                                true,
                                " ' ".$this->importFormValidator->getRowsConstraints()[$this->importFormValidator->getWrongValuekey()]." '"
                            );
                            $validImport = false;
                            break;
                        }
                        else{
                            switch ($renderType){
                                case 'datetime' : $mappedRow[$key] = strtotime($value); break;
                                case 'checkboxToggle' : $mappedRow[$key] = $value == 'true' ? 1 : 0;
                            }
                        }
                    }
                    $index++;
                }
                $sourcePathArray[] = $mappedRow['source_path'];
                $redirectEntities[] = $mappedRow;
            }
            else{
                $this->importFormValidator->setErrorsTypes(
                    'syntaxError',
                    true,
                    ''
                );
                $validImport = false;
                break;
            }
        }
        // save the items if all import are valid
        if($validImport && !is_null($redirectEntities) && count($redirectEntities) > 0){
            $this->importRedirectsRepository->saveRedirects($redirectEntities);
            return true;
        }
        return false;
    }

    /**
     * This function is used to generate alert message
     * @param bool $success
     */
    public function generateAlertMessage(bool $success){
        $body = $success ? 'success' : 'error';
        $flashServiceMessage = $success ? AbstractMessage::OK : AbstractMessage::ERROR;
        $alertMessageBody =  $this->localizationUtility->translate(self::LANG_FILE.$body);
        $flashMessageService = $flashServiceMessage;
        $alertMessageHeader = $success ? $this->localizationUtility->translate(self::LANG_FILE.'import_success_body')
                                : $this->importFormValidator->getErrorMessage();

        $message = GeneralUtility::makeInstance(FlashMessage::class,
            $alertMessageHeader,
            $alertMessageBody,
            $flashMessageService,
        );
        $flashService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashService->getMessageQueueByIdentifier();
        $messageQueue->addMessage($message);
    }

}
