<?php
namespace Is\ServerStatus\Indicator\TrafficMonitor;
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

class TrafficMonitorIndicator implements IndicatorContract, IndicatorInfoContract, IndicatorSettingsContract
{
    use GetIndicatorNameTrait;
    use IsActiveIndicatorTrait;
    
    private const DEFAULT_INTERFACE = 'eth0';

    public function title(): string {
        return Loc::getMessage(IS_SRVSTAT_MODULE_ID.'.INDICATOR.TRAFFIC_MONITOR.TITLE');
    }

    public function description(): string {
        return Loc::getMessage(IS_SRVSTAT_MODULE_ID.'.INDICATOR.TRAFFIC_MONITOR.DESCRIPTION');
    }

    public function settings(): Config\Settings {
        $indicatorName = $this->name();
        $interfaces = implode(', ', $this->_getInterfacesList());
        $interfaces = empty($interfaces) 
            ? Loc::getMessage(IS_SRVSTAT_MODULE_ID.'.INDICATOR.TRAFFIC_MONITOR.CFG_OPT.NO_INTERFACES') 
            : $interfaces;
        return new Config\Settings(
            new Config\Predefined\IsActiveIndicatorOption($indicatorName),
            new Config\StringOption(
                "indicator.$indicatorName.interface",
                Loc::getMessage(IS_SRVSTAT_MODULE_ID.'.INDICATOR.TRAFFIC_MONITOR.CFG_OPT_TITLE.NO_INTERFACES') . " <b>$interfaces</b>",
                self::DEFAULT_INTERFACE
            )
        );
    }
    
    private function _getInterfacesList(): ?array {
        $result = null;
        $bHandleFile = true;
        $stats = null;

        if (!is_readable('/proc/net/dev')) {
            $bHandleFile = false;
        } else {
            $stats = file('/proc/net/dev');
            if (!$stats) {
                $bHandleFile = false;
            }
        }
        
        if ($bHandleFile) {
            foreach ($stats as $line) {
                $line = preg_split('/\s+/', trim($line));
                if (strpos($line[0], ':') !== false) {
                    $interface = trim($line[0], ':');
                    $result[] = $interface;
                }
            }
        } else {
            $cmd = 'ip addr';
            exec($cmd, $output, $retCode);

            if ($retCode !== 0) {
                return null;
            }

            foreach ($output as $line) {
                $line = preg_split('/\s+/', trim($line));
                if (strpos($line[0], ':') !== false) {
                    $interface = preg_split('/@/', trim($line[1], ':'));
                    $result[] = $interface[0];
                }
            }
        }

        return $result;
    }

    private function _getLinuxTrafficDataFromFile(string $interface = null): ?array {
        $interface = is_null($interface) ? self::DEFAULT_INTERFACE : $interface;
        $result = null;

        if (!is_readable('/proc/net/dev')) {
            return null;
        }

        $stats = file('/proc/net/dev');
        if (!$stats) {
            return null;
        }

        foreach ($stats as $line) {
            $line = preg_split('/\s+/', trim($line));
            if (strpos($line[0], ':') !== false) {
                $iface = trim($line[0], ':');
                if ($iface !== $interface) {
                    continue;
                }
                $result = array(
                    'recieve' => $line[1],
                    'transmit' => $line[9],
                );
            }
        }

        return $result;
    }

    private function _getLinuxTrafficDataFromCli(string $interface = null): ?array {
        $interface = is_null($interface) ? self::DEFAULT_INTERFACE : $interface;
        $result = null;

        $cmd = "ip -s link show $interface";
        exec($cmd, $output, $retCode);

        if ($retCode !== 0) {
            return null;
        }

        foreach ($output as $k => $line) {
            $output[$k] = preg_split('/\s+/', trim($line));
        }

        $result = array(
            'recieve' => $output[3][0],
            'transmit' => $output[5][0],
        );

        return $result;
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
        $interface = $options["indicator.$indicatorName.interface"];
        $interfaces = $this->_getInterfacesList();
        
        if (is_null($interfaces) || !in_array($interface, $interfaces)) {
            $retCode = 501;
            $errorMessage = 'E'.$retCode.': Interface not avaliable or no interfaces found';
            return MeasurementException::errorResult($indicatorName, $retCode, $errorMessage);
        }

        if (is_readable('/proc/net/dev')) {
            $trafficData1 = $this->_getLinuxTrafficDataFromFile($interface);
            sleep(1);
            $trafficData2 = $this->_getLinuxTrafficDataFromFile($interface);
        } else {
            $trafficData1 = $this->_getLinuxTrafficDataFromCli($interface);
            sleep(1);
            $trafficData2 = $this->_getLinuxTrafficDataFromCli($interface);
        }

        if ((is_null($trafficData1)) || (is_null($trafficData2))) {
            $retCode = 501;
            $errorMessage = 'E'.$retCode.': Invalid output';
            return MeasurementException::errorResult($indicatorName, $retCode, $errorMessage);
        }

        $output['recieve'] = $trafficData2['recieve'] - $trafficData1['recieve'];
        $output['transmit'] = $trafficData2['transmit'] - $trafficData1['transmit'];
        
        foreach ($output as $k => $v) {
            $output[$k] = round($v / 1024, 2);
        }
        
        $payload = Payload::from($output);
        return Measurement::successResult($this->name(), new BxDateTime, $payload);
    }
}
