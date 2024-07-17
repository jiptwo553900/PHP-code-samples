<?php

namespace Is\Core\Log;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Data\AddResult;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

Loc::loadMessages(__FILE__);

/**
 * Class Logger
 *
 * @package Is\Core
 **/
class Logger extends AbstractLogger
{
    protected const DEFAULT_TAG = null;
    protected const LEVELS = array(
        'DEBUG' => LogLevel::DEBUG,
        'INFO' => LogLevel::INFO,
        'NOTICE' => LogLevel::NOTICE,
        'WARNING' => LogLevel::WARNING,
        'ERROR' => LogLevel::ERROR,
        'CRITICAL' => LogLevel::CRITICAL,
        'ALERT' => LogLevel::ALERT,
        'EMERGENCY' => LogLevel::EMERGENCY,
    );
    
    protected $tag;
    protected $table;
    
    /** 
     *  Представляет реализацию PSR-3. <br><br>
     *  Стандартный интерфейс PSR-3 расширен следующим образом: <br><br>
     *  Для каждого экземпляра Logger можно задать тег при создании, например, так: <br>
     *  $log = new Logger('some_tag'). <br><br>
     *
     *  Методы доступа, связанные с использованием тэгов: <br>
     *  $log->getTag() - возвращает текущий тэг <br>
     *  $log->setTag('some_new_tag') - установить новый тэг <br>
     *  $log->setDefaultTag() - установить тэг в дефолтное значение <br><br>
     * 
     *  Метод доступа (статический), возвращающий массив возможных уровней лога: <br>
     *  Logger::getLevels()
     * 
     * @param ?string $tag you can specify tag name for log. 
     */
    public function __construct(?string $tag = self::DEFAULT_TAG)
    {
        $this->tag = $tag;
        $this->table = new LogTable;
    }
    
    /** Returns log levels. */
    public static function getLevels(): array
    {
        return self::LEVELS;
    }
    
    /** Returns current tag. */
    public function getTag(): ?string
    {
        return $this->tag;
    }
    
    /**
     * Sets new tag name. 
     * Calling with null or '' as argument sets tag to default.
     */
    public function setTag(?string $tag): void
    {
        if ((string)$tag !== '') {
            $this->tag = $tag;
        } else {
            $this->tag = self::DEFAULT_TAG;
        }
    }
    
    /** Sets tag to default. */
    public function setDefaultTag(): void
    {
        $this->tag = self::DEFAULT_TAG;
    }

    /**
     *  Logs with an arbitrary level.
     *  <br><br>
     *  <b>AVAILABLE LEVELS:</b><br>
     *  'DEBUG' (in any case) or \Psr\Log\LogLevel::DEBUG<br>
     *  'INFO' (in any case) or \Psr\Log\LogLevel::INFO<br>
     *  'NOTICE' (in any case) or \Psr\Log\LogLevel::NOTICE<br>
     *  'WARNING' (in any case) or \Psr\Log\LogLevel::WARNING<br>
     *  'ERROR' (in any case) or \Psr\Log\LogLevel::ERROR<br>
     *  'CRITICAL' (in any case) or \Psr\Log\LogLevel::CRITICAL<br>
     *  'ALERT' (in any case) or \Psr\Log\LogLevel::ALERT<br>
     *  'EMERGENCY' (in any case) or \Psr\Log\LogLevel::EMERGENCY<br>
     *  <br>
     *  The message MUST be a string or object implementing __toString().
     *  <br><br>
     *  The message MAY contain placeholders in the form: {foo} where foo
     *  will be replaced by the context data in key "foo".
     *  Placeholder names MUST correspond to keys in the context array.
     *  <br><br>
     *  The context array can contain arbitrary data. 
     *  If an Exception object is passed in the context data, it MUST be in the 'exception' key.
     *  <br><br>
     *  See https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
     *  for the full interface specification.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * 
     * @return AddResult Contains ID of inserted row
     * @throws InvalidArgumentException
     */
    public function log($level, $message, array $context = array()): AddResult
    {
        $levelIsCorrect = $this->isCorrectLevel($level);
        
        if (!$levelIsCorrect) {
            throw new InvalidArgumentException(Loc::getMessage('IS_CORE_LOG_ERROR_NOT_CORRECT_LEVEL'));
        }

        $level = strtoupper($level);
        $message = self::serializeMessageAndContext($message, $context);

        $tag = $this->tag;
        $logTable = $this->table;
        
        return $logTable::addRow($tag, $level, $message);
    }

    protected function isCorrectLevel($level): bool
    {
        $logTable = $this->table;
        $levelIsArrayOrObject = $logTable::isArrayOrObject($level);
        
        if ($levelIsArrayOrObject) {
            return false;
        }
        
        if (in_array(strtolower($level), array_values(self::LEVELS))) {
            return true;
        }

        return false;
    }
    
    protected function serializeMessageAndContext($message, array $context = array()): string
    {
        $context = $this->clearContext($message, $context);

        $message = array(
            'MESSAGE' => $message,
            'CONTEXT' => $context,
        );
        
        return serialize($message);
    }

    protected function clearContext($message, array $context = array()): array
    {
        $logTable = $this->table;
        $messageIsArrayOrObject = $logTable::isArrayOrObject($message);
        
        if ($messageIsArrayOrObject || !is_array($context) || empty($context)) {
            return array();
        }

        foreach ($context as $key => $val) {
            if (strpos($message, '{' . $key . '}') === false) {
                unset($context[$key]);
            }
        }

        return $context;
    }
}