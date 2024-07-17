<?
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Is\Core\Log;

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

Loader::includeModule('is.core');
Loc::loadMessages(__FILE__);

if (empty($_REQUEST) || empty($_REQUEST['ACTION']) || empty($_REQUEST['ID'])) {
    die();
}

$id = $_REQUEST['ID'];
$msg = Log\LogTable::prettifyDataDetail($id);

switch ($_REQUEST['ACTION']) {
    case 'GET_DETAIL_VIEW':
        $json_decoded = json_decode($msg);
        if (json_last_error() === JSON_ERROR_NONE) {
            $msg = '<a href="javascript:showDetailData(\'GET_ENCODED_VIEW\', '.$id.')">json_decode</a><br>' . $msg;
            break;
        }
        
        $unserialized = unserialize($msg);
        if ($unserialized) {
            $msg = '<a href="javascript:showDetailData(\'GET_UNSERIZLIZED_VIEW\', '.$id.')">unserialize</a><br>' . $msg;
        }
        break;
        
    case 'GET_ENCODED_VIEW':
        $msg = Log\LogTable::prettifyValue(json_decode($msg));
        $msg = '<a href="javascript:showDetailData(\'GET_DETAIL_VIEW\', '.$id.')">show original</a><br>' . $msg;
        break;
        
    case 'GET_UNSERIZLIZED_VIEW':
        $msg = Log\LogTable::prettifyValue(unserialize($msg));
        $msg = '<a href="javascript:showDetailData(\'GET_DETAIL_VIEW\', '.$id.')">show original</a><br>' . $msg;
        break;
        
    default:
        break;
}

echo $msg ?: 'NULL';
die();