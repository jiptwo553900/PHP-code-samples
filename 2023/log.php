<?php

namespace Is\Core\Log;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Entity;
use Bitrix\Main\Context;
use Bitrix\Main\ORM\Data\AddResult;
use Bitrix\Main\Type;
use Bitrix\Main\Type\DateTime;
use Is\Core\Orm\AdminHelper;
use Is\Core\Tools;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

Loc::loadMessages(__FILE__);

require_once realpath(__DIR__.'/../orm/adminhelper.php');
require_once realpath(__DIR__.'/../tools.php');

/**
 * Class LogTable
 *
 * @package Is\Core
 **/
class LogTable extends Entity\DataManager
{
    protected const MODULE_ID = Tools::MODULE_ID;
    public const DAYS_KEEP_DEFAULT  = 14;
    
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName(): string
    {
        return 'is_core_log';
    }

    /**
     * Returns entity map definition.
     *
     * @return array
     */
    public static function getMap(): array
    {
        return array(
            
            //---------required fields---------//
            
            'ID' => array(
                'data_type' => 'integer',
                'primary' => true,
                'autocomplete' => true,
                'title' => Loc::getMessage('IS_CORE_LOG_ENTITY_FIELD_ID'),
                
                'default_in_list' => true,
                'in_filter' => true,
                'admin_edit' => AdminHelper::U_SHOW,
            ),
            
            'TIMESTAMP' => array(
                'data_type' => 'datetime',
                'required' => true,
                'title' => Loc::getMessage('IS_CORE_LOG_ENTITY_FIELD_TIMESTAMP'),
                
                'nullable' => false,
                'default_value' => new Type\DateTime(),
                
                'default_in_list' => true,
                'in_filter' => false,
                'admin_edit' => AdminHelper::U_SHOW,
            ),
            
            'LOG_LEVEL' => array(
                'data_type' => 'string',
                'required' => true,
                'title' => Loc::getMessage('IS_CORE_LOG_ENTITY_FIELD_LOG_LEVEL'),
                
                'validation' => array(__CLASS__, 'validateLEVEL'),
                
                'nullable' => false,
                'default_value' => self::getLevels()['DEBUG'],
                
                'default_in_list' => true,
                'in_filter' => true,
                'admin_edit' => AdminHelper::U_SHOW,
                
                'input' => 'select',
                'get_values_callback' => 'getLevels',
                'get_values_args' => array(),
            ),
            
            'TAG' => array(
                'data_type' => 'string',
                'required' => true,
                'title' => Loc::getMessage('IS_CORE_LOG_ENTITY_FIELD_TAG'),

                'nullable' => true,
                'default_value' => null,

                'default_in_list' => true,
                'in_filter' => true,
                'admin_edit' => AdminHelper::U_SHOW,
            ),

            'DATA' => array(
                'data_type' => 'text',
                'required' => true,
                'title' => Loc::getMessage('IS_CORE_LOG_ENTITY_FIELD_DATA'),

                'nullable' => false,
                'default_value' => '',

                'default_in_list' => true,
                'in_filter' => true,
                'admin_edit' => AdminHelper::U_SHOW,
            ),

            //---------optional fields---------//
            
            'URL' => array(
                'data_type' => 'string',
                'required' => false,
                'title' => Loc::getMessage('IS_CORE_LOG_ENTITY_FIELD_URL'),
                
                'nullable' => true,
                'default_value' => null,
                
                'default_in_list' => true,
                'in_filter' => true,
                'admin_edit' => AdminHelper::U_SHOW,
            ),
            
            'USER_ID' => array(
                'data_type' => 'integer',
                'required' => false,
                'title' => Loc::getMessage('IS_CORE_LOG_ENTITY_FIELD_USER_ID'),
                
                'nullable' => true,
                'default_value' => null,
                
                'default_in_list' => true,
                'in_filter' => true,
                'admin_edit' => AdminHelper::U_SHOW,
            ),
            
            'SITE_LID' => array(
                'data_type' => 'string',
                'required' => false,
                'title' => Loc::getMessage('IS_CORE_LOG_ENTITY_FIELD_SITE_LID'),
                
                'nullable' => true,
                'default_value' => null,

                'default_in_list' => true,
                'in_filter' => true,
                'admin_edit' => AdminHelper::U_SHOW,
                
                'input' => 'select',
                'get_values_callback' => 'getSites',
                'get_values_args' => array(),
            ),

            'REQUEST_METHOD' => array(
                'data_type' => 'string',
                'required' => false,
                'title' => Loc::getMessage('IS_CORE_LOG_ENTITY_FIELD_REQUEST_METHOD'),

                'nullable' => true,
                'default_value' => null,

                'default_in_list' => true,
                'in_filter' => true,
                'admin_edit' => AdminHelper::U_SHOW,
            ),

            'USER_AGENT' => array(
                'data_type' => 'string',
                'required' => false,
                'title' => Loc::getMessage('IS_CORE_LOG_ENTITY_FIELD_USER_AGENT'),

                'nullable' => true,
                'default_value' => null,

                'default_in_list' => false,
                'in_filter' => true,
                'admin_edit' => AdminHelper::U_SHOW,
            ),

            'SCRIPT_FILENAME' => array(
                'data_type' => 'string',
                'required' => false,
                'title' => Loc::getMessage('IS_CORE_LOG_ENTITY_FIELD_SCRIPT_FILENAME'),

                'nullable' => true,
                'default_value' => null,

                'default_in_list' => true,
                'in_filter' => true,
                'admin_edit' => AdminHelper::U_SHOW,
            ),

            'REMOTE_ADDR' => array(
                'data_type' => 'string',
                'required' => false,
                'title' => Loc::getMessage('IS_CORE_LOG_ENTITY_FIELD_REMOTE_ADDR'),

                'nullable' => true,
                'default_value' => null,

                'default_in_list' => true,
                'in_filter' => true,
                'admin_edit' => AdminHelper::U_SHOW,
            ),

            //---------reference fields---------//
            
            'USER' => array(
                'data_type' => '\Bitrix\Main\User',
                'reference' => array(
                    '=this.USER_ID' => 'ref.ID'
                ),

                'default_in_list' => false,
                'in_filter' => false,
            ),
        );
    }
    
