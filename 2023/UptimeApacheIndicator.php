<?php
namespace Is\ServerStatus\Indicator\UptimeApache;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime as BxDateTime;
use Is\ServerStatus\Config;
use Is\ServerStatus\Payload;
use Is\ServerStatus\Measurement;
use Is\ServerStatus\MeasurementContract;
use Is\ServerStatus\MeasurementResult;
use Is\ServerStatus\ResultContract;
use Is\ServerStatus\MeasurementException;
use Is\ServerStatus\GetIndicatorNameTrait;
use Is\ServerStatus\IndicatorInfoContract;
use Is\ServerStatus\IndicatorContract;
use Is\ServerStatus\IndicatorSettingsContract;
use Is\ServerStatus\IsActiveIndicatorTrait;

class UptimeApacheIndicator implements IndicatorContract, IndicatorInfoContract, IndicatorSettingsContract
{
    use GetIndicatorNameTrait;
    use IsActiveIndicatorTrait;

    public function title(): string {
        return Loc::getMessage(IS_SRVSTAT_MODULE_ID.'.INDICATOR.UPTIME_APACHE.TITLE');
    }

    public function description(): string {
        return Loc::getMessage(IS_SRVSTAT_MODULE_ID.'.INDICATOR.UPTIME_APACHE.DESCRIPTION');
    }

    public function settings(): Config\Settings {
        $indicatorName = $this->name();
        return new Config\Settings(
            new Config\Predefined\IsActiveIndicatorOption($indicatorName),
        );
    }

    public static function grepBy(): string {
        return 'httpd\|apache';
    }

    /**
     * @param array|null $externalOptions
     * @return MeasurementResult | ResultContract<MeasurementContract, MeasurementException>
     */
    public function indicate(?array $externalOptions = null): MeasurementResult {
        $options = $this->settings()->loadConfig()->valuesMap();
        $indicatorName = $this->name();
        $output = [];
        $retCode = 0;
        
        /** @noinspection SpellCheckingInspection */
        $csep = '|%%|';
        $sep = '|%|';
        $grepBy = static::grepBy() . '\|ELAPSED';
        $cmd = <<<CMD
            ps ax \
            k -etimes \
            o etimes \
            o "$csep" o lstart \
            o "$csep" o user \
            o "$csep" o comm \
            o "$csep" o cmd \
            | grep "$grepBy" \
            | grep -v "grep\|httpd-scale"
        CMD;
        exec($cmd, $output, $retCode);

        if ($retCode !== 0) {
            $errorMessage = 'E'.$retCode.': ' . join(PHP_EOL, $output);
            return MeasurementException::errorResult($indicatorName, $retCode, $errorMessage);
        }

        $fields = array_map('trim', explode($sep, array_shift($output)));

        $resultSuccess = false;
        foreach ($output as $lineKey => $line) {
            $result = [];
            
            $arLine = explode($sep, $line);
            foreach ($arLine as $valueKey => $value) {
                $result[$fields[$valueKey]] = trim($value);
            }
            
            // Check if Command is correct (to avoid trash commands like 'tail')
            $arSearch = explode('\|', static::grepBy());
            if (str_replace($arSearch, '', $result['COMMAND']) !== $result['COMMAND']) {
                $resultSuccess = true;
                break;
            }
        }

        if (isset($result) && $resultSuccess) {
            $payload = Payload::from($result);
            return Measurement::successResult($this->name(), new BxDateTime, $payload);
        }

        $retCode = 501;
        $errorMessage = 'E'.$retCode.': Process not found';
        return MeasurementException::errorResult($indicatorName, $retCode, $errorMessage);
    }
}
