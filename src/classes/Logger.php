<?php

namespace macwinnie\PHPHelpers;

use DateTime, DateTimeZone;

/**
 * Logger class that handles writing out logfiles for PHP programs.
 *
 * Possible loglevels are:
 * * DEBUG
 * * INFO
 * * NOTICE
 * * WARNING
 * * ERROR
 * * CRITICAL
 * * ALERT
 * * EMERGENCY
 * * RETURN (special one that returns the logmessage instead of writing it out to a file)
 *
 * A regular logging entry (in the combined logfile) has the format:
 * `Y-m-d H:i:s.u [loglevel] [class_calling] [length]: message`
 *
 * (See PHP documentation for date format; `LOG_MICROSEC=false` will remove `.u` from date-time.)
 *
 * Within the loglevel specific files, the `[loglevel]` entry is removed.
 *
 * `[length]` represents the length of the logmessage `message` (in Bytes); date, time and the square braced parts are not reflected.
 */
class Logger {

    static private $filenames = [
        'full' => 'full.log',
    ];

    static protected $availableLoglevels = [
        'DEBUG',
        'INFO',
        'NOTICE',
        'WARNING',
        'ERROR',
        'CRITICAL',
        'ALERT',
        'EMERGENCY',
        'RETURN',
    ];

    /**
     * this class is relies on environmental variables:
     *
     * * `LOG_PATH` – where should the log files be placed? Defaults to `/tmp/logs/`
     * * `LOG_LEVEL` – one of the loglevels, defaults to `ERROR`
     * * `LOG_MICROSEC` – should microseconds be reflected? Default is `true`
     * * `LOG_TIMEZONE` – the timezone the logs should reflect, defaults to environmental variable `TIMEZONE` if it exists or `Europe/Berlin`
     */
    static protected $localDefaults = [
        'LOG_PATH'     => '/tmp/logs/',
        'LOG_LEVEL'    => 'error',
        'LOG_MICROSEC' => true,
        'LOG_TIMEZONE' => 'Europe/Berlin',
    ];

    private $envs = [];
    private $time = NULL;
    private $lvl  = NULL;
    private $msg  = NULL;
    private $clss = NULL;
    private $spec = true;

    /**
     * initialize the logger
     *
     * @param string $loglevel loglevel out of static::$availableLoglevels
     * @param string $message  the message to be logged
     */
    protected function __construct ( $loglevel, $message, $class ) {

        // fetch values of environmental functions
        foreach ( static::$localDefaults as $key => $default ) {
            if ( $key == 'LOG_TIMEZONE' ) {
                $this->envs[ $key ] = env( $key, env( 'TIMEZONE', $default ) );
            }
            else {
                $this->envs[ $key ] = env( $key, $default );
            }
        }

        // set all information
        if ( $this->envs[ 'LOG_MICROSEC' ] ) {
            $dateformat = 'Y-m-d H:i:s.u';
        }
        else {
            $dateformat = 'Y-m-d H:i:s';
        }
        $this->time = DateTime::createFromFormat( 'U.u', microtime( true ))
                                ->setTimezone( new DateTimeZone( $this->envs[ 'LOG_TIMEZONE' ] ) )
                                ->format( $dateformat );
        $this->lvl  = $loglevel;
        $this->msg  = $message;
        $this->clss = $class;
    }

    /**
     * create string representation of current log entry
     *
     * @return string log entry
     */
    public function __toString() {

        $format = '%1$s';
        if ( ! $this->spec or $this->lvl == 'RETURN' ) {
            $format .= ' [%2$s]';
        }
        $format .= ' [%5$s] [%3$s]: %4$s';

        $msgsize = strlen( $this->msg );

        return sprintf( $format, $this->time, $this->lvl, $msgsize, $this->msg, $this->clss );
    }

    /**
     * function that handles loglevel method calls to this static class and
     * returns an `\Exception` on all unkown static method calls on this class
     *
     * @param  string $name name of the unknown callable
     * @param  mixed  $args arguments passed for the callable
     *
     * @return mixed        result of the callable, if there is a matching loglevel
     *
     * @throws \Exception   Fatal undefined method when callable is not equal to defined loglevel
     */
    public static function __callStatic ( $name, $args ) {
        $name = strtoupper( $name );
        if ( in_array( $name, static::$availableLoglevels ) ) {
            $log = new static ( $name, $args[0], static::get_calling_class() );
            if ( $name == 'RETURN' ) {
                return strval( $log );
            }
        }
        else {
            throw new \Exception( sprintf( "Fatal: Call to undefined method %s:%s()", get_class( new static() ), $name ) );
        }
    }

    /**
     * get the name of the calling class for logging
     *
     * @return string namespace and class name of calling class
     */
    protected static function get_calling_class() {

        // fetch debug trace
        $trace = debug_backtrace();
        // fetch current class name
        $class = array_shift( $trace )[ 'class' ];

        foreach ( $trace as $entry ) {
            // first differing class name is the one searched
            if ( $entry[ 'class' ] != $class ) {
                return $entry[ 'class' ];
            }
        }
    }

    /**
     * get filename for logfiles
     *
     * @param  string $file logfile
     *
     * @return string       filename for logfile
     *
     * @throws \Exception   if the requested logfile type does not exist
     */
    public static function getFilename ( $file ) {
        if ( ! isset( static::$filenames[ $file ] ) ) {
            throw new \Exception( "The requested file does not exist." );
        }
        return static::$filenames[ $file ];
    }

    /**
     * Ensure that the log path does exist
     *
     * @return boolean Returns `true` on success or `false` on failure.
     */
    public static function ensureLogPathExists () {
        $success = true;
        if ( ! is_dir( env( 'LOG_PATH' ) ) ) {
            $success = mkdir( env( 'LOG_PATH' ), 0700, true );
        }
        return $success;
    }
}
