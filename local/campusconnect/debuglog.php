<?php

defined('MOODLE_INTERNAL') || die();

function debuglog($msg) {
    // Remove the '//' from the start of the next line to turn off all debugging

    // return;

    global $CFG;

    $fp = fopen($CFG->dataroot.'/debug-campusconnect.log', 'a');

    if (!$fp) {
        return;
    }

    fwrite($fp, date('j M Y H:i:s').' - '.$msg."\n");
    fclose($fp);
}

function debuglog_print_r($var) {
    ob_start();
    print_r($var);
    $out = ob_get_contents();
    ob_end_clean();

    debuglog($out);
}

function debuglog_backtrace() {

    $output = "Backtrace: \n";
    $trace = debug_backtrace();
    foreach ($trace as $depth => $details) {
        $output .= $depth.': ';
        $output .= $details['file'].' - ';
        $output .= 'line '.$details['line'].': ';
        if ($details['function']) {
            $output .= $details['function'].'()';
        }
        $output .= "\n";
    }
    debuglog($output);
}


class debug_timing {
    static $lasttime = 0;
    static $times = array();

    static function start() {
        self::$lasttime = microtime(true);
        self::$times = array();
    }

    static function add($description, $logimmediately = false) {
        $data = new stdClass;
        $data->timeelapsed = microtime(true) - self::$lasttime;
        $data->description = $description;
        self::$times[] = $data;
        self::$lasttime = microtime(true);

        if ($logimmediately) {
            debuglog("$description: $data->timeelapsed");
        }
    }

    static function output() {
        foreach (self::$times as $time) {
            echo "$time->description: $time->timeelapsed<br/>\n";
        }
    }

    static function output_log($pagename) {
        $timing = '';
        $total = 0;
        foreach (self::$times as $time) {
            $total += $time->timeelapsed;
        }
        foreach (self::$times as $time) {
            $percent = 100.0 * $time->timeelapsed / $total;
            $percent = sprintf('%d%%', $percent);
            $timing .= "$time->description: $time->timeelapsed ($percent)\n";
        }
        $timing .= "Total: $total\n";
        debuglog($pagename."\n".$timing);
    }
}