The Cache Manager
=================

Use the CacheManager to explicitly invalidate or refresh paths, URLs, routes,
tags or responses with specific headers.

By *invalidating* a piece of content, you tell your caching proxy to no longer
serve it to clients. When next requested, the proxy will fetch a fresh copy
from the backend application and serve that instead.

By *refreshing* a piece of content, a fresh copy will be fetched right away.

.. note::

    These terms are explained in more detail in
    :ref:`An Introduction to Cache Invalidation <foshttpcache:invalidation introduction>`.

The cache manager is available in the Symfony DI container using autowiring
with the ``FOS\HttpCacheBundle\CacheManager`` class.

.. _cache manager invalidation:

``invalidatePath()``
--------------------

.. important::

    Make sure to :ref:`configure your proxy <foshttpcache:proxy-configuration>` for purging first.

Invalidate a path::

    $cacheManager->invalidatePath('/users')->flush();

.. note::

    The ``flush()`` method is explained :ref:`below <flushing>`.

Invalidate a URL::

    $cacheManager->invalidatePath('http://www.example.com/users');

Invalidate a route::

    $cacheManager->invalidateRoute('user_details', array('id' => 123));

Invalidate a :ref:`regular expression <foshttpcache:invalidate regex>`::

    $cacheManager->invalidateRegex('.*', 'image/png', array('example.com'));

The cache manager offers a fluent interface::

    $cacheManager
        ->invalidateRoute('villains_index')
        ->invalidatePath('/bad/guys')
        ->invalidateRoute('villain_details', array('name' => 'Jaws')
        ->invalidateRoute('villain_details', array('name' => 'Goldfinger')
        ->invalidateRoute('villain_details', array('name' => 'Dr. No')
    ;

.. _cache manager refreshing:

``refreshPath()`` and ``refreshRoute()``
----------------------------------------

.. note::

    Make sure to :ref:`configure your proxy <foshttpcache:proxy-configuration>` for purging first.

Refresh a path::

    $cacheManager->refreshPath('/users');

Refresh a URL::

    $cacheManager->refreshPath('http://www.example.com/users');

Refresh a Route::

    $cacheManager->refreshRoute('user_details', array('id' => 123));

.. _cache_manager_tags:

``invalidateTags()``
--------------------

Invalidate cache tags::

    $cacheManager->invalidateTags(array('some-tag', 'other-tag'));

.. note::

    Marking a response with tags can be done through the :doc:`ResponseTagger </features/tagging>`.

.. _flushing:

``flush()``
-----------

Internally, the invalidation requests are queued and only sent out to your HTTP
proxy when the manager is flushed. The manager is flushed automatically at the
right moment:

* when handling a HTTP request, after the response has been sent to the client
  (Symfony’s `kernel.terminate event`_)
* when running a console command, after the command has finished (Symfony’s
  `console.terminate event`_).

You can also flush the cache manager manually::

    $cacheManager->flush();

.. _kernel.terminate event: https://symfony.com/doc/current/components/http_kernel.html#the-kernel-terminate-event
.. _console.terminate event: https://symfony.com/doc/current/components/console/events.html#the-consoleevents-terminate-event
