Installation
============

Requirements
------------

- PHP 8.2 or newer
- A 64-bit PHP runtime
- Composer

BCMath is not required. PSR-16 is optional and is used only when selecting the
simple-cache sequence provider.

Install
-------

.. code-block:: bash

   composer require infocyph/uid

The package autoloads namespaced generator functions from ``src/functions.php``.
Class APIs remain the preferred entry point for parsing, validation, and conversion.
