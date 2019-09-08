<?php
/**
 * Created by PhpStorm.
 * User: Henrik
 * Date: 2/6/2018
 * Time: 11:28 AM
 */

namespace henrik\log\adapters;

use henrik\log\exceptions\FilePermissionException;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;

/**
 * Class FileLoggerAdapter
 * @package henrik\log\adapters
 */
class FileLoggerAdapter implements LoggerInterface
{
    use LoggerTrait;
    /**
     * @var array
     */
    protected $options = array(
        'extension' => 'log',
        'dateFormat' => 'Y-m-d G:i:s.u',
        'filename' => false,
        'flushFrequency' => false,
        'prefix' => 'log_',
        'logFormat' => false,
        'appendContext' => true,
    );

    /**
     * @var
     */
    private $logFilePath;

    /**
     * @var string
     */
    protected $level = LogLevel::INFO;

    /**
     * @var int
     */
    private $logLineCount = 0;

    /**
     * @var array
     */
    protected $logLevels = array(
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 2,
        LogLevel::ERROR => 3,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 5,
        LogLevel::INFO => 6,
        LogLevel::DEBUG => 7
    );

    /**
     * @var
     */
    private $fileHandle;

    /**
     * @var string
     */
    private $directory = "";

    /**
     * @var string
     */
    private $lastLine = '';

    /**
     * @var int
     */
    private $defaultPermissions = 0777;

    /**
     * @param $stdOutPath
     */
    public function setLogToStdOut($stdOutPath)
    {
        $this->logFilePath = $stdOutPath;
    }

    /**
     *
     */
    public function setLogFilePath()
    {
        if ($this->options['filename']) {
            if (strpos($this->options['filename'], '.log') !== false
                || strpos($this->options['filename'], '.txt') !== false) {

                $this->logFilePath = $this->directory . DIRECTORY_SEPARATOR . $this->options['filename'];
            } else {
                $this->logFilePath = $this->directory
                    . DIRECTORY_SEPARATOR
                    . $this->options['filename']
                    . '.'
                    . $this->options['extension'];
            }
        } else {
            $this->logFilePath = $this->directory
                . DIRECTORY_SEPARATOR
                . $this->options['prefix']
                . date('Y-m-d')
                . '.'
                . $this->options['extension'];
        }
    }

    /**
     * @param $writeMode
     */
    public function setFileHandle($writeMode)
    {
        $this->fileHandle = fopen($this->logFilePath, $writeMode);
    }

    /**
     *
     */
    public function __destruct()
    {
        if ($this->fileHandle) {
            fclose($this->fileHandle);
        }
    }

    /**
     * @param $dateFormat
     */
    public function setDateFormat($dateFormat)
    {
        $this->options['dateFormat'] = $dateFormat;
    }

    /**
     * @param $level
     */
    public function setLevel($level)
    {
        $this->level = $level;
    }

    /**
     * @param $level
     * @param $message
     * @param array $context
     * @throws \Exception
     */
    public function log($level, $message, array $context = array())
    {
        if ($this->logLevels[$this->level] < $this->logLevels[$level]) {
            return;
        }
        $message = $this->formatMessage($level, $message, $context);
        $this->write($message);
    }

    /**
     * @param $message
     */
    public function write($message)
    {
        if (null !== $this->fileHandle) {
            if (fwrite($this->fileHandle, $message) === false) {
                throw new FilePermissionException(
                    'The file could not be written to. Check that appropriate permissions have been set.
                    ');
            } else {
                $this->lastLine = trim($message);
                $this->logLineCount++;
                if ($this->options['flushFrequency'] && $this->logLineCount % $this->options['flushFrequency'] === 0) {
                    fflush($this->fileHandle);
                }
            }
        }
    }

    /**
     * Get the file path that the log is currently writing to
     *
     * @return string
     */
    public function getLogFilePath()
    {
        return $this->logFilePath;
    }


    /**
     * Get the last line logged to the log file
     *
     * @return string
     */
    public function getLastLogLine()
    {
        return $this->lastLine;
    }

    /**
     * @param $level
     * @param $message
     * @param $context
     * @return string
     * @throws \Exception
     */
    protected function formatMessage($level, $message, $context)
    {
        if ($this->options['logFormat']) {
            $parts = array(
                'date' => $this->getTimestamp(),
                'level' => strtoupper($level),
                'level-padding' => str_repeat(' ', 9 - strlen($level)),
                'priority' => $this->logLevels[$level],
                'message' => $message,
                'context' => json_encode($context),
            );
            $message = $this->options['logFormat'];
            foreach ($parts as $part => $value) {
                $message = str_replace('{' . $part . '}', $value, $message);
            }
        } else {
            $message = "[{$this->getTimestamp()}] [{$level}] {$message}";
        }
        if ($this->options['appendContext'] && !empty($context)) {
            $message .= PHP_EOL . $this->indent($this->contextToString($context));
        }
        return $message . PHP_EOL;
    }

    /**
     * Gets the correctly formatted Date/Time for the log entry.
     *
     * PHP DateTime is dump, and you have to resort to trickery to get microseconds
     * to work correctly, so here it is.
     *
     * @return string
     * @throws \Exception
     */
    private function getTimestamp()
    {
        $originalTime = microtime(true);
        $micro = sprintf("%06d", ($originalTime - floor($originalTime)) * 1000000);
        $date = new \DateTime(date('Y-m-d H:i:s.' . $micro, $originalTime));
        return $date->format($this->options['dateFormat']);
    }

    /**
     * Takes the given context and coverts it to a string.
     *
     * @param array $context The Context
     * @return string
     */
    protected function contextToString($context)
    {
        $export = '';
        foreach ($context as $key => $value) {
            $export .= "{$key}: ";
            $export .= preg_replace(array(
                '/=>\s+([a-zA-Z])/im',
                '/array\(\s+\)/im',
                '/^  |\G  /m'
            ), array(
                '=> $1',
                'array()',
                '    '
            ), str_replace('array (', 'array(', var_export($value, true)));
            $export .= PHP_EOL;
        }
        return str_replace(array('\\\\', '\\\''), array('\\', '\''), rtrim($export));
    }

    /**
     * Indents the given string with the given indent.
     *
     * @param string $string The string to indent
     * @param string $indent What to use as the indent.
     * @return string
     */
    protected function indent($string, $indent = '    ')
    {
        return $indent . str_replace("\n", "\n" . $indent, $string);
    }

    /**
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * @param string $directory
     */
    public function setDirectory($directory)
    {
        $this->directory = $directory;
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
        if (!file_exists($this->directory)) {
            mkdir($this->directory, $this->defaultPermissions, true);
        }
        if (strpos($this->directory, 'php://') === 0) {
            $this->setLogToStdOut($this->directory);
            $this->setFileHandle('w+');
        } else {
            $this->setLogFilePath();
            if (file_exists($this->logFilePath) && !is_writable($this->logFilePath)) {
                throw new FilePermissionException(
                    'The file could not be written to. Check that appropriate permissions have been set.'
                );
            }
            $this->setFileHandle('a');
        }
        if (!$this->fileHandle) {
            throw new FilePermissionException('The file could not be opened. Check permissions.');
        }
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param array $options
     */
    public function setOptions($options)
    {
        $this->options = array_merge($this->options, $options);
    }
}