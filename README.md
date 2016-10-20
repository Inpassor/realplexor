Realplexor PHP API
==================

[![Latest Stable Version](https://poser.pugx.org/inpassor/realplexor/version)](https://packagist.org/packages/inpassor/realplexor)
[![Total Downloads](https://poser.pugx.org/inpassor/realplexor/downloads)](https://packagist.org/packages/inpassor/realplexor)
[![License](https://poser.pugx.org/inpassor/realplexor/license)](https://packagist.org/packages/inpassor/realplexor)

Author: Inpassor <inpassor@yandex.com>

GitHub repository: https://github.com/Inpassor/realplexor

This library implements
[Dklab_Realplexor](https://github.com/DmitryKoterov/dklab_realplexor)
PHP API.

Dklab_Realplexor is comet server which handles 1000000+ parallel
browser connections.

## Installation

```
composer require inpassor/realplexor
```

## Usage

Create some class which uses the trait RealplexorAPI:
```
class Realplexor
{
    use \inpassor\realplexor\RealplexorAPI;
    ...
}
```

Create the instance of this class (which uses the trait RealplexorAPI):
```
$realplexor = new Realplexor();
$realplexor->host = '127.0.0.1';
$realplexor->port = 10010;
$realplexor->namespace = 'rpl_';
```

Then use it:
```
$realplexor->send('Alpha',$someData);
```

### Public Properties

Property | Type |Description
--- | --- | ---
host | string | The server host. Default: '127.0.0.1'
port | integer | The connection port. Default: 10010
namespace | string | Namespace to use. Default: ''
login | string | Login for connection (if the server need it). Default: ''
password | string | Password for connection (if the server need it). Default: ''
timeout | integer | The connection timeout, in seconds. Default: 5
charset | integer | Charset used in Content-Type for JSON and other responses. Default: 'UTF-8'
lastError | string | Last error message, if error occured.

### Public methods

Method | Description
--- | ---
[send()](#public-function-sendidsandcursors-data-showonlyforids--null) | Send data to Realplexor.
[cmdOnlineWithCounters()](#public-function-cmdonlinewithcountersidprefixes--) | Return list of online IDs (keys) and number of online browsers for each ID ("online" means "connected just now", it is very approximate).
[cmdOnline()](#public-function-cmdonlineidprefixes--) | Return list of online IDs.
[cmdWatch()](#public-function-cmdwatchfrompos-idprefixes--) | Return all Realplexor events (e.g. ID offline/offline changes) happened after $fromPos cursor.

### Protected methods

Method | Description
--- | ---
[_addNamespace()](#protected-function-_addnamespaceidprefixes) | Add the namespace to ID prefixes.
[_cutNamespace()](#protected-function-_cutnamespaceid) | Cut off the namespace from ID. 
[_sendCmd()](#protected-function-_sendcmdcmd) | Send IN command.
[_send()](#protected-function-_sendidentifier-body) | Send specified data to IN channel. Return response data.

### Method Details

#### public function send($idsAndCursors, $data, $showOnlyForIds = null)

Send data to Realplexor.

Parameter | Type |Description
--- | --- | ---
$idsAndCursors | mixed | Target IDs in form of: [id1 => cursor1, id2 => cursor2, ...] or [id1, id2, id3, ...]. If sending to a single ID, you may pass it as a plain string, not array.
$data | mixed | Data to be sent (any format, e.g. nested arrays are OK).
$showOnlyForIds | array | Send this message to only those who also listen any of these IDs. This parameter may be used to limit the visibility to a closed number of cliens: give each client an unique ID and enumerate client IDs in $showOnlyForIds to not to send messages to others.
**return** | boolean | True on success, false on fail. Check $this->lastError for error message if false returned.

#### public function cmdOnlineWithCounters($idPrefixes = [])

Return list of online IDs (keys) and number of online browsers for each ID ("online" means "connected just now", it is very approximate).

Parameter | Type |Description
--- | --- | ---
$idPrefixes | string\|array | If set, only online IDs with these prefixes are returned.
**return** | array | List of matched online IDs (keys) and online counters (values). Check $this->lastError for error message if empty array returned.

#### public function cmdOnline($idPrefixes = [])

Return list of online IDs.

Parameter | Type |Description
--- | --- | ---
$idPrefixes | string\|array | If set, only online IDs with these prefixes are returned.
**return** | array | List of matched online IDs. Check $this->lastError for error message if empty array returned.

#### public function cmdWatch($fromPos, $idPrefixes = [])

Return all Realplexor events (e.g. ID offline/offline changes) happened after $fromPos cursor.

Parameter | Type |Description
--- | --- | ---
$fromPos | string | Start watching from this cursor.
$idPrefixes | string\|array | Watch only changes of IDs with these prefixes.
**return** | array | List of ["event" => ..., "cursor" => ..., "id" => ...]. Check $this->lastError for error message if empty array returned.

#### protected function _addNamespace($idPrefixes)

Add the namespace to ID prefixes.

Parameter | Type |Description
--- | --- | ---
$idPrefixes | string\|array | ID prefixes without namespace.
**return** | string | ID prefixes with namespace.

#### protected function _cutNamespace($id)

Cut off the namespace from ID.

Parameter | Type |Description
--- | --- | ---
$id | string | ID with namespace.
**return** | string | ID without namespace.

#### protected function _sendCmd($cmd)

Send IN command.

Parameter | Type |Description
--- | --- | ---
$cmd | string | Command to send.
**return** | string\|null | Server IN response. Check $this->lastError for error message if null returned.

#### protected function _send($identifier, $body)

Send specified data to IN channel. Return response data.

Parameter | Type |Description
--- | --- | ---
$identifier | string | If set, pass this identifier string.
$body | string | Data to be sent.
**return** | string\|null | Response from IN line. Check $this->lastError for error message if null returned.

## Client-side

To implement Realplexor client-side feel free to use bower package
[inpassor-jquery-realplexor](https://github.com/Inpassor/jquery-realplexor)
