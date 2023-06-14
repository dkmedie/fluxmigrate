<?php

namespace DKM\FluxMigrate;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Utility
{
    const convertXMLToNonNamespace = 1;
    const convertXMLToNamespace = 2;

    const defaultXMLNamespaces = [
        ['key' => 'f',
        'vendor' => 'TYPO3Fluid',
        'name' => 'Fluid'],
        ['key' => 'flux',
            'vendor' => 'FluidTYPO3',
            'name' => 'Flux'],
        ['key' => 'v',
            'vendor' => 'FluidTYPO3',
            'name' => 'VHS']
    ];

    public static function convertXMLToNonNamespace($xml)
    {
        return self::convertXMLNamespace($xml);
    }

    public static function convertXMLToNamespace($xml)
    {
        return self::convertXMLNamespace($xml, self::convertXMLToNamespace);
    }

    /**
     * @param $xml
     * @param int $direction
     * @return array|string|string[]|void
     * @throws Exception
     */
    public static function convertXMLNamespace($xml, int $direction = self::convertXMLToNonNamespace)
    {
        if(!($direction === self::convertXMLToNonNamespace || $direction === self::convertXMLToNamespace)) {
            throw new Exception('Second argument should be const convertXMLToNonNamespace or convertXMLToNamespace');
        }
        $namespace = $nonNamespace = [];
        foreach (self::defaultXMLNamespaces as $XMLNamespace) {
            $namespace[] = "<{$XMLNamespace['key']}:";
            $nonNamespace[] = "<{$XMLNamespace['vendor']}{$XMLNamespace['name']}.";
            $namespace[] = "</{$XMLNamespace['key']}:";
            $nonNamespace[] = "</{$XMLNamespace['vendor']}{$XMLNamespace['name']}.";
        }
        $namespace = array_map('strtolower', $namespace);
        $nonNamespace = array_map('strtolower', $nonNamespace);
        switch ($direction) {
            case self::convertXMLToNonNamespace:
                return str_replace($namespace, $nonNamespace, $xml);
            case self::convertXMLToNamespace:
                $xml = str_replace($nonNamespace, $namespace, $xml);

                // hotfixes
                $xml = urldecode($xml);
                return str_replace(['maxwidth', 'treatidasreference'], ['maxWidth', 'treatIdAsReference'], $xml);
        }
    }

    /**
     * @param $selector
     * @return array|string|string[]
     */
    public static function domCrawlerEscapeForSelector($selector)
    {
        return str_replace(['.'], ['\.'], $selector);
    }



    /**
     * @param string $value
     * @param string $delimiters
     * @param bool $lowerCamelCase
     * @return string
     */
    public static function camelCase(string $value, string $delimiters = '.', bool $lowerCamelCase = false): string
    {
        $value =  str_replace(str_split($delimiters), "", ucwords($value, $delimiters));
        return $lowerCamelCase ? lcfirst($value) : ucfirst($value);
    }

    /**
     * @param $uid
     * @param bool $deleteReference
     * @return void
     * @throws ResourceDoesNotExistException
     */
    public static function getFileUidFromReference($uid, bool $deleteReference)
    {
        $fileUid = null;
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        if($reference = $resourceFactory->getFileReferenceObject($uid)) {
            $fileUid = $reference->getOriginalFile()->getUid();
            // Delete old reference
            if($deleteReference) {
                GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_file_reference')
                    ->delete('sys_file_reference',['uid' => $uid]);
            }
        };
        return $fileUid;
    }

    public static function getFileReferences($fieldName, $contentUid) {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_file_reference');
        return $connection->select(['*'],'sys_file_reference', ['tablenames' => 'tt_content', 'uid_foreign' => $contentUid, 'fieldname' => $fieldName])->fetchAllAssociative();
    }

    /**
     * @param int $fileUid
     * @param int $contentUid
     * @param int $contentPid
     * @param $fieldName
     * @return void
     */
    public static function addFileReference(int $fileUid, int $contentUid, int $contentPid, $fieldName)
    {
//        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
//        $fileObject = $resourceFactory->getFileObject((int)$fileUid);
        // Assemble DataHandler data
        $newId = 'NEW1234';
        $data = [];
        $data['sys_file_reference'][$newId] = [
            'table_local' => 'sys_file',
            'uid_local' => $fileUid,
            'tablenames' => 'tt_content',
            'uid_foreign' => $contentUid,
            'fieldname' => $fieldName,
            'pid' => $contentPid
        ];
        $data['tt_content'][$contentUid] = [
            $fieldName => $newId
        ];

        $languageService = GeneralUtility::makeInstance(LanguageService::class);
        $GLOBALS['LANG'] = $languageService;
        $beUser = new BackendUserAuthentication();
        $beUser->user['admin'] = 1;
        $beUser->workspace = 0;

        // Get an instance of the DataHandler and process the data
        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, [], $beUser);
        $dataHandler->process_datamap();
        // Error or success reporting
        if (count($dataHandler->errorLog) === 0) {
            $test = 1;
            // Handle success
        } else {
            $test = 1;
            // Handle errors
        }
    }
}