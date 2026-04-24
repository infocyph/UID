Installation
============

Requirements
------------

- PHP ``>=8.2``
- ``ext-bcmath``
- Composer

Install
-------

.. code-block:: bash

   composer require infocyph/uid

Autoloaded Helpers
------------------

UID ships global helper functions via Composer autoload from ``src/functions.php``.

If you prefer explicit static APIs only, call the namespaced classes directly:

- ``Infocyph\\UID\\Id``
- ``Infocyph\\UID\\UUID``
- ``Infocyph\\UID\\ULID``
- ``Infocyph\\UID\\Snowflake``
- ``Infocyph\\UID\\Sonyflake``
- ``Infocyph\\UID\\TBSL``
- ``Infocyph\\UID\\NanoID``
- ``Infocyph\\UID\\CUID2``
- ``Infocyph\\UID\\KSUID``
- ``Infocyph\\UID\\XID``
- ``Infocyph\\UID\\OpaqueId``
- ``Infocyph\\UID\\DeterministicId``

Read the Docs Build
-------------------

This repository already includes:

- ``docs/conf.py``
- ``docs/requirements.txt``
- ``.readthedocs.yaml``

So you can publish directly on Read the Docs without extra Sphinx bootstrapping.
