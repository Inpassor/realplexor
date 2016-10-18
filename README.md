Realplexor PHP API
==================

Author: Inpassor <inpassor@yandex.com>

GitHub repository: https://github.com/Inpassor/realplexor

This library implements
[Dklab_Realplexor](https://github.com/DmitryKoterov/dklab_realplexor)
PHP API.

Dklab_Realplexor is comet server which handles 1000000+ parallel
browser connections.

## Installation

1) Add package to your project using composer:
```
composer require inpassor/realplexor
```

2) Use the trait RealplexorAPI whenever you want:
```
class Realplexor
{
    use \inpassor\realplexor\RealplexorAPI;
    ...
}
```

There are several public properties that available to configure:

Property | Description
--- | ---
**host** | Default: 127.0.0.1
**port** | Default: 10010
**namespace** | Default: ''
**login** | Default: ''
**password** | Default: ''
**timeout** | Default: 5

## Usage

Create and configure Realplexor instance (which uses RealplexorAPI trait):
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

To implement Realplexor client-side use \inpassor\assets\JqueryRealplexor
asset, that refers to bower package
[inpassor-jquery-realplexor](https://github.com/Inpassor/jquery-realplexor)
