<?php

namespace Is\ServerStatus\Tools;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Context;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime as BxDateTime;
use Bitrix\Main\Config\Option as BxOption;
use Bitrix\Main\Web\HttpClient;
use DateTimeInterface;
use Is\ServerStatus\Config\Predefined\RestAccessKeyOption;

class Helper
{
    protected const PERFORMANCE_CHECK_STEPS_MAX = 10;
    
    public function getAdminId($groupId = '1'): int
    {
        $arFilter = ["GROUPS_ID" => [$groupId]]; // admin GROUPS_ID may need to be an admin option
        $arParam = ["FIELDS" => ['ID']];
        $resUsers = \CUser::GetList('', '', $arFilter, $arParam);
        while ($arUser = $resUsers->Fetch()) {
            if ($arUser['ID'] > 0) {
                return (int) $arUser['ID'];
            }
        }
        
        return 0;
    }
    
    public function authAsAdmin($groupId = '1'): bool
    {
        global $USER;
        $adminId = $this->getAdminId($groupId);
        
        return $USER->Authorize($adminId);
    }

    public function GetMessage(array $arMess, string $name, ?array $aReplace=null): ?string
    {
        if (isset($arMess[$name])) {
            $s = $arMess[$name];
            if ($aReplace !== null && is_array($aReplace)) {
                foreach($aReplace as $search => $replace) {
                    $s = str_replace($search, $replace, $s);
                }
            }
            return $s;
        }
        
        return null;
    }
    public function getPerfmonData(string $adminUid = '1'): array
    {
        global $USER;
        // globals need for eval()
        global $APPLICATION;
        global $DB;

        $USER->Authorize($adminUid);

        $lang = Loc::getCurrentLang() ?? 'en';
        require ($_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/perfmon/lang/'.$lang.'/admin/perfmon_db_server.php');

        $path = $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/perfmon/admin/perfmon_db_server.php';
        $file = new \SplFileObject($path, 'r');
        $sEval = '';

        // Warning: This MAY not work with some versions of perfmon_db_server.php
        while (!$file->eof()) {
            $line = $file->fgets();
            
            if (strpos($line, '/bitrix/modules/main/include/prolog_admin_after.php') !== false) {
                break;
            }
            
            if (strpos($line, '<?') !== false || empty(trim($line))) {
                continue;
            }
            
            $sEval .= $line;
        }
        $file = null;
        
        try {
            eval(trim($sEval));

            /**
             * @var array $data - using variable from perfmon_db_server.php
             * @var array $MESS - using variable from lang file
             */

            if (isset($data[0]['ITEMS'])) {
                $data = $data[0]['ITEMS'];
            }
            
            return array(
                'items' => $data,
                'mess' => $MESS,
            );
            
        } catch (\Exception $exception) {
            return array(
                'error_code' => 500,
                'mess' => $exception->getMessage(),
            );
        }
    }

    public function testPerformance(): void
    {
        @set_time_limit(0);

        // Clear old performance times
        BxOption::delete(IS_SRVSTAT_MODULE_ID, ['name' => 'perfmon_times']);
        
        $siteAddress = BxOption::get(IS_SRVSTAT_MODULE_ID, "site_address");
        $url = $siteAddress . '/bitrix/services/' . IS_SRVSTAT_MODULE_ID . '/rest.php';
        $apiKeyOptionValue = (new RestAccessKeyOption)->loadValue()->get();
        $url .= '?token=' . $apiKeyOptionValue . '&action=performance-test';

        $httpClient = new HttpClient();
        $httpClient->setHeader('Cookie', 'XDEBUG_SESSION=PHPSTORM');
        
        $log = [];
        $step = self::PERFORMANCE_CHECK_STEPS_MAX;
        while ($step >= 0) {
            if ($step == 0) {
                $url .= "&last=Y";
            }
            $result = $httpClient->get($url);
            $step--;
        }
    }
    
    public static function getActiveAgents(): array {
        $arResult = array();
        $rsAgents = \CAgent::GetList([], ['ACTIVE' => 'Y']);
        while ($arAgent = $rsAgents->Fetch()) {
            $arResult[] = $arAgent;
        }
        return $arResult;
    }

    /**
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentException
     */
    public static function getActiveSites(): array {
        $arResult = array();
        $rsSites = (new \Bitrix\Main\SiteTable)->getList(array(
            'filter' => ['=ACTIVE' => 'Y'],
        ));
        while ($arSite = $rsSites->Fetch()) {
            $arResult[$arSite['SERVER_NAME']] = $arSite;
        }
        
        return $arResult;
    }

    public static function formatDateTime(?string $datetime): ?string {
        if (empty($datetime)) {
            return null;
        }

        global $DB;
        if (!$DB->IsDate($datetime)) {
            return 'wrong datetime format';
        }
        
        $bxDateTime = new BxDateTime($datetime);
        return $bxDateTime->format(DateTimeInterface::ATOM);
        
    }
}