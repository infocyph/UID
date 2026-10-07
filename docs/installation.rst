Installation
============

Requirements
------------

- PHP 8.4 or newer
- A 64-bit PHP runtime
- PHP ctype extension
- Composer

BCMath is not required. PSR-16 is optional and is used only when selecting the
simple-cache sequence provider.

Install
-------

.. code-block:: bash

   composer require infocyph/uid

The package autoloads namespaced generator functions from ``src/functions.php``.
Class APIs remain the preferred entry point for parsing, validation, and conversion.

Optional Integrations
---------------------

UID has no mandatory Runwire, PSR-20 clock, or PSR-16 cache dependency. Install
only the integration used by the application:

.. code-block:: bash

   composer require infocyph/runwire:^2.1.1
   composer require psr/clock:^1.0
   composer require psr/simple-cache:^3.0

Runwire enables request-aware cooperative waits, PSR-20 enables injectable wall
clocks through ``GenerationContext``, and PSR-16 is required only for the
simple-cache sequence provider.
