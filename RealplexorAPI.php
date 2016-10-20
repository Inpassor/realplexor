<?php
/**
 * Realplexor PHP API
 *
 * @author DmitryKoterov <dmitry.koterov@gmail.com>
 * @link https://github.com/DmitryKoterov/dklab_realplexor/blob/master/api/php/Dklab/Realplexor.php
 *
 * @author Inpassor <inpassor@yandex.com>
 * @link https://github.com/Inpassor/yii2-realplexor
 *
 * @version 0.1.2 (2016.10.20)
 */

namespace inpassor\realplexor;

trait RealplexorAPI
{

    /**
     * @var string The server host.
     */
    public $host = '127.0.0.1';

    /**
     * @var int The connection port.
     */
    public $port = 10010;

    /**
     * @var string Namespace to use.
     */
    public $namespace = '';

    /**
     * @var string Login for connection (if the server need it).
     */
    public $login = '';

    /**
     * @var string Password for connection (if the server need it).
     */
    public $password = '';

    /**
     * @var int The connection timeout, in seconds.
     */
    public $timeout = 5;

    /**
     * @var string Charset used in Content-Type for JSON and other responses.
     */
    public $charset = 'UTF-8';

    /**
     * @var string Last error message, if error occured.
     */
    public $lastError = '';

    /**
     * Send data to Realplexor.
     * @param mixed $idsAndCursors Target IDs in form of: [id1 => cursor1, id2 => cursor2, ...]
     * or [id1, id2, id3, ...]. If sending to a single ID, you may pass it as a plain string, not array.
     * @param mixed $data Data to be sent (any format, e.g. nested arrays are OK).
     * @param array $showOnlyForIds Send this message to only those who also listen any of these IDs.
     * This parameter may be used to limit the visibility to a closed number of cliens: give each client
     * an unique ID and enumerate client IDs in $showOnlyForIds to not to send messages to others.
     * @return bool True on success, false on fail. Check $this->lastError for error message if false returned.
     */
    public function send($idsAndCursors, $data, $showOnlyForIds = null)
    {
        $data = json_encode($data);
        $pairs = [];
        foreach ((array)$idsAndCursors as $id => $cursor) {
            if (is_int($id)) {
                $id = $cursor; // This is not cursor, but ID!
                $cursor = null;
            }
            if (!preg_match('/^\w+$/', $id)) {
                $this->lastError = 'Identifier must be alphanumeric, "' . $id . '" given.';
                return false;
            }
            $id = $this->namespace . $id;
            if ($cursor !== null) {
                if (!is_numeric($cursor)) {
                    $this->lastError = 'Cursor must be numeric, "' . $cursor . '" given.';
                    return false;
                }
                $pairs[] = $cursor . ':' . $id;
            } else {
                $pairs[] = $id;
            }
        }
        if (is_array($showOnlyForIds)) {
            foreach ($showOnlyForIds as $id) {
                $pairs[] = '*' . $this->namespace . $id;
            }
        }
        return $this->_send(implode(',', $pairs), $data) === null ? false : true;
    }

    /**
     * Return list of online IDs (keys) and number of online browsers for each ID
     * ("online" means "connected just now", it is very approximate).
     * @param string|array $idPrefixes If set, only online IDs with these prefixes are returned.
     * @return array List of matched online IDs (keys) and online counters (values). Check $this->lastError for
     * error message if empty array returned.
     */
    public function cmdOnlineWithCounters($idPrefixes = [])
    {
        if (!($responce = trim($this->_sendCmd('online' . $this->_addNamespace($idPrefixes))))) {
            return [];
        }
        $lines = explode("\n", $responce);
        $result = [];
        foreach ($lines as $line) {
            @list($id, $counter) = explode(' ', $line);
            if (!$id) {
                continue;
            }
            $result[$this->_cutNamespace($id)] = $counter;
        }
        return $result;
    }

    /**
     * Return list of online IDs.
     * @param string|array $idPrefixes If set, only online IDs with these prefixes are returned.
     * @return array List of matched online IDs. Check $this->lastError for error message if empty array returned.
     */
    public function cmdOnline($idPrefixes = [])
    {
        return array_keys($this->cmdOnlineWithCounters($idPrefixes));
    }

