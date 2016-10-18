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
 * @version 0.1.1 (2016.10.18)
 */

namespace inpassor\realplexor;

trait RealplexorAPI
{

    /**
     * @var string
     */
    public $host = '127.0.0.1';

    /**
     * @var int
     */
    public $port = 10010;

    /**
     * @var string
     */
    public $namespace = '';

    /**
     * @var string
     */
    public $login = '';

    /**
     * @var string
     */
    public $password = '';

    /**
     * @var int
     */
    public $timeout = 5;

    /**
     * Send data to Realplexor.
     * @param mixed $idsAndCursors Target IDs in form of: [id1 => cursor1, id2 => cursor2, ...]
     * or [id1, id2, id3, ...]. If sending to a single ID, you may pass it as a plain string, not array.
     * @param mixed $data Data to be sent (any format, e.g. nested arrays are OK).
     * @param array $showOnlyForIds Send this message to only those who also listen any of these IDs.
     * This parameter may be used to limit the visibility to a closed number of cliens: give each client
     * an unique ID and enumerate client IDs in $showOnlyForIds to not to send messages to others.
     * @throws RealplexorException
     */
    public function send($idsAndCursors, $data, $showOnlyForIds = null)
    {
        $data = json_encode($data);
        $pairs = [];
        foreach ((array)$idsAndCursors as $id => $cursor) {
            if (is_int($id)) {
                $id = $cursor; // this is NOT cursor, but ID!
                $cursor = null;
            }
            if (!preg_match('/^\w+$/', $id)) {
                throw new RealplexorException('Identifier must be alphanumeric, "' . $id . '" given');
            }
            $id = $this->namespace . $id;
            if ($cursor !== null) {
                if (!is_numeric($cursor)) {
                    throw new RealplexorException('Cursor must be numeric, "' . $cursor . '" given');
                }
                $pairs[] = "$cursor:$id";
            } else {
                $pairs[] = $id;
            }
        }
        if (is_array($showOnlyForIds)) {
            foreach ($showOnlyForIds as $id) {
                $pairs[] = "*" . $this->namespace . $id;
            }
        }
        $this->_send(implode(',', $pairs), $data);
    }

    /**
     * Return list of online IDs (keys) and number of online browsers for each ID.
     * ("online" means "connected just now", it is very approximate)
     * @param array $idPrefixes If set, only online IDs with these prefixes are returned.
     * @return array List of matched online IDs (keys) and online counters (values).
     */
    public function cmdOnlineWithCounters($idPrefixes = null)
    {
        // Add namespace
        $idPrefixes = $idPrefixes !== null ? (array)$idPrefixes : [];
        if ($this->namespace) {
            if (!$idPrefixes) {
                // if no prefix passed, we still need namespace prefix
                $idPrefixes = [''];
            }
            foreach ($idPrefixes as $i => $idp) {
                $idPrefixes[$i] = $this->namespace . $idp;
            }
        }
        // Execute
        if (!($responce = trim($this->_sendCmd('online' . ($idPrefixes ? ' ' . implode(' ', $idPrefixes) : ''))))) {
            return [];
        }
        $lines = explode("\n", $responce);
        // Parse
        $result = [];
        foreach ($lines as $line) {
            @list($id, $counter) = explode(' ', $line);
            if (!$id) {
                continue;
            }
            // Cut off namespace
            if ($this->namespace && mb_strpos($id, $this->namespace, 'UTF-8') === 0) {
                $id = substr($id, mb_strlen($this->namespace, 'UTF-8'));
            }
            $result[$id] = $counter;
        }
        return $result;
    }

    /**
     * Return list of online IDs.
     * @param array $idPrefixes If set, only online IDs with these prefixes are returned.
     * @return array List of matched online IDs.
     */
    public function cmdOnline($idPrefixes = null)
    {
        return array_keys($this->cmdOnlineWithCounters($idPrefixes));
    }