    public static function validateLEVEL(): array
    {
        return array(
            new Entity\Validator\Length(),
        );
    }

    /** Returns array of log levels */
    protected static function getLevels(): array
    {
        return Logger::getLevels();
    }

    /** Returns array of current sites */
    protected static function getSites(): array
    {
        $result = array();
        
        $rsSites = \CSite::GetList('sort', 'desc');
        while ($arSite = $rsSites->Fetch()) {
            $result[$arSite['LID']] = $arSite['NAME'];
        }
        
        return $result;
    }

    /** Returns true if $item is array or object */
    public static function isArrayOrObject($item): bool
    {
        if (is_array($item) || is_object($item)) {
            return true;
        }

        return false;
    }

    /**
     * Returns HTML string data using \Symfony\Component\VarDumper\Dumper\HtmlDumper.
     *
     * @param mixed $id ID of record in \Is\Core\Log\LogTable
     */
    public static function prettifyDataDetail($id): ?string
    {
        if (empty($id) || intval($id) != $id) {
            return null;
        }

        $arRecord = self::getById($id)->fetch();
        $data = unserialize($arRecord['DATA']);

        if (!is_array($data) || empty($data) || empty($data['MESSAGE'])) {
            return null;
        }

        $message = $data['MESSAGE'];
        $context = $data['CONTEXT'];

        if (empty($context)) {
            return self::prettifyValue($message);
        }

        $replace = array();
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = self::prettifyValue($val);
        }