    /**
     * Return all Realplexor events (e.g. ID offline/offline changes) happened after $fromPos cursor.
     * @param string $fromPos Start watching from this cursor.
     * @param string|array $idPrefixes Watch only changes of IDs with these prefixes.
     * @return array List of ["event" => ..., "cursor" => ..., "id" => ...]. Check $this->lastError for error message
     * if empty array returned.
     */
    public function cmdWatch($fromPos, $idPrefixes = [])
    {
        if (!$fromPos) {
            $fromPos = 0;
        }
        if (!preg_match('/^[\d.]+$/', $fromPos)) {
            $this->lastError = 'Position value must be numeric, "' . $fromPos . '" given.';
            return [];
        }
        if (!($responce = trim($this->_sendCmd('watch ' . $fromPos . $this->_addNamespace($idPrefixes))))) {
            return [];
        }
        $lines = explode("\n", $responce);
        $events = [];
        foreach ($lines as $line) {
            if (!preg_match('/^ (\w+) \s+ ([^:]+):(\S+) \s* $/sx', $line, $m)) {
                trigger_error('Cannot parse the event: "' . $line . '"');
                continue;
            }
            $events[] = [
                'event' => $m[1],
                'pos' => $m[2],
                'id' => $this->_cutNamespace($m[3]),
            ];
        }
        $this->lastError = '';
        return $events;
    }

    /**
     * Add the namespace to ID prefixes.
     * @param string|array $idPrefixes ID prefixes without namespace.
     * @return string ID prefixes with namespace.
     */
    protected function _addNamespace($idPrefixes)
    {
        $idPrefixes = (array)$idPrefixes;
        if ($this->namespace) {
            $idPrefixes = $idPrefixes ? $idPrefixes : [''];
            foreach ($idPrefixes as $i => $idp) {
                $idPrefixes[$i] = $this->namespace . $idp;
            }
        }
        return $idPrefixes ? ' ' . implode(' ', $idPrefixes) : '';
    }

    /**
     * Cut off the namespace from ID.
     * @param string $id ID with namespace.
     * @return string ID without namespace.
     */
    protected function _cutNamespace($id)
    {
        return ($this->namespace && mb_strpos($id, $this->namespace, $this->charset) === 0) ? substr($id, mb_strlen($this->namespace, $this->charset)) : $id;
    }

    /**
     * Send IN command.
     * @param string $cmd Command to send.
     * @return string|null Server IN response. Check $this->lastError for error message if null returned.
     */
    protected function _sendCmd($cmd)
    {
        return $this->_send(null, $cmd . "\n");
    }

    /**
     * Send specified data to IN channel. Return response data.
     * @param string $identifier If set, pass this identifier string.
     * @param string $body Data to be sent.
     * @return string|null Response from IN line. Check $this->lastError for error message if null returned.
     */
    protected function _send($identifier, $body)
    {
        // Build HTTP request.
        $data = "POST / HTTP/1.1\r\n"
            . 'Host: ' . $this->host . "\r\n"
            . 'Content-Length: ' . mb_strlen($body, $this->charset) . "\r\n"
            . 'X-Realplexor: identifier='
            . (($this->login && $this->password) ? $this->login . ':' . $this->password . '@' : '')
            . ($identifier ? $identifier : '')
            . "\r\n"
            . "\r\n"
            . $body;
        // Proceed with sending.
        $initialTrackErrors = ini_get('track_errors');
        ini_set('track_errors', 1);
        $result = null;
        try {
            $host = ($this->port == 443 ? 'ssl://' : '') . $this->host;
            $f = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);
            if (!$f) {
                $this->lastError = 'Error #' . $errno . ': ' . $errstr;
                return null;
            }
            if (
                @fwrite($f, $data) === false
                || !@stream_socket_shutdown($f, STREAM_SHUT_WR)
            ) {
                $this->lastError = $php_errormsg;
                return null;
            }
            $result = @stream_get_contents($f);
            if (
                $result === false
                || !@fclose($f)
            ) {
                $this->lastError = $php_errormsg;
                return null;
            }
            ini_set('track_errors', $initialTrackErrors);
        } catch (\Exception $e) {
            ini_set('track_errors', $initialTrackErrors);
            $this->lastError = $e;
            return null;
        }
        // Analyze the result.
        if (!$result) {
            $this->lastError = '';
            return null;
        }
        @list($headers, $body) = preg_split('/\r?\n\r?\n/s', $result, 2);
        if (!preg_match('{^HTTP/[\d.]+ \s+ ((\d+) [^\r\n]*)}six', $headers, $m)) {
            $this->lastError = 'Non-HTTP response received:' . "\n" . $result;
            return null;
        }
        if ($m[2] != 200) {
            $this->lastError = 'Request failed:' . $m[1] . "\n" . $body;
            return null;
        }
        if (!preg_match('/^Content-Length: \s* (\d+)/mix', $headers, $m)) {
            $this->lastError = 'No Content-Length header in response headers:' . "\n" . $headers;
            return null;
        }
        $needLen = $m[1];
        $recvLen = mb_strlen($body, $this->charset);
        if ($needLen != $recvLen) {
            $this->lastError = 'Response length (' . $recvLen . ') is different than specified in Content-Length header (' . $needLen . '): possibly broken response' . "\n";
            return null;
        }
        $this->lastError = '';
        return $body;
    }

}
