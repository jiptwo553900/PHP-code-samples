<?php

namespace Is\ServerStatus\Indicator\DomainExpiration;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime as BxDateTime;
use DateTimeInterface;
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
use Is\ServerStatus\Tools\Helper;

class DomainExpirationIndicator implements IndicatorContract, IndicatorInfoContract, IndicatorSettingsContract
{
    use GetIndicatorNameTrait;
    use IsActiveIndicatorTrait;

    protected array $_arSites = array();

    public function title(): string
    {
        return Loc::getMessage(IS_SRVSTAT_MODULE_ID . '.INDICATOR.DOMAIN_EXPIRATION.TITLE');
    }

    public function description(): string
    {
        return Loc::getMessage(IS_SRVSTAT_MODULE_ID . '.INDICATOR.DOMAIN_EXPIRATION.DESCRIPTION');
    }

    protected function setSites(): void {
        $this->_arSites = Helper::getActiveSites();
    }

    /** Returns domain expiration date (in unix seconds) or null if result was never reached */
    protected function getDomainExpiration($site): ?int
    {
        $zone = explode('.', $site);
        $zone = end($zone);
        $server = null;
        
        switch ($zone) {
            case 'ru':
            case 'su':
            case 'рф': $server = 'whois.tcinet.ru'; break;
            case 'com':
            case 'net': $server = 'whois.verisign-grs.com'; break;
            case 'org': $server = 'whois.pir.org'; break;
        }
        
        if (empty($server)) {
            return null;
        }

        $date = null;
        
        $socket = fsockopen($server, 43);
        if ($socket) {
            fputs($socket, $site . PHP_EOL);
            
            while (!feof($socket)) {
                $res = fgets($socket, 128);

                if (mb_stripos($res, 'paid-till:') !== false) {
                    $date = explode('paid-till:', $res);
                    $date = strtotime(trim($date[1]));
                    break;
                }
                if (mb_stripos($res, 'Registry Expiry Date:') !== false) {
                    $date = explode('Registry Expiry Date:', $res);
                    $date = strtotime(trim($date[1]));
                    break;
                }
            }
            
            fclose($socket);
        }
        
        return $date;
    }

    public function settings(): Config\Settings
    {
        $indicatorName = $this->name();
        $arSelectOptions = array();

        $this->setSites();
        foreach ($this->_arSites as $arSite) {
            $arSelectOptions[] = $arSite['SERVER_NAME'];
        }

        $defaultSites = array_unique($arSelectOptions);
        $sitesOption = new Config\SelectMultipleOption(
            "indicator.$indicatorName.sites",
            Loc::getMessage(IS_SRVSTAT_MODULE_ID . '.INDICATOR.DOMAIN_EXPIRATION.CFG_OPT_TITLE.DOMAIN_LIST'),
            $defaultSites,
            true
        );

        $arSelectOptions['IS_GROUPED'] = 0; // no groups need in <select>
        $sitesOption->setSelectOptions($arSelectOptions);

        return new Config\Settings(
            new Config\Predefined\IsActiveIndicatorOption($indicatorName),
            $sitesOption
        );
    }

    /**
     * @return MeasurementResult | ResultContract<MeasurementContract, MeasurementException>
     */
    public function indicate(?array $externalOptions = null): MeasurementResult
    {
        $options = $this->settings()->loadConfig()->valuesMap();
        $indicatorName = $this->name();
        $output = [];
        $retCode = 501;

        $arSelectedSites = $options["indicator.$indicatorName.sites"];
        if (empty($arSelectedSites)) {
            $errorMessage = 'E'.$retCode.': ' . Loc::getMessage(IS_SRVSTAT_MODULE_ID . '.INDICATOR.DOMAIN_EXPIRATION.EMPTY_LIST');
            return MeasurementException::errorResult($indicatorName, $retCode, $errorMessage);
        }

        $arActiveSites = $this->_arSites;
        if (empty($arActiveSites)) {
            $errorMessage = 'E'.$retCode.': ' . Loc::getMessage(IS_SRVSTAT_MODULE_ID . '.INDICATOR.DOMAIN_EXPIRATION.NO_ACTIVE_SITES');
            return MeasurementException::errorResult($indicatorName, $retCode, $errorMessage);
        }

        foreach ($arSelectedSites as $site) {
            $siteParts = explode('.', $site);
            
            while (count($siteParts) >= 2) {
                // Попытка получить срок годности для текущего домена
                $validTo = $this->getDomainExpiration($site);

                // Если значение получено - прервать цикл
                if (!empty($validTo)) {
                    break;
                }
                
                // Иначе отбросить поддомен и повторить итерацию
                array_shift($siteParts);
                $site = implode('.', $siteParts);
            }
            
            if (empty($validTo)) {
                $output[$site] = array(
                    'error' => true,
                    'errCode' => $retCode,
                    'output' => 'E'.$retCode.': ' . Loc::getMessage(IS_SRVSTAT_MODULE_ID . '.INDICATOR.DOMAIN_EXPIRATION.ERROR'),
                );
                continue;
            }
            
            // Если в результате еще не присутствует текущий домен, добавить его в результат
            if (empty($output[$site])) {
                $output[$site]['valid_to'] = BxDateTime::createFromTimestamp($validTo)->format(DateTimeInterface::ATOM);
                $output[$site]['seconds_left'] = $validTo - strtotime('now');
            }
        }

        $payload = Payload::from($output);
        return Measurement::successResult($indicatorName, new BxDateTime, $payload);
    }
}