        return strtr($message, $replace);
    }

    /**
     * Returns pretty value using \Symfony\Component\VarDumper\Dumper\HtmlDumper.
     */
    public static function prettifyValue($value): ?string
    {
        $valueIsArrayOrObject = self::isArrayOrObject($value);
        
        if ($valueIsArrayOrObject) {
            
            $cloner = new VarCloner();
            $cloner->setMaxItems(-1);
            $data = $cloner->cloneVar($value);

            $dumper = new HtmlDumper();
            $dumper->setTheme('light');

            return $dumper->dump($data, true, array(
                'maxDepth' => 1,
                'maxStringLength' => 255,
            ));
            
        }
        
        return $value;
    }

    public static function onBeforeAdd(Entity\Event $event): Entity\EventResult
    {
        $result = new Entity\EventResult;
        
        self::circulate();
        
        return $result;
    }
    
    /** Deletes all records alder than $daysKeep */
    protected static function circulate(): void
    {
        static $bIsCleared;
        if ($bIsCleared === true) {
            return;
        }
        
        static $daysKeep;
        if (!isset($daysKeep)) {
            $daysKeep = self::getDaysKeep();
        }

        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();

        $tblName = self::getTableName();
        $tblName = $sqlHelper->forSql($tblName);
        
        $time = (new DateTime())->add("-$daysKeep day");
        $time = $sqlHelper->convertToDbDateTime($time);

        $sqlQuery = "DELETE FROM `$tblName` WHERE `TIMESTAMP` < $time";
        // e.g. DELETE FROM `is_core_log` WHERE `TIMESTAMP` < '2024-06-21 15:53:02'

        try {
            $connection->queryExecute($sqlQuery);
            $bIsCleared = true;
        } catch (SqlQueryException $e) {
            $bIsCleared = false;
        }
    }
    
    protected static function getDaysKeep(): string
    {
        return Option::get(
            self::MODULE_ID,
            'is_core_log_days_keep',
            self::DAYS_KEEP_DEFAULT,
        );
    }

    /**
     * Adds a record to \Is\Core\Log\LogTable
     *
     * @param ?string $tag tag name
     * @param string $level log level
     * @param string $message serialized message
     */
    public static function addRow(?string $tag, string $level, string $message): AddResult
    {
        global $USER, $_SERVER;

        $userId = 0;
        if (isset($USER) && method_exists($USER, 'GetID')) {
            $userId = $USER->GetID();
        }

        $context = Application::getInstance()->getContext();
        $remoteAddr = self::getRemoteAddress($context);
        $siteLid = $context->getSite();

        return self::add(array(
            'TIMESTAMP' => new DateTime(),
            'LOG_LEVEL' => $level,
            'TAG' => $tag,
            'DATA' => $message,
            'URL' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'],
            'USER_ID' => $userId,
            'SITE_LID' => $siteLid,
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
            'USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
            'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'],
            'REMOTE_ADDR' => $remoteAddr ?: $_SERVER['REMOTE_ADDR'],
        ));
    }

    protected static function getRemoteAddress(Context $context)
    {
        $remoteAddr = null;

        foreach(array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP') as $key => $value) {
            if (!empty($_SERVER[$value])) {
                $ips = explode(", ", $_SERVER[$value]);

                foreach ($ips as $ip) {
                    // Skip RFC 1918 IPs 10.0.0.0/8, 172.16.0.0/12 and 192.168.0.0/16
                    if
                    (
                        !preg_match("/^(10|172\.16|192\.168)\./", $ip)
                        && preg_match("/^[^.]+\.[^.]+\.[^.]+\.[^.]+/", $ip)
                    ) {
                        $remoteAddr = $ip;
                        break;
                    }
                }
            }
        }

        if (empty($remoteAddr)) {
            $remoteAddr = $context->getRequest()->getHeader('x-real-ip');
        }

        return $remoteAddr;
    }
}