    /**
     * Return all Realplexor events (e.g. ID offline/offline changes) happened after $fromPos cursor.
     * @param string $fromPos Start watching from this cursor.
     * @param array $idPrefixes Watch only changes of IDs with these prefixes.
     * @return array List of ["event" => ..., "cursor" => ..., "id" => ...].
     * @throws RealplexorException
     */
    public function cmdWatch($fromPos, $idPrefixes = null)
    {
        $idPrefixes = $idPrefixes !== null ? (array)$idPrefixes : [];
        if (!$fromPos) {
            $fromPos = 0;
        }
        if (!preg_match('/^[\d.]+$/', $fromPos)) {
            throw new RealplexorException('Position value must be numeric, "' . $fromPos . '" given');
        }
        // Add namespace
        if ($this->namespace) {
            if (!$idPrefixes) {
                // if no prefix passed, we still need namespace prefix
                $idPrefixes = [''];
            }
            foreach ($idPrefixes as $i => $idp) {
                $idPrefixes[$i] = $this->namespace . $idp;
            }
        }
        // Execute
        if (!($responce = trim($this->_sendCmd('watch ' . $fromPos . ($idPrefixes ? ' ' . implode(' ', $idPrefixes) : ''))))) {
            return [];
        }
        $lines = explode("\n", $responce);
        // Parse
        $events = [];
        foreach ($lines as $line) {
            if (!preg_match('/^ (\w+) \s+ ([^:]+):(\S+) \s* $/sx', $line, $m)) {
                trigger_error("Cannot parse the event: \"$line\"");
                continue;
            }
            @list($event, $pos, $id) = [$m[1], $m[2], $m[3]];
            // Cut off namespace
            if ($fromPos && $this->namespace && mb_strpos($id, $this->namespace, 'UTF-8') === 0) {
                $id = substr($id, mb_strlen($this->namespace, 'UTF-8'));
            }
            $events[] = [
                'event' => $event,
                'pos' => $pos,
                'id' => $id,
            ];
        }
        return $events;
    }

    /**
     * Send IN command.
     * @param string $cmd Command to send.
     * @return string Server IN response.
     */
    protected function _sendCmd($cmd)
    {
        return $this->_send(null, "$cmd\n");
    }

    /**
     * Send specified data to IN channel. Return response data.
     * @param string $identifier If set, pass this identifier string.
     * @param string $body Data to be sent.
     * @return null|string Response from IN line.
     * @throws RealplexorException
     */
    protected function _send($identifier, $body)
    {
        // Build HTTP request.
        $data = "POST / HTTP/1.1\r\n"
            . 'Host: ' . $this->host . "\r\n"
            . 'Content-Length: ' . mb_strlen($body, 'UTF-8') . "\r\n"
            . 'X-Realplexor: identifier='
            . (($this->login && $this->password) ? $this->login . ':' . $this->password . '@' : '')
            . ($identifier ? $identifier : '')
            . "\r\n"
            . "\r\n"
            . $body;
        // Proceed with sending.
        // TODO: remove exceptions
        $initialTrackErrors = ini_get('track_errors');
        ini_set('track_errors', 1);
        $result = null;
        try {
            $host = ($this->port == 443 ? 'ssl://' : '') . $this->host;
            $f = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);
            if (!$f) {
                throw new RealplexorException("Error #$errno: $errstr");
            }
            if (@fwrite($f, $data) === false) {
                throw new RealplexorException($php_errormsg);
            }
            if (!@stream_socket_shutdown($f, STREAM_SHUT_WR)) {
                throw new RealplexorException($php_errormsg);
            }
            $result = @stream_get_contents($f);
            if ($result === false) {
                throw new RealplexorException($php_errormsg);
            }
            if (!@fclose($f)) {
                throw new RealplexorException($php_errormsg);
            }
            ini_set('track_errors', $initialTrackErrors);
        } catch (RealplexorException $e) {
            ini_set('track_errors', $initialTrackErrors);
            throw $e;
        }
        // Analyze the result.
        if ($result) {
            @list($headers, $body) = preg_split('/\r?\n\r?\n/s', $result, 2);
            if (!preg_match('{^HTTP/[\d.]+ \s+ ((\d+) [^\r\n]*)}six', $headers, $m)) {
                throw new RealplexorException("Non-HTTP response received:\n" . $result);
            }
            if ($m[2] != 200) {
                throw new RealplexorException("Request failed: " . $m[1] . "\n" . $body);
            }
            if (!preg_match('/^Content-Length: \s* (\d+)/mix', $headers, $m)) {
                throw new RealplexorException("No Content-Length header in response headers:\n" . $headers);
            }
            $needLen = $m[1];
            $recvLen = mb_strlen($body, 'UTF-8');
            if ($needLen != $recvLen) {
                throw new RealplexorException("Response length ($recvLen) is different than specified in Content-Length header ($needLen): possibly broken response\n");
            }
            return $body;
        }
        return $result;
    }

}

class RealplexorException extends \Exception
{
}
